<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\Framework\DataObjectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\AddressFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;

class SveaShippingInfo
{
    /**
     * @var Json
     */
    private Json $jsonSerializer;

    /**
     * @var DataObjectFactory
     */
    private DataObjectFactory $dataObjectFactory;

    /**
     * @var AddressFactory
     */
    private AddressFactory $addressFactory;

    /**
     * Read by Carrier model, excludes Svea Shipping as a listed shipping option if set to true
     *
     * @var bool
     */
    private $excludeSveaShipping = true;

    public function __construct(
        Json $jsonSerializer,
        DataObjectFactory $dataObjectFactory,
        AddressFactory $addressFactory
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->addressFactory = $addressFactory;
    }

    /**
     * Get data object with Svea Shipping Info from quote
     *
     * @param Quote $quote
     * @return DataObject|null
     */
    public function getFromQuote(Quote $quote): ?DataObject
    {
        $extShippingInfo = $quote->getExtShippingInfo();
        if (!$extShippingInfo) {
            return null;
        }

        try {
            $extShipInfoArray = $this->jsonSerializer->unserialize($extShippingInfo);
        } catch (\Exception $e) {
            return null;
        }

        $sveaArray = $extShipInfoArray['svea_shipping_info'] ?? [];
        if (count($sveaArray) < 1) {
            return null;
        }
        return $this->dataObjectFactory->create()->setData($sveaArray);
    }

    /**
     * Set Svea Shipping Info on quote
     *
     * @param Quote $quote
     * @param array $content
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setInQuote(Quote $quote, array $content): void
    {
        $contentObj = $this->dataObjectFactory->create()->setData($content);
        try {
            $extShippingInfo = $this->jsonSerializer->unserialize($quote->getExtShippingInfo());
        } catch (\Exception $e) {
            $extShippingInfo = [];
        }

        $extShippingInfo['svea_shipping_info'] = $contentObj->toArray();

        $quote->setExtShippingInfo($this->jsonSerializer->serialize($extShippingInfo));
        $quote->getPayment()->setAdditionalInformation('svea_shipping_info', $contentObj->toArray());
    }

    /**
     * Get data object with Svea Shipping Info from order
     *
     * @param Order $order
     * @return DataObject|null
     */
    public function getFromOrder(Order $order): ?DataObject
    {
        $sveaShippingInfo = $order->getPayment()->getAdditionalInformation()['svea_shipping_info'] ?? [];
        if (count($sveaShippingInfo) < 1) {
            return null;
        }
        return $this->dataObjectFactory->create()->setData($sveaShippingInfo);
    }

    /**
     * Create order address from location data object
     *
     * @param DataObject $location
     * @return Address
     */
    public function createOrderAddressFromLocation(DataObject $location): Address
    {
        $address = $this->addressFactory->create();
        $locationAddress = $location->getAddress();
        $address->setCompany($location->getName());
        $address->setCountryId($locationAddress['countryCode']);
        $address->setPostcode($locationAddress['postalCode']);
        $address->setCity($locationAddress['city']);
        $street = [$locationAddress['streetAddress']];
        if (isset($locationAddress['streetAddress2']) && !empty($locationAddress['streetAddress2'])) {
            $street[] = $locationAddress['streetAddress2'];
        }
        $address->setStreet($street);
        return $address;
    }

    public function setExcludeSveaShipping(bool $exclude): void
    {
        $this->excludeSveaShipping = $exclude;
    }

    public function getExcludeSveaShipping(): bool
    {
        return $this->excludeSveaShipping;
    }
}

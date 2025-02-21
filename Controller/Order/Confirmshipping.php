<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Checkout\Model\ShippingInformationFactory;
use Magento\Checkout\Model\ShippingInformationManagement;
use Svea\Checkout\Api\UsesServiceContainerInterface;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Controller\Order\Update;

class Confirmshipping extends Update implements HttpPostActionInterface, UsesServiceContainerInterface
{
    const SERVICE_CONTAINER_NAME = 'controller:order:confirmshipping';

    /**
     * @var ShippingInformationFactory
     */
    private $shipInfoFactory;

    /**
     * @var ShippingInformationManagement
     */
    private $shipInfoManagement;

    /**
     * @var SveaShippingInfo
     */
    private $shipInfoService;

    public function execute()
    {
        $this->assignServices();
        $request = $this->getRequest();
        /** @var HttpRequest $request */
        $quote = $this->checkoutSession->getQuote();
        $content = $request->getPost()->toArray();
        $carrier = $content['carrier'];

        try {
            $this->shipInfoService->setInQuote($quote, $content);
        } catch (\Exception $e) {
            return $this->returnError();
        }

        $shipInfo = $this->shipInfoFactory->create();
        $shipInfo->setBillingAddress($quote->getBillingAddress());
        $shipInfo->setShippingAddress($quote->getShippingAddress());
        $shipInfo->setShippingCarrierCode(Carrier::CODE);
        $shipInfo->setShippingMethodCode(strtolower($carrier));

        try {
            $this->shipInfoManagement->saveAddressInformation($quote->getId(), $shipInfo);
        } catch (\Exception $e) {
            return $this->returnError();
        }

        return $this->_sendResponse([
            'cart',
            'coupon',
            'shipping',
            'shipping_method',
            'messages',
            'svea'
        ], true, true);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Json
     */
    private function returnError(): \Magento\Framework\Controller\Result\Json
    {
        return $this->jsonResultFactory->create()->setData(
            [
                'reload' => true,
                'messages' => __('We couln\'t save the shipping information. Checkout will now reload.')
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getServiceContainerName(): string
    {
        return self::SERVICE_CONTAINER_NAME;
    }

    /**
     * @inheritDoc
     */
    public function assignServices(): void
    {
        $serviceContainer = $this->sveaCheckoutContext->getServiceContainer($this->getServiceContainerName());
        $this->shipInfoFactory = $serviceContainer['shipInfoFactory'];
        $this->shipInfoManagement = $serviceContainer['shipInfoManagement'];
        $this->shipInfoService = $serviceContainer['shipInfoService'];
    }
}

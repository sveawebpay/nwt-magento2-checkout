<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Checkout;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\UrlInterface;
use Magento\Checkout\Model\Session;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory as CountryCollectionFactory;
use Svea\Checkout\Helper\Data;

class International implements ArgumentInterface
{
    private Data $helper;

    private Session $checkoutSession;

    private CountryCollectionFactory $countryCollectionFactory;

    private UrlInterface $url;

    public function __construct(
        Data $helper,
        Session $checkoutSession,
        CountryCollectionFactory $countryCollectionFactory,
        UrlInterface $url
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->countryCollectionFactory = $countryCollectionFactory;
        $this->url = $url;
    }

    /**
     * Get country options formatted for checkout display
     *
     * @return array
     */
    public function getCountryOptions(): array
    {
        $allowedCountries = $this->helper->getGeneralAllowedCountries();
        $collection = $this->countryCollectionFactory->create();
        $collection->addFieldToFilter('country_id', ['in' => $allowedCountries]);
        $optionArray = $collection->toOptionArray(false);

        $selectedCountry = $this->getSelectedCountry();
        foreach ($optionArray as &$option) {
            $option['selected'] = $option['value'] === $selectedCountry;
        }

        return $optionArray;
    }

    /**
     * Action for country select form, points to Svea\Checkout\Controller\Order\ChangeCountry
     *
     * @return string
     */
    public function getFormAction(): string
    {
        return $this->url->getUrl('*/order/changeCountry');
    }

    /**
     * @return string
     */
    private function getSelectedCountry(): string
    {
        $quote = $this->checkoutSession->getQuote();
        $mainAddress = $quote->getShippingAddress();
        if ($quote->isVirtual()) {
            $mainAddress = $quote->getBillingAddress();
        }

        return $mainAddress->getCountryId() ?? '';
    }
}

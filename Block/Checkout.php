<?php
namespace Svea\Checkout\Block;

use Svea\Checkout\Model\Svea\Context;
/**
 * @method int getSessionLifetimeSeconds()
 */
class Checkout extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote;

    /**
     * @var \Magento\Quote\Model\Quote\Address
     */
    protected $_address;

    /**
     * @var \Magento\Customer\Model\Address\Config
     */
    protected $_addressConfig;

    /**
     * Currently selected shipping rate
     *
     * @var Rate
     */
    protected $_currentShippingRate = null;

    /**
     * Paypal controller path
     *
     * @var string
     */
    protected $_controllerPath = 'sveacheckout/order';

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $_taxHelper;

    /**
     * @var \Magento\Framework\Pricing\PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry;

    /**
     * @var \Magento\Cms\Model\Template\FilterProvider
     */

    protected $filterProvider;

    protected $getCurrentQuoteService;

    protected $getCurrentSveaOrderIdService;

    protected $iframeSnippet;

    /**
     * @var Context
     */
    private $checkoutContext;
    private $giftCardHelper;


    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Customer\Model\Address\Config $addressConfig
     * @param \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency
     * @param \Magento\Cms\Model\Template\FilterProvider
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Svea\Checkout\Service\GetCurrentQuote $getCurrentQuoteService
     * @param \Svea\Checkout\Service\GetCurrentSveaOrderId $getCurrentSveaOrderIdService,
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        \Magento\Cms\Model\Template\FilterProvider $filterProvider,
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Service\GetCurrentQuote $getCurrentQuoteService,
        \Svea\Checkout\Service\GetCurrentSveaOrderId $getCurrentSveaOrderIdService,
        \Svea\Checkout\Model\Svea\Context $checkoutContext,
        \Svea\Checkout\Helper\GiftCard $giftCardHelper,
        array $data = []
    ) {
        $this->priceCurrency = $priceCurrency;
        $this->_taxHelper = $taxHelper;
        $this->_addressConfig = $addressConfig;
        $this->filterProvider = $filterProvider;
        $this->helper = $helper;
        $this->getCurrentSveaOrderIdService = $getCurrentSveaOrderIdService;
        $this->getCurrentQuoteService = $getCurrentQuoteService;
        $this->checkoutContext = $checkoutContext;
        $this->giftCardHelper = $giftCardHelper;
        parent::__construct($context, $data);
    }

    public function getCartCtrlKeyCookieName()
    {
        return $this->helper->getCartCtrlKeyCookieName();
    }

    public function generateHashSignatureByQuote()
    {
        return $this->helper->generateHashSignatureByQuote($this->getQuote());
    }

    public function subscribeNewsletter()
    {
        return $this->helper->subscribeNewsletter($this->getQuote()->getPayment());
    }

    public function helper()
    {
        return $this->helper;
    }

    public function getQuote()
    {
        if (!$this->_quote) {
            $this->_quote = $this->getCurrentQuoteService->getQuote();
        }
        return $this->_quote;
    }

    public function getBlockFilter()
    {
        return $this->filterProvider->getBlockFilter();
    }
    public function filter($text)
    {
        return $this->filterProvider->getBlockFilter()->filter($text);
    }

    /**
     * Quote object setter
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return $this
     */
    public function setQuote(\Magento\Quote\Model\Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    public function getCouponCode()
    {
        return $this->getQuote()->getCouponCode();
    }

    /**
     * @return array
     */
    public function getCheckoutConfig() : array
    {
        return $this->checkoutContext->getConfig();
    }

    /**
     * Return quote billing address
     *
     * @return \Magento\Quote\Model\Quote\Address
     */
    public function getBillingAddress()
    {
        return $this->getQuote()->getBillingAddress();
    }

    /**
     * Return quote shipping address
     *
     * @return false|\Magento\Quote\Model\Quote\Address
     */
    public function getShippingAddress()
    {
        if ($this->getQuote()->getIsVirtual()) {
            return false;
        }
        return $this->getQuote()->getShippingAddress();
    }

    public function getSveaCheckoutKey()
    {
        return $this->helper->getMerchantId();
    }

    public function getSveaOrderId()
    {
        return $this->getCurrentSveaOrderIdService->getSveaOrderId();
    }

    /**
     * Return carrier name from config, base on carrier code
     *
     * @param string $carrierCode
     * @return string
     */
    public function getCarrierName($carrierCode)
    {
        if ($name = $this->_scopeConfig->getValue("carriers/{$carrierCode}/title", \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return $name;
        }
        return $carrierCode;
    }

    /**
     * Get either shipping rate code or empty value on error
     *
     * @param \Magento\Framework\DataObject $rate
     * @return string
     */
    public function renderShippingRateValue(\Magento\Framework\DataObject $rate)
    {
        if ($rate->getErrorMessage()) {
            return '';
        }
        return $rate->getCode();
    }

    /**
     * Get shipping rate code title and its price or error message
     *
     * @param \Magento\Framework\DataObject $rate
     * @param string $format
     * @param string $inclTaxFormat
     * @return string
     */
    public function renderShippingRateOption($rate, $format = '%s - %s%s', $inclTaxFormat = ' (%s %s)')
    {
        $renderedInclTax = '';
        if ($rate->getErrorMessage()) {
            $price = $rate->getErrorMessage();
        } else {
            $price = $this->_getShippingPrice(
                $rate->getPrice(),
                $this->_taxHelper->displayShippingPriceIncludingTax()
            );

            $incl = $this->_getShippingPrice($rate->getPrice(), true);
            if ($incl != $price && $this->_taxHelper->displayShippingBothPrices()) {
                $renderedInclTax = sprintf($inclTaxFormat, $this->escapeHtml(__('Incl. Tax')), $incl);
            }
        }
        $title = $rate->getMethodTitle() ?: $rate->getCarrierTitle();
        $title = $title ?: $rate->getCode();

        return sprintf($format, $this->escapeHtml($title), $price, $renderedInclTax);
    }

    /**
     * Getter for current shipping rate
     *
     * @return string
     */
    public function getCurrentShippingRate()
    {
        if ($this->_currentShippingRate) {
            return $this->_currentShippingRate->getCode();
        }

        return "";
    }

    /**
     * Set controller path
     *
     * @param string $prefix
     * @return void
     */
    public function setControllerPath($prefix)
    {
        $this->_controllerPath = $prefix;
    }

    /**
     * Return formatted shipping price
     *
     * @param float $price
     * @param bool $isInclTax
     * @return string
     */
    protected function _getShippingPrice($price, $isInclTax)
    {
        return $this->_formatPrice($this->_taxHelper->getShippingPrice($price, $isInclTax));
    }

    /**
     * Format price base on store convert price method
     *
     * @param float $price
     * @return string
     */
    protected function _formatPrice($price)
    {
        return $this->priceCurrency->convertAndFormat(
            $price,
            true,
            \Magento\Framework\Pricing\PriceCurrencyInterface::DEFAULT_PRECISION,
            $this->getQuote()->getStore()
        );
    }

    public function getIframeSnippet()
    {
        return $this->iframeSnippet;
    }

    public function setIframeSnippet($snippet)
    {
        $this->iframeSnippet = $snippet;
    }

    /**
     * Retrieve payment method and assign additional template values
     *
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function _beforeToHtml()
    {
        if (!$this->getQuote()->getIsVirtual()) {

            // prepare shipping rates
            $this->_address = $this->getQuote()->getShippingAddress();
            $groups = $this->_address->getGroupedAllShippingRates();
            if ($groups && $this->_address) {
                $this->setShippingRateGroups($groups);
                // determine current selected code & name
                foreach ($groups as $code => $rates) {
                    foreach ($rates as $rate) {
                        if ($this->_address->getShippingMethod() == $rate->getCode()) {
                            $this->_currentShippingRate = $rate;
                            break 2;
                        }
                    }
                }
            }
        }

        // misc shipping parameters
        $this->setShippingMethodSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/SaveShippingMethod")
        )->setShippingAddressSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/ShippingAddressChange")
        )->setCommentSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/SaveComment")
        )->setNewsletterSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/SaveNewsletter")
        )->setCouponSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/SaveCoupon")
        )->setGiftcardSubmitUrl(
            $this->getUrl("{$this->_controllerPath}/SaveGiftCard")
        );

        return parent::_beforeToHtml();
    }

    /**
     * @return bool
     */
    public function isRewardEnabled()
    {
        return $this->helper->isRewardEnabled($this->_storeManager->getStore());
    }

    /**
     * @return bool
     */
    public function isSveaShippingActive()
    {
        return $this->helper->getSveaShippingActive($this->_storeManager->getStore());
    }

    /**
     * @return int
     */
    public function getSveaCreatedAt(): int
    {
        return (int)$this->getQuote()->getPayment()->getAdditionalInformation('svea_created_at');
    }
}

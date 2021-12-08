<?php
namespace Svea\Checkout\Helper;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;

/**
 * Class Data
 * @package Svea\Checkout\Helper
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Cart Cookie name. Will be used to check if the cart was updated
     */
    const COOKIE_CART_CTRL_KEY = 'SveaCartCtrlKey';

    /**
     * Svea System Settings, Connection group
     */
    const XML_PATH_CONNECTION  = 'svea_checkout/connection/';

    /**
     * Svea System Settings, settings group
     */
    const XML_PATH_SETTINGS = 'svea_checkout/settings/';

    /**
     * Svea System Settings, layout group
     */
    const XML_PATH_LAYOUT = 'svea_checkout/layout/';


    const XML_PATH_PRODUCT_CAMPAIGN = 'svea_checkout/campaign_widget/';

    /**
     * Svea Payment, test API url
     */
    const API_BASE_URL_TEST = "https://checkoutapistage.svea.com";

    /**
     * Svea Payment, live API url
     */
    const API_BASE_URL_LIVE = "https://checkoutapi.svea.com";

    /**
     * Base URL For Svea Checkout Administration Production server
     */
    const API_ADMIN_BASE_URL_LIVE = 'https://paymentadminapi.svea.com';

    /**
     * Base URL For Svea Checkout Administration Demo server
     */
    const API_ADMIN_BASE_URL_TEST = 'https://paymentadminapistage.svea.com';

    /**
      * Svea Partner key
      */
    const PARTNER_KEY = "2E999136-F4CC-465B-B5CB-873C28E93EEC";

    /**
     * Invoice fee row
     */
    const INVOICE_FEE_ARTICLE_NUMBER = '6eaceaec-fffc-41ad-8095-c21de609bcfd';

    /**
     * Store manager
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /** @var \Svea\Checkout\Model\Svea\Locale $sveaLocale */
    protected $sveaLocale;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Svea\Checkout\Model\Svea\Locale $locale
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Svea\Checkout\Model\Svea\Locale $locale
    ) {
        $this->sveaLocale = $locale;
        $this->storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getSharedSecret($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONNECTION . 'shared_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getMerchantId($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CONNECTION . 'merchant_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function isEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CONNECTION . 'enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function isTestMode($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_CONNECTION . 'test_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getInvoiceFeeLabel($store = null)
    {
        return __("Invoice Fee");
    }

    /**
     * @param null $store
     * @return string
     */
    public function getApiUrl($store = null)
    {
        if ($this->isTestMode($store)) {
            return self::API_BASE_URL_TEST;
        } else {
            return self::API_BASE_URL_LIVE;
        }
    }

    public function getAdminApiUrl($store=null)
    {
        if ($this->isTestMode($store)) {
            return self::API_ADMIN_BASE_URL_TEST;
        } else {
            return self::API_ADMIN_BASE_URL_LIVE;
        }
    }

    /**
     * @param null $store
     * @return bool
     */
    protected function _replaceCheckout($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SETTINGS . 'replace_checkout',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return bool
     */
    public function replaceCheckout($store = null)
    {
        return $this->isEnabled($store) && $this->_replaceCheckout($store);
    }

    /**
     * @param null $store
     * @return bool
     */
    public function registerCustomerOnCheckout($store = null)
    {
        return $this->getStoreConfigFlag(self::XML_PATH_SETTINGS . 'register_customer', $store);
    }

    /**
     * @param null $store
     * @return bool
     */
    public function isCampaignWidgetActive($store = null)
    {
        return $this->getStoreConfigFlag(self::XML_PATH_PRODUCT_CAMPAIGN . 'enable', $store);
    }

    /**
     * @param null $store
     * @return bool
     */
    public function canCapture($store = null)
    {
        return $this->getStoreConfigFlag(self::XML_PATH_SETTINGS . 'can_capture', $store);
    }

    /**
     * @param null $store
     * @return bool
     */
    public function canCapturePartial($store = null)
    {
        return $this->getStoreConfigFlag(self::XML_PATH_SETTINGS . 'can_capture_partial', $store);
    }

    /** Helpers */
    public function getCheckoutPath($path = null)
    {
        if (empty($path)) {
            return 'sveacheckout';
        }

        return 'sveacheckout/order/' . trim(ltrim($path, '/'));
    }

    /**
     * @return string
     */
    public function getSuccessPageUrl()
    {
        return $this->getCheckoutUrl('success');
    }

    public function getConfirmationUrl($hash)
    {
        return $this->getCheckoutUrl('confirmation', ['hash' => $hash, '_escape_params' => false]);
    }

    /**
     * @param $hash
     *
     * @return string
     */
    public function getValidationUrl($hash)
    {
        $proxyDomain = $this->getDevProxyBaseUrl();

        return is_null($proxyDomain)
            ? $this->getCheckoutUrl('validateOrder', ['sid'=>'{checkout.order.uri}', 'hash' => $hash, '_escape_params' => false])
            : "$proxyDomain/sveacheckout/order/validateOrder/sid/{checkout.order.uri}/hash/$hash";
    }

    /**
     * @param $hash
     * @return string
     */
    public function getPushUrl($hash)
    {
        $proxyDomain = $this->getDevProxyBaseUrl();

        return is_null($proxyDomain)
            ? $this->getCheckoutUrl('push', ['sid'=>'{checkout.order.uri}','hash' => $hash, '_escape_params' => false])
            : "$proxyDomain/sveacheckout/order/push/sid/{checkout.order.uri}/$hash";
    }

    /**
     * @return string|null
     */
    private function getDevProxyBaseUrl()
    {
        return $this->getStoreConfig('dev/svea/webhook_domain');
    }

    /**
     * @param null $path
     * @param array $params
     * @return string
     */
    public function getCheckoutUrl($path = null, $params = [])
    {
        if (empty($path)) {
            return $this->_getUrl('sveacheckout', $params);
        }
        return $this->_getUrl($this->getCheckoutPath($path), $params);
    }

    /**
     * @param null $store
     * @return string
     */
    public function getTermsUrl($store = null)
    {
        //if there are multiple pages with same url key; magento will generate options with key|id
        $url = explode('|', (string)$this->getStoreConfig(self::XML_PATH_SETTINGS . 'terms_url', $store));
        return $this->_getUrl($url[0]);
    }

    /**
     * @return string
     */
    public function getCartCtrlKeyCookieName()
    {
        return self::COOKIE_CART_CTRL_KEY;
    }

    /**
     * @param Payment $payment
     * @return bool
     */
    public function subscribeNewsletter(Payment $payment)
    {
        if ($payment) {
            $status = (int)$payment->getAdditionalInformation("svea_checkout_newsletter");
        } else {
            $status = null;
        }

        if ($status) { //when is set (in quote) is -1 for NO, 1 for Yes
            return $status>0;
        } else {
            //get default value from settings
            return $this->getStoreConfigFlag(self::XML_PATH_SETTINGS . 'newsletter_subscribe', $payment->getQuote()->getStore()->getId());
        }
    }

    public function showCouponLayout($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LAYOUT . 'display_discount',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return array|null
     */
    public function getCountries($store = null)
    {
        $values = $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'allowed_countries',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        return $this->splitStringToArray($values);
    }

    public function getDefaultCountry($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'default_country',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getDefaultConsumerType($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'default_customer_type',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getDefaultShippingMethod($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'default_shipping_method',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getConsumerTypes($store = null)
    {
        $values =  $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'customer_types',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        return $this->splitStringToArray($values);
    }

    /**
     * @param null $store
     * @return int
     */
    public function getMaximumAmountDiff($store = null)
    {
        $allowDiff = $this->scopeConfig->isSetFlag(
            self::XML_PATH_SETTINGS . 'allow_decimal_diff',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        if (!$allowDiff) {
            return 0;
        }

        $diff = $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'maximum_decimal_diff',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );

        if (!$diff) {
            return 0;
        }

        return (int) $diff;
    }

    /**
     * This function returns a hash, we will use it to check for changes in the quote!
     * @param Quote $quote
     * @return string
     */
    public function generateHashSignatureByQuote(Quote $quote)
    {
        $shippingMethod = null;
        $countryId = null;
        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $countryId = $shippingAddress->getCountryId();
            $shippingMethod = $shippingAddress->getShippingMethod();
        }

        $billingAddress = $quote->getBillingAddress();
        $info = [
            'currency'=> $quote->getQuoteCurrencyCode(),
            'shipping_method' => $shippingMethod,
            'shipping_country' => $countryId,
            'billing_country' =>$billingAddress->getCountryId(),
            'payment' => $quote->getPayment()->getMethod(),
            'subtotal'=> sprintf("%.2f", round($quote->getBaseSubtotal(), 2)), //store base (currency will be set in checkout)
            'total'=> sprintf("%.2f", round($quote->getBaseGrandTotal(), 2)),  //base grand total
            'items'=> []
        ];

        foreach ($quote->getAllVisibleItems() as $item) {
            $info['items'][$item->getId()] = sprintf("%.2f", round($item->getQty()*$item->getBasePriceInclTax(), 2));
        }
        ksort($info['items']);
        return md5(serialize($info));
    }

    /**
     * @param $path
     * @param null $store
     * @return mixed
     */
    public function getStoreConfig($path, $store = null)
    {
        return $this->scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param $path
     * @param null $store
     * @return bool
     */
    public function getStoreConfigFlag($path, $store = null)
    {
        return $this->scopeConfig->isSetFlag(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getAdditionalBlock($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_LAYOUT . 'additional_block',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    protected function splitStringToArray($values)
    {
        return preg_split("#\s*[ ,;]\s*#", $values, null, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * @param null $store
     *
     * @return mixed
     */
    public function isSendOrderEmail($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SETTINGS . 'send_order_email',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getPartnerKey(): string
    {
        return self::PARTNER_KEY;
    }

    /**
     * Check if reward functionality is available
     */
    public function isRewardEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
             self::XML_PATH_LAYOUT . 'use_reward_points',
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
             $store
         );
    }
}

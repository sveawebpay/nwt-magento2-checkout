<?php declare(strict_types=1);

namespace Svea\Checkout\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Svea\Checkout\Helper\Data;

class Layout
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var Data
     */
    private Data $data;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $data,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession
    ) {
        $this->storeManager = $storeManager;
        $this->data = $data;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Returns shipping template path if Svea Shipping is disabled, so that standard shipping will be shown
     * Otherwise returns null since then shipping block should not be displayed
     *
     * @param string $template
     * @return string|null
     */
    public function getShippingTemplate($template = 'Svea_Checkout::checkout/shipping.phtml'): ?string
    {
        $store = $this->storeManager->getStore();
        if ($this->data->getSveaShippingActive($store->getCode())) {
            return null;
        }

        return $template;
    }

    /**
     * If set to require Customer account, Recurring is only shown if customer is logged in
     *
     * @param string $template
     * @return string|null
     */
    public function getRecurringTemplate($template = 'Svea_Checkout::payment/checkout/recurring.phtml'): ?string
    {
        $disable =
            !$this->data->getRecurringPaymentsActive()
            || ($this->data->getRecurringRequireAccount() && !$this->customerSession->isLoggedIn());
        if ($disable) {
            return null;
        }

        return $template;
    }

    /**
     * Standard success page template – if order didn't use Svea payment method don't render Svea things on success page
     *
     * @param string $template Default template, can also be set as function param in layout xml
     * @return string|null
     */
    public function getSuccessTemplateForStandardSuccessPage(
        string $template = 'Svea_Checkout::onepage/success.phtml'
    ): ?string {
        try {
            $order = $this->checkoutSession->getLastRealOrder();
        } catch (\Throwable) {
            return null;
        }

        if ('sveacheckout' !== $order->getPayment()->getMethod()) {
            return null;
        }

        return $template;
    }
}

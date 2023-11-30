<?php

namespace Svea\Checkout\Block\Payment\Checkout;

use Svea\Checkout\Model\Client\Api\OrderManagement;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;
use Svea\Checkout\Model\RecurringInfoRepository;
use Svea\Checkout\Service\FrequencyOptionDisplay;

/**
 * @method OrderManagement getSveaOrderManagement()
 * @method SveaRecurringInfo getRecurringInfoService()
 * @method RecurringInfoRepository getRecurringInfoRepo()
 */
class Info extends \Magento\Payment\Block\Info
{
    use FrequencyOptionDisplay;

    const CANCEL_URL_PATH = 'svea/recurring/cancel';

    private ?ModelRecurringInfo $recurringInfo = null;

    /**
     * @var string
     */
    protected $_template = 'Svea_Checkout::payment/checkout/info.phtml';

    /**
     * @return string
     */
    public function toPdf()
    {
        $this->setTemplate('Svea_Checkout::payment/checkout/pdf.phtml');
        return $this->toHtml();
    }

    public function getSveaPaymentMethod()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_payment_method');
        } catch (\Exception $e) {
            return "";
        }
    }

    public function getSveaCheckoutId()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_order_id');
        } catch (\Exception $e) {
            return "";
        }
    }

    public function getSveaBillingReferences(): array
    {
        // Only company orders paid with invoice has billing references
        // We skip the API call to check for them in other cases
        $isCompany = $this->getInfo()->getAdditionalInformation('is_company');
        $isInvoicePayment = ('INVOICE' === $this->getSveaPaymentMethod());
        if (!$isCompany || !$isInvoicePayment) {
            return [];
        }

        $handler = $this->getSveaOrderManagement();
        $handler->resetCredentials($this->getMethod()->getStore());
        try {
            $sveaOrder = $handler->getOrder($this->getSveaCheckoutId());
            return $sveaOrder->getBillingReferences();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSveaCustomerReference()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_customer_reference');
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * @return ModelRecurringInfo
     */
    public function getRecurringInfo(): ModelRecurringInfo
    {
        if (!$this->recurringInfo) {
            $this->recurringInfo = $this->getRecurringInfoService()->paymentInfoGetter($this->getInfo());
        }
        return $this->recurringInfo;
    }

    /**
     * @return string
     */
    public function getCancelRecurringHtml(): string
    {
        return $this->getChildHtml('cancel_recurring_button');
    }

    /**
     * @inheritDoc
     */
    protected function _beforeToHtml()
    {
        $recurringInfo = $this->getRecurringInfo();
        if (!$recurringInfo->getId()) {
            return parent::_beforeToHtml();
        }

        $recurringToken = $recurringInfo->getRecurringToken();
        $orderId = $this->getRequest()->getParam('order_id');

        $urlParams = ['order_id' => $orderId, 'token' => $recurringToken, '_secure' => true];
        $submitUrl = $this->getUrl(self::CANCEL_URL_PATH, $urlParams);

        $message = __('Are you sure you wish to cancel the subscription? This cannot be undone!');

        $onclick = "confirmSetLocation('{$message}', '{$submitUrl}');";
        $this->addChild(
            'cancel_recurring_button',
            \Magento\Backend\Block\Widget\Button::class,
            [
                'id' => 'cancel_recurring_button',
                'label' => __('Cancel Subscription'),
                'class' => 'action-secondary save',
                'onclick' => $onclick
            ]
        );
        return parent::_beforeToHtml();
    }
}

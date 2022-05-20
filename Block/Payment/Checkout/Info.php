<?php

namespace Svea\Checkout\Block\Payment\Checkout;


class Info extends \Magento\Payment\Block\Info
{
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

    public function getSveaCustomerReference()
    {
        try {
            return $this->getInfo()->getAdditionalInformation('svea_customer_reference');
        } catch (\Exception $e) {
            return "";
        }
    }
}

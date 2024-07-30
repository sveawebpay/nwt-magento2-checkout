<?php

namespace Svea\Checkout\Model\Invoice\Total;

use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Fee extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $amount = $invoice->getOrder()->getSveaInvoiceFee();
        if (!$amount) {
            return $this;
        }

        $invoiceFeeInvoiced =
            $invoice->getOrder()->getPayment()->getAdditionalInformation()['svea_invoice_fee_invoiced']
            ?? false
        ;

        if ($invoiceFeeInvoiced) {
            return $this;
        }

        $invoice->setSveaInvoiceFee($amount);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $amount);
        return $this;
    }
}
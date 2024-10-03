<?php
namespace Svea\Checkout\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;


class AddInvoiceFeeToOrder implements ObserverInterface
{

    public function execute(EventObserver $observer)
    {
        $quote = $observer->getQuote();
        $invoiceFee = $quote->getSveaInvoiceFee();
        if (!$invoiceFee) {
            return $this;
        }

        $order = $observer->getOrder();
        $order->setData('svea_invoice_fee', $invoiceFee);
        $order->setGrandTotal($order->getGrandTotal() + $invoiceFee);

        return $this;
    }
    
 }


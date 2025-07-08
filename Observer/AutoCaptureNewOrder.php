<?php declare(strict_types=1);

namespace Svea\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Logger\Logger;

/**
 * Auto Capture Observer
 */
class AutoCaptureNewOrder implements ObserverInterface
{
    public function __construct(
        Data $config,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        InvoiceSender $invoiceSender,
        Logger $logger
    ) {
        $this->config = $config;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
    }

    /**
     * @var Data
     */
    private Data $config;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * Will create and capture invoice for a new order if configured
     * Event: checkout_type_onepage_save_order_after
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getData('order');
        /** @var \Magento\Sales\Model\Order $order */
        if ($order->getPayment()->getMethod() !== 'sveacheckout') {
            return;
        }

        $storeId = (int)$order->getStoreId();
        if (!$this->config->canCapture($storeId) || !$this->config->autoCapture($storeId)) {
            return;
        }

        try {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->capture();

            if ($this->config->isSendOrderEmail($storeId)) {
                $this->invoiceSender->send($invoice);
            }

            // Save invoice and order in one transaction
            $transactionSave = $this->transactionFactory->create();
            $transactionSave->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Error on Auto Capture Invoice for order %s', $order->getIncrementId()));
            $this->logger->error($e);
        }
    }
}

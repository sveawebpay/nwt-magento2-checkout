<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Observer;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Logger\Logger;
use Svea\Checkout\Observer\AutoCaptureNewOrder;

class AutoCaptureNewOrderTest extends TestCase
{
    private Data&MockObject $config;
    private InvoiceService&MockObject $invoiceService;
    private TransactionFactory&MockObject $transactionFactory;
    private InvoiceSender&MockObject $invoiceSender;
    private Logger&MockObject $logger;
    private AutoCaptureNewOrder $observer;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Data::class);
        $this->invoiceService = $this->createMock(InvoiceService::class);
        $this->transactionFactory = $this->createMock(TransactionFactory::class);
        $this->invoiceSender = $this->createMock(InvoiceSender::class);
        $this->logger = $this->createMock(Logger::class);

        $this->observer = new AutoCaptureNewOrder(
            $this->config,
            $this->invoiceService,
            $this->transactionFactory,
            $this->invoiceSender,
            $this->logger
        );
    }

    public function testExecuteDoesNothingWhenPaymentMethodIsNotSveaCheckout(): void
    {
        $payment = $this->makePayment('paypal_express');
        $order = $this->makeOrder($payment);
        $event = $this->makeObserver($order);

        $this->invoiceService->expects($this->never())->method('prepareInvoice');

        $this->observer->execute($event);
    }

    public function testExecuteDoesNothingWhenCanCaptureIsDisabled(): void
    {
        $payment = $this->makePayment('sveacheckout');
        $order = $this->makeOrder($payment, storeId: 1);
        $event = $this->makeObserver($order);

        $this->config->expects($this->once())->method('canCapture')->with(1)->willReturn(false);
        $this->invoiceService->expects($this->never())->method('prepareInvoice');

        $this->observer->execute($event);
    }

    public function testExecuteDoesNothingWhenAutoCaptureIsDisabled(): void
    {
        $payment = $this->makePayment('sveacheckout');
        $order = $this->makeOrder($payment, storeId: 1);
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->expects($this->once())->method('autoCapture')->with(1)->willReturn(false);
        $this->invoiceService->expects($this->never())->method('prepareInvoice');

        $this->observer->execute($event);
    }

    #[DataProvider('unsupportedPaymentTypeProvider')]
    public function testExecuteDoesNothingForUnsupportedPaymentType(string $paymentType): void
    {
        $payment = $this->makePayment('sveacheckout', paymentMethodType: $paymentType);
        $order = $this->makeOrder($payment, storeId: 1);
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->method('autoCapture')->willReturn(true);
        $this->invoiceService->expects($this->never())->method('prepareInvoice');

        $this->observer->execute($event);
    }

    public static function unsupportedPaymentTypeProvider(): array
    {
        return [
            'empty'        => [''],
            'invoice'      => ['Invoice'],
            'partpayment'  => ['PartPayment'],
        ];
    }

    #[DataProvider('supportedPaymentTypeProvider')]
    public function testExecuteCreatesAndCapturesInvoiceForSupportedPaymentTypes(string $paymentType): void
    {
        $payment = $this->makePayment('sveacheckout', paymentMethodType: $paymentType);
        $order = $this->makeOrder($payment, storeId: 1);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->makeChainableTransaction();
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->method('autoCapture')->willReturn(true);
        $this->config->method('isSendOrderEmail')->willReturn(false);

        $this->invoiceService->expects($this->once())
            ->method('prepareInvoice')->with($order)->willReturn($invoice);
        $invoice->expects($this->once())->method('register');
        $invoice->expects($this->once())->method('capture');
        $invoice->method('getOrder')->willReturn($order);

        $this->transactionFactory->method('create')->willReturn($transaction);

        $this->observer->execute($event);
    }

    public static function supportedPaymentTypeProvider(): array
    {
        return [
            'card'      => [AutoCaptureNewOrder::PAYMENT_TYPE_CARD],
            'swish'     => [AutoCaptureNewOrder::PAYMENT_TYPE_SWISH],
            'vipps'     => [AutoCaptureNewOrder::PAYMENT_TYPE_VIPPS],
            'mobilepay' => [AutoCaptureNewOrder::PAYMENT_TYPE_MOBILEPAY],
        ];
    }

    public function testExecuteSendsInvoiceEmailWhenConfigured(): void
    {
        $payment = $this->makePayment('sveacheckout', paymentMethodType: AutoCaptureNewOrder::PAYMENT_TYPE_CARD);
        $order = $this->makeOrder($payment, storeId: 2);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->makeChainableTransaction();
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->method('autoCapture')->willReturn(true);
        $this->config->method('isSendOrderEmail')->with(2)->willReturn(true);

        $this->invoiceService->method('prepareInvoice')->willReturn($invoice);
        $invoice->method('getOrder')->willReturn($order);
        $this->transactionFactory->method('create')->willReturn($transaction);

        $this->invoiceSender->expects($this->once())->method('send')->with($invoice);

        $this->observer->execute($event);
    }

    public function testExecuteDoesNotSendEmailWhenConfiguredOff(): void
    {
        $payment = $this->makePayment('sveacheckout', paymentMethodType: AutoCaptureNewOrder::PAYMENT_TYPE_CARD);
        $order = $this->makeOrder($payment, storeId: 1);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->makeChainableTransaction();
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->method('autoCapture')->willReturn(true);
        $this->config->method('isSendOrderEmail')->willReturn(false);

        $this->invoiceService->method('prepareInvoice')->willReturn($invoice);
        $invoice->method('getOrder')->willReturn($order);
        $this->transactionFactory->method('create')->willReturn($transaction);

        $this->invoiceSender->expects($this->never())->method('send');

        $this->observer->execute($event);
    }

    public function testExecuteLogsErrorAndContinuesWhenExceptionThrown(): void
    {
        $payment = $this->makePayment('sveacheckout', paymentMethodType: AutoCaptureNewOrder::PAYMENT_TYPE_CARD);
        $order = $this->makeOrder($payment, storeId: 1, incrementId: '100000001');
        $event = $this->makeObserver($order);

        $this->config->method('canCapture')->willReturn(true);
        $this->config->method('autoCapture')->willReturn(true);

        $this->invoiceService->method('prepareInvoice')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->atLeastOnce())->method('error');

        // Should not throw — exceptions are swallowed and logged
        $this->observer->execute($event);
    }

    // --- Helpers ---

    private function makePayment(string $method, string $paymentMethodType = ''): Payment&MockObject
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $payment->method('getAdditionalInformation')
            ->with('svea_payment_method_type')
            ->willReturn($paymentMethodType);

        return $payment;
    }

    private function makeOrder(
        Payment&MockObject $payment,
        int $storeId = 1,
        string $incrementId = '100000001'
    ): Order&MockObject {
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getIncrementId')->willReturn($incrementId);

        return $order;
    }

    private function makeObserver(Order&MockObject $order): Observer&MockObject
    {
        $observer = $this->createMock(Observer::class);
        $observer->method('getData')->with('order')->willReturn($order);

        return $observer;
    }

    private function makeChainableTransaction(): Transaction&MockObject
    {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('addObject')->willReturnSelf();

        return $transaction;
    }
}

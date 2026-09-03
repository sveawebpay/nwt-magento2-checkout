<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\OrderRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Model\Data\PaymentRecurringInfo;
use Svea\Checkout\Model\Data\PaymentRecurringInfoFactory;
use Svea\Checkout\Model\RecurringInfo;
use Svea\Checkout\Model\RecurringInfoFactory;
use Svea\Checkout\Service\SveaRecurringInfo;

class SveaRecurringInfoTest extends TestCase
{
    private PaymentRecurringInfoFactory&MockObject $paymentRecurringInfoFactory;
    private TokenClient&MockObject $tokenClient;
    private OrderRepository&MockObject $orderRepo;
    private RecurringInfoRepositoryInterface&MockObject $recurringInfoRepo;
    private RecurringInfoFactory&MockObject $recurringInfoModelFactory;
    private SveaRecurringInfo $service;

    protected function setUp(): void
    {
        $this->paymentRecurringInfoFactory = $this->createMock(PaymentRecurringInfoFactory::class);
        $this->tokenClient = $this->createMock(TokenClient::class);
        $this->orderRepo = $this->createMock(OrderRepository::class);
        $this->recurringInfoRepo = $this->createMock(RecurringInfoRepositoryInterface::class);
        $this->recurringInfoModelFactory = $this->createMock(RecurringInfoFactory::class);

        $this->service = new SveaRecurringInfo(
            $this->paymentRecurringInfoFactory,
            $this->tokenClient,
            $this->orderRepo,
            $this->recurringInfoRepo,
            $this->recurringInfoModelFactory
        );
    }

    // --- quoteGetter ---

    public function testQuoteGetterReturnsEmptyInfoWhenNoAdditionalData(): void
    {
        $emptyInfo = new PaymentRecurringInfo();
        $this->paymentRecurringInfoFactory->method('create')->willReturn($emptyInfo);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getAdditionalInformation')
            ->with(SveaRecurringInfo::RECURRING_PAYMENT_INFO_KEY)
            ->willReturn(null);

        $quote = $this->makeQuote($payment);

        $result = $this->service->quoteGetter($quote);

        $this->assertInstanceOf(PaymentRecurringInfo::class, $result);
        $this->assertNull($result->getFrequencyOption());
    }

    public function testQuoteGetterPopulatesInfoFromPaymentAdditionalData(): void
    {
        $info = new PaymentRecurringInfo();
        $this->paymentRecurringInfoFactory->method('create')->willReturn($info);

        $data = ['frequency_option' => '1|month', 'recurring_token' => 'tok_abc'];
        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getAdditionalInformation')
            ->with(SveaRecurringInfo::RECURRING_PAYMENT_INFO_KEY)
            ->willReturn($data);

        $quote = $this->makeQuote($payment);

        $result = $this->service->quoteGetter($quote);

        $this->assertSame('1|month', $result->getFrequencyOption());
        $this->assertSame('tok_abc', $result->getRecurringToken());
    }

    // --- quoteSetter ---

    public function testQuoteSetterStoresInfoInPaymentAdditionalData(): void
    {
        $info = new PaymentRecurringInfo();
        $info->setFrequencyOption('2|week');
        $info->setRecurringToken('tok_xyz');

        $payment = $this->createMock(QuotePayment::class);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(
                SveaRecurringInfo::RECURRING_PAYMENT_INFO_KEY,
                $this->arrayHasKey('frequency_option')
            );

        $quote = $this->makeQuote($payment);

        $this->service->quoteSetter($quote, $info);
    }

    // --- orderGetter ---

    public function testOrderGetterReturnsEmptyInfoWhenAdditionalDataMissingKey(): void
    {
        $emptyInfo = new PaymentRecurringInfo();
        $this->paymentRecurringInfoFactory->method('create')->willReturn($emptyInfo);

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getAdditionalInformation')->willReturn([]); // no recurring key

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $result = $this->service->orderGetter($order);

        $this->assertInstanceOf(PaymentRecurringInfo::class, $result);
        $this->assertNull($result->getFrequencyOption());
    }

    public function testOrderGetterPopulatesInfoFromOrderPayment(): void
    {
        $info = new PaymentRecurringInfo();
        $this->paymentRecurringInfoFactory->method('create')->willReturn($info);

        $data = [
            SveaRecurringInfo::RECURRING_PAYMENT_INFO_KEY => [
                'frequency_option' => '3|day',
                'recurring_token' => 'tok_order',
            ]
        ];

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getAdditionalInformation')->willReturn($data);

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $result = $this->service->orderGetter($order);

        $this->assertSame('3|day', $result->getFrequencyOption());
        $this->assertSame('tok_order', $result->getRecurringToken());
    }

    // --- scheduleNextRecurringOrder ---

    public function testScheduleNextRecurringOrderComputesMonthlyNextDate(): void
    {
        $info = new PaymentRecurringInfo();
        $info->setFrequencyOption('1|month');
        $this->paymentRecurringInfoFactory->method('create')->willReturn($info);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getAdditionalInformation')->willReturn(['frequency_option' => '1|month']);
        $payment->method('setAdditionalInformation');

        $quote = $this->makeQuote($payment);

        $this->service->scheduleNextRecurringOrder($quote);

        $expected = date('Y-m-d', strtotime('+1 month'));
        $this->assertSame($expected, $info->getNextOrderDate());
    }

    public function testScheduleNextRecurringOrderComputesPluralUnit(): void
    {
        $info = new PaymentRecurringInfo();
        $info->setFrequencyOption('2|week');
        $this->paymentRecurringInfoFactory->method('create')->willReturn($info);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getAdditionalInformation')->willReturn(['frequency_option' => '2|week']);
        $payment->method('setAdditionalInformation');

        $quote = $this->makeQuote($payment);

        $this->service->scheduleNextRecurringOrder($quote);

        $expected = date('Y-m-d', strtotime('+2 weeks'));
        $this->assertSame($expected, $info->getNextOrderDate());
    }

    public function testScheduleNextRecurringOrderSetsProvidedToken(): void
    {
        $info = new PaymentRecurringInfo();
        $info->setFrequencyOption('1|month');
        $this->paymentRecurringInfoFactory->method('create')->willReturn($info);

        $payment = $this->createMock(QuotePayment::class);
        $payment->method('getAdditionalInformation')->willReturn(['frequency_option' => '1|month']);
        $payment->method('setAdditionalInformation');

        $quote = $this->makeQuote($payment);

        $this->service->scheduleNextRecurringOrder($quote, 'new_token_123');

        $this->assertSame('new_token_123', $info->getRecurringToken());
    }

    // --- cancel ---

    public function testCancelCallsTokenClientAndSetsDate(): void
    {
        // RecurringInfo uses DataObject magic __call — use onlyMethods([]) so real
        // getData/setData work without a constructor, then assert on data state.
        $recurringInfo = $this->getMockBuilder(RecurringInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $recurringInfo->setData('recurring_token', 'tok_cancel');

        $this->tokenClient->expects($this->once())->method('cancelToken')->with('tok_cancel');

        $this->service->cancel($recurringInfo);

        $this->assertSame(date('Y-m-d'), $recurringInfo->getData('canceled_date'));
        $this->assertNull($recurringInfo->getData('next_order_date'));
    }

    // --- stored snippet ---

    public function testStoredChangePaymentSnippetRoundtrip(): void
    {
        $this->assertNull($this->service->getStoredChangePaymentSnippet());

        $this->service->setStoredChangePaymentSnippet('<html>snippet</html>');

        $this->assertSame('<html>snippet</html>', $this->service->getStoredChangePaymentSnippet());
    }

    // --- loadOrder / saveOrder ---

    public function testLoadOrderDelegatesToRepository(): void
    {
        $order = $this->createMock(Order::class);
        $this->orderRepo->expects($this->once())->method('get')->with(42)->willReturn($order);

        $result = $this->service->loadOrder(42);

        $this->assertSame($order, $result);
    }

    public function testSaveOrderDelegatesToRepository(): void
    {
        $order = $this->createMock(Order::class);
        $this->orderRepo->expects($this->once())->method('save')->with($order);

        $this->service->saveOrder($order);
    }

    // --- helpers ---

    private function makeQuote(QuotePayment&MockObject $payment): Quote&MockObject
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getPayment')->willReturn($payment);

        return $quote;
    }
}

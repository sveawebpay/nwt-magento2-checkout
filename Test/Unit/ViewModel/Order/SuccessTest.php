<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\ViewModel\Order;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order as SalesOrder;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\OrderRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Helper\GiftCard as GiftCardHelper;
use Svea\Checkout\Model\CheckoutContext as SveaCheckoutContext;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Svea\Order as SveaOrderHandler;
use Svea\Checkout\ViewModel\Checkout\NonceProvider;
use Svea\Checkout\ViewModel\Order\Success;

class SuccessTest extends TestCase
{
    /** @var CheckoutSession&MockObject */
    private $checkoutSession;

    /** @var HttpContext&MockObject */
    private $httpContext;

    /** @var OrderRepository&MockObject */
    private $orderRepository;

    /** @var GiftCardHelper&MockObject */
    private $giftCardHelper;

    /** @var NonceProvider&MockObject */
    private $nonceProvider;

    /** @var SveaCheckoutContext&MockObject */
    private $sveaCheckoutContext;

    /** @var SveaOrderHandler&MockObject */
    private $sveaOrderHandler;

    /** @var UrlInterface&MockObject */
    private $urlBuilder;

    /** @var LoggerInterface&MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->addMethods(['getLastOrderId', 'getLastRealOrderId', 'getSveaOrderId', 'unsSveaOrderId'])
            ->getMock();
        $this->httpContext = $this->createMock(HttpContext::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->giftCardHelper = $this->createMock(GiftCardHelper::class);
        $this->nonceProvider = $this->createMock(NonceProvider::class);
        $this->sveaOrderHandler = $this->createMock(SveaOrderHandler::class);
        $this->sveaCheckoutContext = $this->createMock(SveaCheckoutContext::class);
        $this->sveaCheckoutContext->method('getSveaOrderHandler')->willReturn($this->sveaOrderHandler);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function makeViewModel(): Success
    {
        return new Success(
            $this->checkoutSession,
            $this->httpContext,
            $this->orderRepository,
            $this->giftCardHelper,
            $this->nonceProvider,
            $this->sveaCheckoutContext,
            $this->urlBuilder,
            $this->logger
        );
    }

    public function testGetRealOrderIdReturnsNullWhenSessionEmpty(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(null);

        $this->assertNull($this->makeViewModel()->getRealOrderId());
    }

    public function testGetRealOrderIdCastsToInt(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn('42');

        $this->assertSame(42, $this->makeViewModel()->getRealOrderId());
    }

    public function testGetIncrementIdReturnsString(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn(100000123);

        $this->assertSame('100000123', $this->makeViewModel()->getIncrementId());
    }

    public function testGetIncrementIdReturnsNullWhenAbsent(): void
    {
        $this->checkoutSession->method('getLastRealOrderId')->willReturn(null);

        $this->assertNull($this->makeViewModel()->getIncrementId());
    }

    public function testGetOrderReturnsNullWhenNoOrderId(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(null);
        $this->orderRepository->expects($this->never())->method('get');

        $this->assertNull($this->makeViewModel()->getOrder());
    }

    public function testGetOrderLoadsAndCachesOrder(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(42);
        $orderMock = $this->createMock(SalesOrder::class);
        $this->orderRepository->expects($this->once())
            ->method('get')
            ->with(42)
            ->willReturn($orderMock);

        $vm = $this->makeViewModel();
        $this->assertSame($orderMock, $vm->getOrder());
        $this->assertSame($orderMock, $vm->getOrder(), 'Second call should use cached value');
    }

    public function testGetOrderLogsAndReturnsNullOnRepositoryFailure(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(42);
        $this->orderRepository->method('get')->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('could not load order 42'));

        $this->assertNull($this->makeViewModel()->getOrder());
    }

    public function testGetOrderItemsReturnsEmptyWhenNoOrder(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(null);

        $this->assertSame([], $this->makeViewModel()->getOrderItems());
    }

    public function testGetOrderItemsReturnsAllVisibleItems(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(7);
        $item = $this->createMock(OrderItem::class);
        $orderMock = $this->createMock(SalesOrder::class);
        $orderMock->method('getAllVisibleItems')->willReturn([$item]);
        $this->orderRepository->method('get')->willReturn($orderMock);

        $this->assertSame([$item], $this->makeViewModel()->getOrderItems());
    }

    public function testGetIframeSnippetReturnsEmptyWhenSveaOrderIdMissing(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn(null);
        $this->checkoutSession->expects($this->never())->method('unsSveaOrderId');
        $this->sveaOrderHandler->expects($this->never())->method('loadSveaOrderById');

        $this->assertSame('', $this->makeViewModel()->getIframeSnippet());
    }

    public function testGetIframeSnippetReturnsSnippetAndClearsSession(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn('svea-123');
        $this->checkoutSession->expects($this->once())->method('unsSveaOrderId');

        $payment = $this->createMock(GetOrderResponse::class);
        $payment->method('getStatus')->willReturn('Final');
        $this->sveaOrderHandler->expects($this->once())
            ->method('loadSveaOrderById')
            ->with('svea-123', true)
            ->willReturn($payment);
        $this->sveaOrderHandler->method('getIframeSnippet')->willReturn('<iframe/>');

        $this->assertSame('<iframe/>', $this->makeViewModel()->getIframeSnippet());
    }

    public function testGetIframeSnippetReturnsEmptyForCreatedStatus(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn('svea-123');
        $payment = $this->createMock(GetOrderResponse::class);
        $payment->method('getStatus')->willReturn('Created');
        $this->sveaOrderHandler->method('loadSveaOrderById')->willReturn($payment);
        $this->sveaOrderHandler->expects($this->never())->method('getIframeSnippet');

        $this->assertSame('', $this->makeViewModel()->getIframeSnippet());
    }

    public function testGetIframeSnippetReadsSessionOnceAcrossCalls(): void
    {
        $this->checkoutSession->expects($this->once())
            ->method('getSveaOrderId')
            ->willReturn('svea-123');
        $payment = $this->createMock(GetOrderResponse::class);
        $payment->method('getStatus')->willReturn('Final');
        $this->sveaOrderHandler->expects($this->once())
            ->method('loadSveaOrderById')
            ->willReturn($payment);
        $this->sveaOrderHandler->method('getIframeSnippet')->willReturn('<iframe/>');

        $vm = $this->makeViewModel();
        $vm->getIframeSnippet();
        $vm->getIframeSnippet();
    }

    public function testGetIframeSnippetLogsAndReturnsEmptyOnHandlerFailure(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn('svea-123');
        $this->sveaOrderHandler->method('loadSveaOrderById')
            ->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('could not load Svea order svea-123'));

        $this->assertSame('', $this->makeViewModel()->getIframeSnippet());
    }

    public function testGetGiftCardsReturnsEmptyWhenNoOrder(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(null);

        $this->assertSame([], $this->makeViewModel()->getGiftCards());
    }

    public function testGetGiftCardsReturnsEmptyWhenOrderHasNone(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(7);
        $orderMock = $this->createMock(SalesOrder::class);
        $orderMock->method('getData')->with('gift_cards')->willReturn(null);
        $this->orderRepository->method('get')->willReturn($orderMock);
        $this->giftCardHelper->expects($this->never())->method('getGiftCards');

        $this->assertSame([], $this->makeViewModel()->getGiftCards());
    }

    public function testGetGiftCardsDelegatesToHelper(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(7);
        $orderMock = $this->createMock(SalesOrder::class);
        $orderMock->method('getData')->with('gift_cards')->willReturn('serialized-gift-cards');
        $this->orderRepository->method('get')->willReturn($orderMock);
        $this->giftCardHelper->expects($this->once())
            ->method('getGiftCards')
            ->with('serialized-gift-cards')
            ->willReturn(['card-1']);

        $this->assertSame(['card-1'], $this->makeViewModel()->getGiftCards());
    }

    public function testGetNonceDelegatesToProvider(): void
    {
        $this->nonceProvider->method('generateNonce')->willReturn('abc');

        $this->assertSame('abc', $this->makeViewModel()->getNonce());
    }

    /**
     * @dataProvider formatQtyProvider
     */
    public function testFormatQty(mixed $input, string $expected): void
    {
        $this->assertSame($expected, $this->makeViewModel()->formatQty($input));
    }

    public function formatQtyProvider(): array
    {
        return [
            'integer'                       => [1, '1'],
            'integer-float'                 => [1.0, '1'],
            'string-integer'                => ['3', '3'],
            'half'                          => [2.5, '2.5'],
            'two-decimals'                  => [1.25, '1.25'],
            'trailing-zeros-stripped'       => [1.5000, '1.5'],
            'zero'                          => [0, '0'],
            'four-decimal-cap'              => [1.123456, '1.1235'],
        ];
    }

    public function testGetCanViewOrderReadsHttpContext(): void
    {
        $this->httpContext->method('getValue')
            ->with(CustomerContext::CONTEXT_AUTH)
            ->willReturn(true);

        $this->assertTrue($this->makeViewModel()->getCanViewOrder());
    }

    public function testGetViewOrderUrlReturnsEmptyWithoutOrder(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(null);
        $this->urlBuilder->expects($this->never())->method('getUrl');

        $this->assertSame('', $this->makeViewModel()->getViewOrderUrl());
    }

    public function testGetViewOrderUrlBuildsViewUrl(): void
    {
        $this->checkoutSession->method('getLastOrderId')->willReturn(42);
        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('sales/order/view', ['order_id' => 42])
            ->willReturn('http://example.com/sales/order/view/order_id/42');

        $this->assertSame(
            'http://example.com/sales/order/view/order_id/42',
            $this->makeViewModel()->getViewOrderUrl()
        );
    }

    public function testGetContinueShoppingUrlReturnsBaseUrl(): void
    {
        $this->urlBuilder->method('getUrl')->with()->willReturn('http://example.com/');

        $this->assertSame('http://example.com/', $this->makeViewModel()->getContinueShoppingUrl());
    }
}

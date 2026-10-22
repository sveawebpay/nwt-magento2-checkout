<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Controller\Order\Confirmation;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;
use Svea\Checkout\Model\CheckoutOrderNumberReference;
use Svea\Checkout\Model\Push;
use Svea\Checkout\Model\PushRepository;
use Svea\Checkout\Model\PushRepositoryFactory;

/**
 * Covers the quote_id fallback, used when the Svea order id is missing from the session.
 * Only the customer Svea redirected here knows the quote hash, so every request without
 * the right, unguessable hash must be refused before any order reaches the session.
 */
class ConfirmationTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const RESERVED_ORDER_ID = '100000123';
    private const SVEA_ORDER_ID = 9001;
    private const RANDOM_HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';

    private Confirmation&MockObject $controller;
    private RequestInterface&Stub $request;
    private PushRepository&MockObject $pushRepo;
    private Session&MockObject $checkoutSession;
    private ManagerInterface&MockObject $messageManager;
    private Quote&MockObject $quote;
    private ResponseInterface&Stub $response;

    protected function setUp(): void
    {
        $this->request = $this->createStub(RequestInterface::class);
        $this->response = $this->createStub(ResponseInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        // No Svea order id in the session, so execute() always takes the quote_id fallback.
        $refHelper = $this->createStub(CheckoutOrderNumberReference::class);
        $refHelper->method('getSveaOrderId')->willReturn(null);

        $sveaCheckout = $this->createStub(Checkout::class);
        $sveaCheckout->method('getRefHelper')->willReturn($refHelper);
        $sveaCheckout->method('getLogger')->willReturn($this->createStub(LoggerInterface::class));

        $this->pushRepo = $this->createMock(PushRepository::class);
        $pushRepositoryFactory = $this->createStub(PushRepositoryFactory::class);
        $pushRepositoryFactory->method('create')->willReturn($this->pushRepo);

        $orderCollection = $this->createStub(OrderCollection::class);
        $orderCollection->method('addFieldToFilter')->willReturnSelf();
        $orderCollection->method('load')->willReturnSelf();
        $orderCollection->method('count')->willReturn(0);
        $orderCollectionFactory = $this->createStub(OrderCollectionFactory::class);
        $orderCollectionFactory->method('create')->willReturn($orderCollection);

        $sveaCheckoutContext = $this->createStub(CheckoutContext::class);
        $sveaCheckoutContext->method('getOrderCollectionFactory')->willReturn($orderCollectionFactory);
        $sveaCheckoutContext->method('getHelper')->willReturn($this->createStub(Data::class));

        // Magic setters (setLastOrderId etc.) go through __call and are chained.
        $this->checkoutSession = $this->createMock(Session::class);
        $this->checkoutSession->method('__call')->willReturnSelf();
        $this->checkoutSession->method('clearQuote')->willReturnSelf();
        $this->checkoutSession->method('clearStorage')->willReturnSelf();

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReservedOrderId'])
            ->getMock();
        $this->quote->method('getReservedOrderId')->willReturn(self::RESERVED_ORDER_ID);
        $this->quote->setData('svea_order_id', self::SVEA_ORDER_ID);
        $this->quote->setData('svea_client_order_id', self::RESERVED_ORDER_ID);

        $this->controller = $this->getMockBuilder(Confirmation::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRequest', 'loadQuote', 'loadOrder', '_redirect'])
            ->getMock();
        $this->controller->method('getRequest')->willReturn($this->request);
        $this->controller->method('loadQuote')->with(self::QUOTE_ID)->willReturn($this->quote);

        $this->setProperty('sveaCheckout', $sveaCheckout);
        $this->setProperty('sveaCheckoutContext', $sveaCheckoutContext);
        $this->setProperty('pushRepositoryFactory', $pushRepositoryFactory);
        $this->setProperty('checkoutSession', $this->checkoutSession);
        $this->setProperty('messageManager', $this->messageManager);
    }

    public function testRejectsRequestWithoutHash(): void
    {
        $this->quote->setData('svea_hash', self::RANDOM_HASH);

        $this->assertRejected(null);
    }

    public function testRejectsRequestWithEmptyHash(): void
    {
        $this->quote->setData('svea_hash', self::RANDOM_HASH);

        $this->assertRejected('');
    }

    public function testRejectsRequestWithWrongHash(): void
    {
        $this->quote->setData('svea_hash', self::RANDOM_HASH);

        $this->assertRejected(str_repeat('0', 40));
    }

    public function testRejectsArrayHashWithoutError(): void
    {
        $this->quote->setData('svea_hash', self::RANDOM_HASH);

        $this->assertRejected([self::RANDOM_HASH]);
    }

    public function testRejectsEmptyHashOnQuoteWithoutHash(): void
    {
        $this->quote->setData('svea_hash', '');

        $this->assertRejected('');
    }

    public function testRejectsLegacySha1HashEvenWhenItMatches(): void
    {
        $legacyHash = sha1(self::RESERVED_ORDER_ID);
        $this->quote->setData('svea_hash', $legacyHash);

        $this->assertRejected($legacyHash);
    }

    public function testRejectsLegacySha1HashOfSequencedClientOrderNumber(): void
    {
        // The hash was generated before the client order number got a new sequence.
        $legacyHash = sha1(self::RESERVED_ORDER_ID . '-3');
        $this->quote->setData('svea_hash', $legacyHash);
        $this->quote->setData('svea_client_order_id', self::RESERVED_ORDER_ID . '-4');

        $this->assertRejected($legacyHash);
    }

    public function testValidHashReachesSuccessPage(): void
    {
        $this->quote->setData('svea_hash', self::RANDOM_HASH);
        $this->stubRequest(self::RANDOM_HASH);

        $push = $this->createStub(Push::class);
        $push->method('getOrderId')->willReturn(55);
        $this->pushRepo->expects($this->once())
            ->method('get')
            ->with(self::SVEA_ORDER_ID)
            ->willReturn($push);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(55);
        $this->controller->method('loadOrder')->with(55)->willReturn($order);

        $this->messageManager->expects($this->never())->method('addErrorMessage');
        $this->controller->expects($this->once())
            ->method('_redirect')
            ->with('checkout/onepage/success')
            ->willReturn($this->response);

        $this->assertSame($this->response, $this->controller->execute());
    }

    /**
     * @param mixed $hash
     */
    private function assertRejected($hash): void
    {
        $this->stubRequest($hash);

        // Nothing about the order may be looked up or put in the requester's session.
        $this->pushRepo->expects($this->never())->method('get');
        $this->controller->expects($this->never())->method('loadOrder');
        $this->checkoutSession->expects($this->never())->method('__call');

        $this->messageManager->expects($this->once())->method('addErrorMessage');
        $this->controller->expects($this->once())
            ->method('_redirect')
            ->with('*')
            ->willReturn($this->response);

        $this->assertSame($this->response, $this->controller->execute());
    }

    /**
     * @param mixed $hash
     */
    private function stubRequest($hash): void
    {
        $this->request->method('getParam')->willReturnMap([
            ['hash', null, $hash],
            ['quote_id', null, self::QUOTE_ID],
        ]);
    }

    private function setProperty(string $name, $value): void
    {
        $class = new \ReflectionClass($this->controller);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $property = $class->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($this->controller, $value);
    }
}

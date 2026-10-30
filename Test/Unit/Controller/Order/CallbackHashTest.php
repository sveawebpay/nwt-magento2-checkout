<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Api\Data\PushInterface;
use Svea\Checkout\Controller\Order\Push;
use Svea\Checkout\Controller\Order\ValidateOrder;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\MerchantDataResponse;
use Svea\Checkout\Model\PushRepository;
use Svea\Checkout\Model\PushRepositoryFactory;

/**
 * SC-303: Push and ValidateOrder compare the callback hash in constant time and refuse
 * a missing, empty or non-string hash, also when the quote has no hash.
 */
class CallbackHashTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const SVEA_ORDER_ID = 9001;
    private const QUOTE_HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';

    public static function refusedHashProvider(): array
    {
        return [
            'missing hash' => [self::QUOTE_HASH, null],
            'empty hash' => [self::QUOTE_HASH, ''],
            'wrong hash' => [self::QUOTE_HASH, str_repeat('0', 40)],
            'array hash' => [self::QUOTE_HASH, [self::QUOTE_HASH]],
            'missing hash on quote without hash' => [null, null],
            'empty hash on quote with empty hash' => ['', ''],
        ];
    }

    /**
     * @param mixed $requestHash
     */
    #[DataProvider('refusedHashProvider')]
    public function testPushRefusesHash(?string $quoteHash, $requestHash): void
    {
        $push = $this->makePush($quoteHash);
        $push->expects($this->never())->method('placeOrder');

        $this->expectExceptionMessage('invalid svea hash');
        $this->createMagentoOrder($push, $requestHash);
    }

    public function testPushPlacesOrderForMatchingHash(): void
    {
        $order = $this->createStub(Order::class);
        $push = $this->makePush(self::QUOTE_HASH);
        $push->expects($this->once())->method('placeOrder')->willReturn($order);

        $this->assertSame($order, $this->createMagentoOrder($push, self::QUOTE_HASH));
    }

    /**
     * @param mixed $requestHash
     */
    #[DataProvider('refusedHashProvider')]
    public function testValidateOrderRefusesHash(?string $quoteHash, $requestHash): void
    {
        $result = $this->createMock(Json::class);
        $result->method('setData')->willReturnSelf();
        $result->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $controller = $this->makeValidateOrder($quoteHash, $requestHash, $result);
        $controller->expects($this->never())->method('validateOrder');

        $controller->execute();
    }

    public function testValidateOrderValidatesMatchingHash(): void
    {
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnSelf();
        $result->method('setHttpResponseCode')->willReturnSelf();
        $controller = $this->makeValidateOrder(self::QUOTE_HASH, self::QUOTE_HASH, $result);
        $controller->expects($this->once())->method('validateOrder');

        $controller->execute();
    }

    private function makePush(?string $quoteHash): Push
    {
        $push = $this->getMockBuilder(Push::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadQuote', 'placeOrder'])
            ->getMock();
        $push->method('loadQuote')->with(self::QUOTE_ID)->willReturn($this->makeQuote($quoteHash));

        return $push;
    }

    /**
     * @param mixed $requestHash
     */
    private function createMagentoOrder(Push $push, $requestHash)
    {
        $method = new \ReflectionMethod(Push::class, 'createMagentoOrder');

        return $method->invoke($push, $this->makeSveaOrder(), $requestHash);
    }

    /**
     * @param mixed $requestHash
     */
    private function makeValidateOrder(?string $quoteHash, $requestHash, Json $result): ValidateOrder
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['sid', null, (string)self::SVEA_ORDER_ID],
            ['hash', null, $requestHash],
        ]);

        $sveaCheckout = $this->createStub(Checkout::class);
        $sveaCheckout->method('getLogger')->willReturn($this->createStub(LoggerInterface::class));

        $pushRepo = $this->createStub(PushRepository::class);
        $pushRepo->method('get')->willThrowException(new NoSuchEntityException());
        $pushRepositoryFactory = $this->createStub(PushRepositoryFactory::class);
        $pushRepositoryFactory->method('create')->willReturn($pushRepo);

        $jsonResultFactory = $this->createStub(JsonFactory::class);
        $jsonResultFactory->method('create')->willReturn($result);

        $controller = $this->getMockBuilder(ValidateOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'assignServices', 'validateSveaOrderId', 'loadSveaOrder', 'loadQuote', 'getRequest',
                'validateOrder', 'createNewPushObject',
            ])
            ->getMock();
        $controller->method('getRequest')->willReturn($request);
        $controller->method('createNewPushObject')->willReturn($this->createStub(PushInterface::class));
        $controller->method('loadSveaOrder')->willReturn($this->makeSveaOrder());
        $controller->method('loadQuote')->with(self::QUOTE_ID)->willReturn($this->makeQuote($quoteHash));

        $this->setProperty($controller, 'sveaCheckout', $sveaCheckout);
        $this->setProperty($controller, 'sveaCheckoutContext', $this->createStub(CheckoutContext::class));
        $this->setProperty($controller, 'pushRepositoryFactory', $pushRepositoryFactory);
        $this->setProperty($controller, 'jsonResultFactory', $jsonResultFactory);

        return $controller;
    }

    private function makeSveaOrder(): GetOrderResponse
    {
        $merchantData = $this->createStub(MerchantDataResponse::class);
        $merchantData->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $sveaOrder = $this->createStub(GetOrderResponse::class);
        $sveaOrder->method('getMerchantData')->willReturn($merchantData);
        $sveaOrder->method('getOrderId')->willReturn(self::SVEA_ORDER_ID);

        return $sveaOrder;
    }

    private function makeQuote(?string $hash): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getPayment'])
            ->getMock();
        $quote->method('getId')->willReturn(self::QUOTE_ID);
        $quote->method('getPayment')->willReturn($this->createStub(Payment::class));
        $quote->setData('svea_hash', $hash);

        return $quote;
    }

    private function setProperty(object $object, string $name, $value): void
    {
        $class = new \ReflectionClass($object);
        while (!$class->hasProperty($name)) {
            $class = $class->getParentClass();
        }
        $class->getProperty($name)->setValue($object, $value);
    }
}

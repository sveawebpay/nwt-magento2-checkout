<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Controller\Order\ValidateOrder;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\MerchantDataResponse;
use Svea\Checkout\Model\Push;
use Svea\Checkout\Model\PushRepository;
use Svea\Checkout\Model\PushRepositoryFactory;

/**
 * SC-305: when a push already exists for the Svea order, ValidateOrder must check the hash
 * before it answers with the Magento order number.
 */
class ValidateOrderExistingPushTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const SVEA_ORDER_ID = 9001;
    private const MAGENTO_ORDER_ID = 55;
    private const INCREMENT_ID = '100000123';
    private const QUOTE_HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';

    public function testWrongHashDoesNotRevealOrderNumber(): void
    {
        $data = [];
        $result = $this->makeResult($data);
        $result->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();
        $controller = $this->makeController(str_repeat('0', 40), $result);
        $controller->expects($this->never())->method('loadOrder');

        $controller->execute();

        $this->assertSame(false, $data['Valid']);
        $this->assertArrayNotHasKey('ClientOrderNumber', $data);
    }

    public function testMatchingHashAnswersWithExistingOrderNumber(): void
    {
        $data = [];
        $result = $this->makeResult($data);
        $controller = $this->makeController(self::QUOTE_HASH, $result);
        $controller->expects($this->never())->method('validateOrder');

        $controller->execute();

        $this->assertSame(['Valid' => true, 'ClientOrderNumber' => self::INCREMENT_ID], $data);
    }

    private function makeResult(array &$data): Json&MockObject
    {
        $result = $this->createMock(Json::class);
        $result->method('setData')->willReturnCallback(function ($value) use (&$data, &$result) {
            $data = $value;
            return $result;
        });

        return $result;
    }

    private function makeController(string $requestHash, Json $result): ValidateOrder&MockObject
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['sid', null, (string)self::SVEA_ORDER_ID],
            ['hash', null, $requestHash],
        ]);

        $merchantData = $this->createStub(MerchantDataResponse::class);
        $merchantData->method('getQuoteId')->willReturn(self::QUOTE_ID);
        $sveaOrder = $this->createStub(GetOrderResponse::class);
        $sveaOrder->method('getMerchantData')->willReturn($merchantData);
        $sveaOrder->method('getOrderId')->willReturn(self::SVEA_ORDER_ID);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $quote->method('getId')->willReturn(self::QUOTE_ID);
        $quote->setData('svea_hash', self::QUOTE_HASH);

        // The order was already created and its push stored.
        $push = $this->createStub(Push::class);
        $push->method('getOrderId')->willReturn(self::MAGENTO_ORDER_ID);
        $pushRepo = $this->createStub(PushRepository::class);
        $pushRepo->method('get')->willReturn($push);
        $pushRepositoryFactory = $this->createStub(PushRepositoryFactory::class);
        $pushRepositoryFactory->method('create')->willReturn($pushRepo);

        $order = $this->createStub(Order::class);
        $order->method('getIncrementId')->willReturn(self::INCREMENT_ID);

        $sveaCheckout = $this->createStub(Checkout::class);
        $sveaCheckout->method('getLogger')->willReturn($this->createStub(LoggerInterface::class));

        $jsonResultFactory = $this->createStub(JsonFactory::class);
        $jsonResultFactory->method('create')->willReturn($result);

        $controller = $this->getMockBuilder(ValidateOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'assignServices', 'validateSveaOrderId', 'loadSveaOrder', 'loadQuote', 'getRequest',
                'loadOrder', 'validateOrder',
            ])
            ->getMock();
        $controller->method('getRequest')->willReturn($request);
        $controller->method('loadSveaOrder')->willReturn($sveaOrder);
        $controller->method('loadQuote')->with(self::QUOTE_ID)->willReturn($quote);
        $controller->method('loadOrder')->with(self::MAGENTO_ORDER_ID)->willReturn($order);

        $this->setProperty($controller, 'sveaCheckout', $sveaCheckout);
        $this->setProperty($controller, 'sveaCheckoutContext', $this->createStub(CheckoutContext::class));
        $this->setProperty($controller, 'pushRepositoryFactory', $pushRepositoryFactory);
        $this->setProperty($controller, 'jsonResultFactory', $jsonResultFactory);

        return $controller;
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

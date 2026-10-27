<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Controller\Order\ValidateOrder;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\MerchantDataResponse;
use Svea\Checkout\Model\PushRepository;
use Svea\Checkout\Model\PushRepositoryFactory;

/**
 * SC-302: a hash mismatch must not write the quote's secret hash to the log.
 */
class ValidateOrderTest extends TestCase
{
    private const QUOTE_ID = 42;
    private const SVEA_ORDER_ID = 9001;
    private const QUOTE_HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';
    private const REQUEST_HASH = '0000000000000000000000000000000000000000';

    public function testHashMismatchIsLoggedWithoutHashes(): void
    {
        $messages = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = (string)$message;
        });

        $result = $this->createMock(Json::class);
        $result->method('setData')->willReturnSelf();
        $result->expects($this->once())->method('setHttpResponseCode')->with(400)->willReturnSelf();

        $controller = $this->makeController($logger, $result);
        $controller->execute();

        $mismatch = array_values(array_filter($messages, fn ($m) => str_contains($m, 'does not match')));
        $this->assertCount(1, $mismatch);
        $this->assertStringContainsString((string)self::QUOTE_ID, $mismatch[0]);
        $this->assertStringContainsString((string)self::SVEA_ORDER_ID, $mismatch[0]);
        foreach ($messages as $message) {
            $this->assertStringNotContainsString(self::QUOTE_HASH, $message);
            $this->assertStringNotContainsString(self::REQUEST_HASH, $message);
        }
    }

    private function makeController(LoggerInterface $logger, Json $result): ValidateOrder&MockObject
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([
            ['sid', null, (string)self::SVEA_ORDER_ID],
            ['hash', null, self::REQUEST_HASH],
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

        $sveaCheckout = $this->createStub(Checkout::class);
        $sveaCheckout->method('getLogger')->willReturn($logger);

        $pushRepo = $this->createStub(PushRepository::class);
        $pushRepo->method('get')->willThrowException(new NoSuchEntityException());
        $pushRepositoryFactory = $this->createStub(PushRepositoryFactory::class);
        $pushRepositoryFactory->method('create')->willReturn($pushRepo);

        $jsonResultFactory = $this->createStub(JsonFactory::class);
        $jsonResultFactory->method('create')->willReturn($result);

        $controller = $this->getMockBuilder(ValidateOrder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['assignServices', 'validateSveaOrderId', 'loadSveaOrder', 'loadQuote', 'getRequest'])
            ->getMock();
        $controller->method('getRequest')->willReturn($request);
        $controller->method('loadSveaOrder')->willReturn($sveaOrder);
        $controller->method('loadQuote')->with(self::QUOTE_ID)->willReturn($quote);

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

<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Controller\Index\Index;
use Svea\Checkout\Controller\Order\SaveComment;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;

/**
 * SC-309: shoppers only see messages written for them. Other exception texts, e.g. built from
 * Svea API error bodies, are logged and replaced with a generic message.
 */
class ShopperErrorMessageTest extends TestCase
{
    private const INTERNAL_MESSAGE = 'Client error: `POST https://checkoutapi.svea.com/api/orders` resulted in 400 {"Code":"InvalidMerchant"}';

    /** @var string[] */
    private array $shownMessages = [];

    /** @var string[] */
    private array $loggedMessages = [];

    public function testCheckoutPageHidesInternalErrorMessage(): void
    {
        $this->runIndex(new \Exception(self::INTERNAL_MESSAGE));

        $this->assertSame(
            ['Svea Checkout could not be started. Please try again or contact customer service.'],
            $this->shownMessages
        );
        $this->assertStringContainsString(self::INTERNAL_MESSAGE, implode("\n", $this->loggedMessages));
    }

    public function testCheckoutPageShowsMessageWrittenForShoppers(): void
    {
        $this->runIndex(new LocalizedException(new Phrase('Some products are out of stock.')));

        $this->assertSame(['Some products are out of stock.'], $this->shownMessages);
    }

    public function testCheckoutUpdateHidesInternalErrorMessage(): void
    {
        $sveaCheckout = $this->makeSveaCheckout(new \Exception(self::INTERNAL_MESSAGE));
        $controller = $this->getMockBuilder(SaveComment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'getRequest', '_redirect', 'getSveaCheckout'])
            ->getMock();
        $request = $this->createStub(Http::class);
        $request->method('isXmlHttpRequest')->willReturn(false);
        $controller->method('getRequest')->willReturn($request);
        $controller->method('getSveaCheckout')->willReturn($sveaCheckout);
        $this->setProperty($controller, 'sveaCheckoutContext', $this->createStub(CheckoutContext::class));
        $this->setProperty($controller, 'messageManager', $this->makeMessageManager());

        $sendResponse = new \ReflectionMethod($controller, '_sendResponse');
        $sendResponse->invoke($controller, ['messages']);

        $this->assertSame(
            ['The checkout could not be updated. Please try again or contact customer service.'],
            $this->shownMessages
        );
        $this->assertStringContainsString(self::INTERNAL_MESSAGE, implode("\n", $this->loggedMessages));
    }

    private function runIndex(\Exception $exception): void
    {
        $controller = $this->getMockBuilder(Index::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSveaCheckout', '_redirect'])
            ->getMock();
        $controller->method('getSveaCheckout')->willReturn($this->makeSveaCheckout($exception));
        $controller->expects($this->once())->method('_redirect')->with('checkout/cart');
        $this->setProperty($controller, 'sveaCheckoutContext', $this->createStub(CheckoutContext::class));
        $this->setProperty($controller, 'messageManager', $this->makeMessageManager());

        $controller->execute();
    }

    private function makeSveaCheckout(\Exception $exception): Checkout&MockObject
    {
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($message) {
            $this->loggedMessages[] = (string)$message;
        });

        $sveaCheckout = $this->getMockBuilder(Checkout::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['initCheckout', 'getLogger', 'setCheckoutContext'])
            ->getMock();
        $sveaCheckout->method('initCheckout')->willThrowException($exception);
        $sveaCheckout->method('getLogger')->willReturn($logger);

        return $sveaCheckout;
    }

    private function makeMessageManager(): ManagerInterface
    {
        $messageManager = $this->createStub(ManagerInterface::class);
        $messageManager->method('addErrorMessage')->willReturnCallback(function ($message) use (&$messageManager) {
            $this->shownMessages[] = (string)$message;
            return $messageManager;
        });

        return $messageManager;
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

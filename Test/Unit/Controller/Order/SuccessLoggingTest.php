<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Svea\Checkout\Controller\Order\Success;
use Svea\Checkout\Model\Checkout;
use Svea\Checkout\Model\CheckoutContext;

/**
 * SC-306: an invalid success page visit is logged with identifiers only, not the session data.
 */
class SuccessLoggingTest extends TestCase
{
    public function testInvalidSessionIsLoggedWithoutSessionData(): void
    {
        $messages = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($message) use (&$messages) {
            $messages[] = (string)$message;
        });
        $sveaCheckout = $this->createStub(Checkout::class);
        $sveaCheckout->method('getLogger')->willReturn($logger);

        // Session without a last success quote id, so the visit is invalid.
        $session = $this->createMock(Session::class);
        $session->expects($this->never())->method('getData');
        $session->method('__call')->willReturnCallback(fn ($method) => match ($method) {
            'getLastOrderId' => 55,
            'getSveaOrderId' => 9001,
            default => null,
        });

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $controller = $this->getMockBuilder(Success::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCheckoutSession', 'getSveaCheckout'])
            ->getMock();
        $controller->method('getCheckoutSession')->willReturn($session);
        $controller->method('getSveaCheckout')->willReturn($sveaCheckout);
        $this->setProperty($controller, 'sveaCheckoutContext', $this->createStub(CheckoutContext::class));
        $this->setProperty($controller, 'resultRedirectFactory', $redirectFactory);

        $this->assertSame($redirect, $controller->execute());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Last order ID: 55', $messages[0]);
        $this->assertStringContainsString('Svea order ID: 9001', $messages[0]);
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

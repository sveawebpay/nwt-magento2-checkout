<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller;

use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RawFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Controller\Order\Cart;
use Svea\Checkout\Controller\Order\Confirmshipping;
use Svea\Checkout\Controller\Order\Push;
use Svea\Checkout\Controller\Order\SaveComment;
use Svea\Checkout\Controller\Order\SaveCoupon;
use Svea\Checkout\Controller\Order\SaveGiftCard;
use Svea\Checkout\Controller\Order\SaveNewsletter;
use Svea\Checkout\Controller\Order\SaveShippingMethod;
use Svea\Checkout\Controller\Order\Update;
use Svea\Checkout\Controller\Order\ValidateOrder;
use Svea\Checkout\Controller\Recurring\Push as RecurringPush;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Service\SveaRecurringInfo;

/**
 * SC-300: only Svea's server to server callbacks skip Magento's CSRF validation, and the
 * checkout update endpoints only accept the checkout's own AJAX requests, also over GET.
 */
class CsrfProtectionTest extends TestCase
{
    public static function checkoutUpdateControllerProvider(): array
    {
        return [
            'cart' => [Cart::class],
            'confirm shipping' => [Confirmshipping::class],
            'save comment' => [SaveComment::class],
            'save coupon' => [SaveCoupon::class],
            'save gift card' => [SaveGiftCard::class],
            'save newsletter' => [SaveNewsletter::class],
            'save shipping method' => [SaveShippingMethod::class],
        ];
    }

    /**
     * @param class-string<Update> $class
     */
    #[DataProvider('checkoutUpdateControllerProvider')]
    public function testCheckoutUpdateRefusesRequestWithoutAjaxHeader(string $class): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $controller = $this->makeController($class);
        $controller->expects($this->never())->method('execute');
        $controller->expects($this->once())->method('_redirect')->with('*')->willReturn($response);

        $this->assertSame($response, $controller->dispatch($this->makeRequest(false)));
    }

    /**
     * @param class-string<Update> $class
     */
    #[DataProvider('checkoutUpdateControllerProvider')]
    public function testCheckoutUpdateKeepsDefaultCsrfValidation(string $class): void
    {
        $this->assertFalse(is_subclass_of($class, CsrfAwareActionInterface::class));
    }

    public function testCheckoutUpdateRunsForAjaxRequest(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $controller = $this->makeController(SaveCoupon::class);
        $controller->expects($this->never())->method('_redirect');
        $controller->expects($this->once())->method('execute')->willReturn($response);

        $this->assertSame($response, $controller->dispatch($this->makeRequest(true)));
    }

    public function testValidateOrderCallbackRunsWithoutAjaxHeader(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $controller = $this->makeController(ValidateOrder::class);
        $controller->expects($this->never())->method('_redirect');
        $controller->expects($this->once())->method('execute')->willReturn($response);

        $this->assertSame($response, $controller->dispatch($this->makeRequest(false)));
    }

    public function testSveaCallbacksSkipCsrfValidation(): void
    {
        $request = $this->makeRequest(false);
        $callbacks = [
            $this->getMockBuilder(Push::class)->disableOriginalConstructor()->onlyMethods([])->getMock(),
            $this->getMockBuilder(ValidateOrder::class)->disableOriginalConstructor()->onlyMethods([])->getMock(),
            new RecurringPush(
                $this->createStub(Http::class),
                $this->createStub(TokenClient::class),
                $this->createStub(RawFactory::class),
                $this->createStub(RecurringInfoRepositoryInterface::class),
                $this->createStub(SveaRecurringInfo::class)
            ),
        ];

        foreach ($callbacks as $callback) {
            $this->assertInstanceOf(CsrfAwareActionInterface::class, $callback);
            $this->assertTrue($callback->validateForCsrf($request));
            $this->assertNull($callback->createCsrfValidationException($request));
        }
    }

    /**
     * @param class-string<Update> $class
     */
    private function makeController(string $class): Update&MockObject
    {
        $controller = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', '_redirect'])
            ->getMock();

        $actionFlag = $this->createStub(ActionFlag::class);
        $actionFlag->method('get')->willReturn(false);
        $property = new \ReflectionProperty(\Magento\Framework\App\Action\Action::class, '_actionFlag');
        $property->setValue($controller, $actionFlag);

        return $controller;
    }

    private function makeRequest(bool $isAjax): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('isXmlHttpRequest')->willReturn($isAjax);
        $request->method('isDispatched')->willReturn(true);
        $request->method('getFullActionName')->willReturn('sveacheckout_order_test');

        return $request;
    }
}

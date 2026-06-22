<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\GetCurrentSveaOrderId;

/**
 * Session subclass that promotes the magic DataObject getSveaOrderId() into a
 * real declared method so PHPUnit 12 can configure it via onlyMethods().
 */
abstract class TestCheckoutSession extends Session
{
    abstract public function getSveaOrderId(): mixed;
}

class GetCurrentSveaOrderIdTest extends TestCase
{
    private TestCheckoutSession&MockObject $checkoutSession;
    private GetCurrentSveaOrderId $service;

    protected function setUp(): void
    {
        // getQuote() is already a real method on Session, so onlyMethods() accepts it.
        // getSveaOrderId() is declared on TestCheckoutSession above to make it mockable.
        $this->checkoutSession = $this->getMockBuilder(TestCheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSveaOrderId', 'getQuote'])
            ->getMock();

        $this->service = new GetCurrentSveaOrderId($this->checkoutSession);
    }

    public function testGetSveaOrderIdDelegatesToSessionAndReturnsValue(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn('abc-123');

        $result = $this->service->getSveaOrderId();

        $this->assertSame('abc-123', $result);
    }

    public function testGetSveaOrderIdReturnsNullWhenSessionHasNoOrderId(): void
    {
        $this->checkoutSession->method('getSveaOrderId')->willReturn(null);

        $result = $this->service->getSveaOrderId();

        $this->assertNull($result);
    }

    public function testGetQuoteIsInheritedFromParent(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $result = $this->service->getQuote();

        $this->assertSame($quote, $result);
    }
}

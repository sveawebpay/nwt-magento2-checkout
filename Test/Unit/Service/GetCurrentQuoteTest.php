<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\GetCurrentQuote;

class GetCurrentQuoteTest extends TestCase
{
    private Session&MockObject $checkoutSession;
    private GetCurrentQuote $service;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(Session::class);

        $this->service = new GetCurrentQuote($this->checkoutSession);
    }

    public function testGetQuoteReturnsQuoteFromSession(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $result = $this->service->getQuote();

        $this->assertSame($quote, $result);
    }

    public function testGetQuoteCachesResultAndCallsSessionOnce(): void
    {
        $quote = $this->createMock(Quote::class);
        $this->checkoutSession->expects($this->once())->method('getQuote')->willReturn($quote);

        $first = $this->service->getQuote();
        $second = $this->service->getQuote();

        $this->assertSame($first, $second);
    }
}

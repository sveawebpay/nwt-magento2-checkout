<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Quote\Total;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Quote\Total\Fee;

class FeeTest extends TestCase
{
    private Fee $fee;

    protected function setUp(): void
    {
        $this->fee = new Fee();
    }

    public function testCollectReturnsSelfWhenNoInvoiceFeeOnQuote(): void
    {
        $quote = $this->makeQuote(0);
        $total = $this->makeTotal();
        $shipping = $this->makeShippingAssignment();

        $result = $this->fee->collect($quote, $shipping, $total);

        $this->assertSame($this->fee, $result);
        $this->assertSame(0, $total->getTotalAmount('svea_invoice_fee'));
    }

    public function testCollectSetsTotalAmountWhenFeePresent(): void
    {
        $quote = $this->makeQuote(25.00);
        $total = $this->makeTotal();
        $shipping = $this->makeShippingAssignment();

        $this->fee->collect($quote, $shipping, $total);

        $this->assertSame(25.00, $total->getTotalAmount('svea_invoice_fee'));
    }

    public function testCollectSetsBaseTotalAmountEqualToTotalAmount(): void
    {
        $quote = $this->makeQuote(30.00);
        $total = $this->makeTotal();
        $shipping = $this->makeShippingAssignment();

        $this->fee->collect($quote, $shipping, $total);

        $this->assertSame(30.00, $total->getBaseTotalAmount('svea_invoice_fee'));
    }

    public function testFetchReturnsEmptyArrayWhenNoFeeInTotal(): void
    {
        $quote = $this->makeQuote(0);
        $total = $this->makeTotal();

        $result = $this->fee->fetch($quote, $total);

        $this->assertSame([], $result);
    }

    public function testFetchReturnsArrayWithCorrectStructureWhenFeePresent(): void
    {
        $quote = $this->makeQuote(20.00);
        $total = $this->makeTotal();
        $total->setTotalAmount('svea_invoice_fee', 20.00);

        $result = $this->fee->fetch($quote, $total);

        $this->assertSame('svea_invoice_fee', $result['code']);
        $this->assertSame(20.00, $result['value']);
        $this->assertArrayHasKey('title', $result);
    }

    public function testGetLabelReturnsInvoiceFeePhrase(): void
    {
        $label = $this->fee->getLabel();

        $this->assertStringContainsStringIgnoringCase('invoice fee', (string)$label);
    }

    // --- helpers ---

    private function makeQuote(float $fee): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $quote->setData('svea_invoice_fee', $fee ?: null);
        return $quote;
    }

    private function makeTotal(): Total
    {
        return $this->getMockBuilder(Total::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    // parent::collect() calls $shippingAssignment->getShipping()->getAddress();
    // _setAddress() requires the concrete Quote\Address class, not the interface.
    private function makeShippingAssignment(): ShippingAssignmentInterface&MockObject
    {
        $address = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->getMock();

        $shipping = $this->createMock(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($address);

        $assignment = $this->createMock(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($shipping);
        return $assignment;
    }
}

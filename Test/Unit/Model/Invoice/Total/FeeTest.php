<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Invoice\Total;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Invoice\Total\Fee;

class FeeTest extends TestCase
{
    private Fee $fee;

    protected function setUp(): void
    {
        $this->fee = new Fee();
    }

    public function testCollectReturnsSelfWhenOrderHasNoInvoiceFee(): void
    {
        $invoice = $this->makeInvoice(0.0);

        $result = $this->fee->collect($invoice);

        $this->assertSame($this->fee, $result);
    }

    public function testCollectReturnsSelfWhenFeeAlreadyInvoiced(): void
    {
        $invoice = $this->makeInvoice(15.00, feeAlreadyInvoiced: true);

        $result = $this->fee->collect($invoice);

        $this->assertSame($this->fee, $result);
        // Grand total must not change
        $this->assertSame(100.00, $invoice->getGrandTotal());
    }

    public function testCollectSetsFeeOnInvoiceAndAddsToGrandTotal(): void
    {
        $invoice = $this->makeInvoice(15.00);
        $invoice->setGrandTotal(100.00);
        $invoice->setBaseGrandTotal(100.00);

        $this->fee->collect($invoice);

        $this->assertSame(15.00, $invoice->getSveaInvoiceFee());
        $this->assertSame(115.00, $invoice->getGrandTotal());
        $this->assertSame(115.00, $invoice->getBaseGrandTotal());
    }

    public function testCollectAddsCorrectlyWhenExistingTotalsAreNonZero(): void
    {
        $invoice = $this->makeInvoice(25.00);
        $invoice->setGrandTotal(200.00);
        $invoice->setBaseGrandTotal(200.00);

        $this->fee->collect($invoice);

        $this->assertSame(225.00, $invoice->getGrandTotal());
        $this->assertSame(225.00, $invoice->getBaseGrandTotal());
    }

    // --- helpers ---

    private function makeInvoice(
        float $fee,
        bool $feeAlreadyInvoiced = false
    ): Invoice&MockObject {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturn(
            $feeAlreadyInvoiced ? ['svea_invoice_fee_invoiced' => true] : []
        );

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment'])
            ->getMock();
        $order->method('getPayment')->willReturn($payment);
        $order->setData('svea_invoice_fee', $fee ?: null);

        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $invoice->method('getOrder')->willReturn($order);
        $invoice->setGrandTotal(100.00);
        $invoice->setBaseGrandTotal(100.00);

        return $invoice;
    }
}

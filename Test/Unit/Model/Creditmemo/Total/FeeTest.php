<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Creditmemo\Total;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Creditmemo\Total\Fee;

class FeeTest extends TestCase
{
    private Fee $fee;

    protected function setUp(): void
    {
        $this->fee = new Fee();
    }

    public function testCollectReturnsSelfWhenOrderHasNoInvoiceFee(): void
    {
        $creditmemo = $this->makeCreditmemo(0.0);

        $result = $this->fee->collect($creditmemo);

        $this->assertSame($this->fee, $result);
    }

    public function testCollectSetsFeeOnCreditmemoAndAddsToGrandTotal(): void
    {
        $creditmemo = $this->makeCreditmemo(15.00);
        $creditmemo->setGrandTotal(80.00);
        $creditmemo->setBaseGrandTotal(80.00);

        $this->fee->collect($creditmemo);

        $this->assertSame(15.00, $creditmemo->getSveaInvoiceFee());
        $this->assertSame(95.00, $creditmemo->getGrandTotal());
        $this->assertSame(95.00, $creditmemo->getBaseGrandTotal());
    }

    public function testCollectAddsCorrectlyWhenExistingTotalsAreNonZero(): void
    {
        $creditmemo = $this->makeCreditmemo(10.00);
        $creditmemo->setGrandTotal(50.00);
        $creditmemo->setBaseGrandTotal(50.00);

        $this->fee->collect($creditmemo);

        $this->assertSame(60.00, $creditmemo->getGrandTotal());
        $this->assertSame(60.00, $creditmemo->getBaseGrandTotal());
    }

    public function testCollectReturnsSelfOnSuccess(): void
    {
        $creditmemo = $this->makeCreditmemo(5.00);

        $result = $this->fee->collect($creditmemo);

        $this->assertSame($this->fee, $result);
    }

    // --- helpers ---

    private function makeCreditmemo(float $fee): Creditmemo&MockObject
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $order->setData('svea_invoice_fee', $fee ?: null);

        $creditmemo = $this->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getMock();
        $creditmemo->method('getOrder')->willReturn($order);
        $creditmemo->setGrandTotal(0.00);
        $creditmemo->setBaseGrandTotal(0.00);

        return $creditmemo;
    }
}

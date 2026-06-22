<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Observer\AddInvoiceFeeToOrder;

/**
 * Observer extends DataObject, so getQuote()/getOrder() resolve via __call → getData().
 * We pass the objects through the Observer constructor instead of stubbing methods.
 */
class AddInvoiceFeeToOrderTest extends TestCase
{
    private AddInvoiceFeeToOrder $observer;

    protected function setUp(): void
    {
        $this->observer = new AddInvoiceFeeToOrder();
    }

    public function testExecuteDoesNotModifyOrderWhenQuoteHasNoInvoiceFee(): void
    {
        $quote = $this->makeQuote(0.0);
        $order = $this->makeOrder(200.00);

        $this->observer->execute(new Observer(['quote' => $quote, 'order' => $order]));

        $this->assertSame(200.00, $order->getGrandTotal());
        $this->assertNull($order->getData('svea_invoice_fee'));
    }

    public function testExecuteSetsInvoiceFeeOnOrder(): void
    {
        $quote = $this->makeQuote(20.00);
        $order = $this->makeOrder(150.00);

        $this->observer->execute(new Observer(['quote' => $quote, 'order' => $order]));

        $this->assertSame(20.00, $order->getData('svea_invoice_fee'));
    }

    public function testExecuteAddsInvoiceFeeToOrderGrandTotal(): void
    {
        $quote = $this->makeQuote(20.00);
        $order = $this->makeOrder(150.00);

        $this->observer->execute(new Observer(['quote' => $quote, 'order' => $order]));

        $this->assertSame(170.00, $order->getGrandTotal());
    }

    // --- helpers ---

    private function makeQuote(float $fee): Quote&MockObject
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        if ($fee > 0) {
            $quote->setData('svea_invoice_fee', $fee);
        }
        return $quote;
    }

    private function makeOrder(float $grandTotal): Order&MockObject
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $order->setGrandTotal($grandTotal);
        return $order;
    }
}

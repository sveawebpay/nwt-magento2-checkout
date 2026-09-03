<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Observer\PaymentCapture;

/**
 * Event and Observer both extend DataObject. Their getPayment()/getInvoice()/
 * getEvent() calls resolve via __call → getData(), so we pass data through the
 * constructor rather than using mocks for those objects.
 */
class PaymentCaptureTest extends TestCase
{
    private PaymentCapture $observer;

    protected function setUp(): void
    {
        $this->observer = new PaymentCapture();
    }

    public function testExecuteSetsInvoiceOnPayment(): void
    {
        $invoice = $this->createMock(Invoice::class);
        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $event = new Event(['payment' => $payment, 'invoice' => $invoice]);
        $eventObserver = new Observer(['event' => $event]);

        $this->observer->execute($eventObserver);

        $this->assertSame($invoice, $payment->getCapturedInvoice());
    }

    public function testExecuteOverwritesPreviousCapturedInvoice(): void
    {
        $firstInvoice = $this->createMock(Invoice::class);
        $secondInvoice = $this->createMock(Invoice::class);

        $payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $payment->setData('captured_invoice', $firstInvoice);

        $event = new Event(['payment' => $payment, 'invoice' => $secondInvoice]);
        $eventObserver = new Observer(['event' => $event]);

        $this->observer->execute($eventObserver);

        $this->assertSame($secondInvoice, $payment->getCapturedInvoice());
    }
}

<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Payment\Method;

use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Payment\Method\Checkout;

class CheckoutTest extends TestCase
{
    private Checkout&MockObject $method;

    private Payment&Stub $payment;

    private Order&Stub $order;

    protected function setUp(): void
    {
        $this->order = $this->getStubBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfig', 'getBaseTotalDue', 'getTotalDue'])
            ->getStub();
        $this->payment = $this->getStubBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder'])
            ->getStub();
        $this->payment->method('getOrder')->willReturn($this->order);

        $this->method = $this->getMockBuilder(Checkout::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getInfoInstance', 'getConfigData', 'authorize'])
            ->getMock();
        $this->method->method('getInfoInstance')->willReturn($this->payment);
    }

    public static function invalidSveaOrderIdProvider(): array
    {
        return [
            'missing' => [null],
            'empty string' => [''],
            'zero' => ['0'],
            'negative' => ['-5'],
            'non numeric' => ['abc'],
            'decimal' => ['12.5'],
            'array' => [['svea_order_id' => '123']],
        ];
    }

    #[DataProvider('invalidSveaOrderIdProvider')]
    public function testInitializeRejectsInvalidSveaOrderId(mixed $sveaOrderId): void
    {
        $this->payment->setAdditionalInformation('svea_order_id', $sveaOrderId);

        $this->method->expects($this->never())->method('authorize');
        $this->expectException(LocalizedException::class);

        try {
            $this->method->initialize('authorize', new DataObject());
        } finally {
            $this->assertNull($this->order->getData('svea_order_id'));
        }
    }

    public function testInitializeRejectsSveaOrderIdNestedUnderAdditionalData(): void
    {
        $this->payment->setAdditionalInformation('additional_data', ['svea_order_id' => '123']);

        $this->method->expects($this->never())->method('authorize');
        $this->expectException(LocalizedException::class);

        $this->method->initialize('authorize', new DataObject());
    }

    public static function validSveaOrderIdProvider(): array
    {
        return [
            'numeric string' => ['8812345'],
            'integer' => [8812345],
        ];
    }

    #[DataProvider('validSveaOrderIdProvider')]
    public function testInitializeAuthorizesWhenSveaOrderIdIsPresent(int|string $sveaOrderId): void
    {
        $this->payment->setAdditionalInformation('svea_order_id', $sveaOrderId);

        $orderConfig = $this->createStub(OrderConfig::class);
        $orderConfig->method('getStateStatuses')->willReturn(['pending' => 'Pending']);
        $this->order->method('getConfig')->willReturn($orderConfig);
        $this->order->method('getBaseTotalDue')->willReturn(100.0);
        $this->order->method('getTotalDue')->willReturn(100.0);
        $this->method->method('getConfigData')->with('order_status')->willReturn('pending');

        $this->method->expects($this->once())->method('authorize')->with($this->payment, 100.0);
        $stateObject = new DataObject();

        $this->method->initialize('authorize', $stateObject);

        $this->assertSame($sveaOrderId, $this->order->getData('svea_order_id'));
        $this->assertSame(Order::STATE_NEW, $stateObject->getState());
        $this->assertSame('pending', $stateObject->getStatus());
    }
}

<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Order;

use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Controller\Order\Push;
use Svea\Checkout\Model\CheckoutContext;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Push as PushModel;
use Svea\Checkout\Model\PushRepository;

/**
 * SC-307: Svea's push and the customer's confirmation can both try to create the order for the
 * same payment. Order creation runs under a database lock keyed on the Svea order id.
 */
class PushLockTest extends TestCase
{
    private const SVEA_ORDER_ID = 9001;
    private const LOCK_NAME = 'svea_checkout_create_order_9001';
    private const HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';

    private LockManagerInterface&MockObject $lockManager;
    private PushModel&MockObject $push;
    private int $existingMagentoOrders = 0;

    protected function setUp(): void
    {
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->push = $this->getMockBuilder(PushModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrderId', 'setOrderId'])
            ->getMock();
    }

    public function testCreatesOrderUnderLockAndReleasesIt(): void
    {
        $this->lockManager->expects($this->once())->method('lock')->with(self::LOCK_NAME, 30)->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock')->with(self::LOCK_NAME);
        $controller = $this->makeController();
        $controller->expects($this->once())->method('createMagentoOrder')->willReturn($this->makeOrder(55));

        $this->assertSame(55, $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH));
    }

    public function testDoesNotCreateOrderWithoutLock(): void
    {
        $this->lockManager->method('lock')->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlock');
        $controller = $this->makeController();
        $controller->expects($this->never())->method('createMagentoOrder');

        $this->expectExceptionMessage('Could not get the order lock for Svea order 9001');
        $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH);
    }

    public function testReturnsOrderCreatedWhileWaitingForLock(): void
    {
        // The other request held the lock past the timeout but finished creating the order.
        $this->existingMagentoOrders = 1;
        $this->lockManager->method('lock')->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlock');
        $controller = $this->makeController();
        $controller->expects($this->never())->method('createMagentoOrder');

        $this->assertSame(77, $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH));
    }

    public function testFailsClearlyWithoutLockManagerConfig(): void
    {
        $controller = $this->makeController(null);
        $controller->expects($this->never())->method('createMagentoOrder');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('controller:order:push');
        $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH);
    }

    public function testReturnsOrderCreatedByOtherRequest(): void
    {
        // The other request held the lock, created the order and saved it on the push.
        $this->push->method('getOrderId')->willReturn(55);
        $this->lockManager->method('lock')->willReturn(true);
        $controller = $this->makeController();
        $controller->expects($this->never())->method('createMagentoOrder');

        $this->assertSame(55, $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH));
    }

    public function testReturnsExistingMagentoOrderNotYetSavedOnPush(): void
    {
        $this->existingMagentoOrders = 1;
        $this->lockManager->method('lock')->willReturn(true);
        $controller = $this->makeController();
        $controller->expects($this->never())->method('createMagentoOrder');

        $this->assertSame(77, $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH));
    }

    public function testReleasesLockWhenOrderCreationFails(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock')->with(self::LOCK_NAME);
        $controller = $this->makeController();
        $controller->method('createMagentoOrder')->willThrowException(new \Exception('Could not place order'));

        $this->expectExceptionMessage('Could not place order');
        $controller->tryToCreateOrder(self::SVEA_ORDER_ID, self::HASH);
    }

    /**
     * @param array|null|false $serviceContainer false: the default container with the lock manager
     */
    private function makeController($serviceContainer = false): Push&MockObject
    {
        if ($serviceContainer === false) {
            $serviceContainer = ['lockManager' => $this->lockManager];
        }

        $sveaOrder = $this->createStub(GetOrderResponse::class);
        $sveaOrder->method('getOrderId')->willReturn(self::SVEA_ORDER_ID);
        $sveaOrder->method('getStatus')->willReturn('Final');

        $pushRepo = $this->createStub(PushRepository::class);
        $pushRepo->method('get')->willReturn($this->push);

        $existingOrder = $this->makeOrder(77);
        $collection = $this->createStub(OrderCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('load')->willReturnSelf();
        $collection->method('count')->willReturnCallback(fn () => $this->existingMagentoOrders);
        $collection->method('getFirstItem')->willReturn($existingOrder);
        $collectionFactory = $this->createStub(OrderCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $context = $this->createStub(CheckoutContext::class);
        $context->method('getServiceContainer')
            ->willReturnMap([['controller:order:push', $serviceContainer]]);
        $context->method('getOrderCollectionFactory')->willReturn($collectionFactory);

        $controller = $this->getMockBuilder(Push::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadSveaOrder', 'createMagentoOrder'])
            ->getMock();
        $controller->method('loadSveaOrder')->willReturn($sveaOrder);

        $this->setProperty($controller, 'pushRepo', $pushRepo);
        $this->setProperty($controller, 'sveaCheckoutContext', $context);

        return $controller;
    }

    private function makeOrder(int $id): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn($id);

        return $order;
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

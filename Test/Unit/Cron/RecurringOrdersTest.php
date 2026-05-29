<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Cron;

use Magento\Framework\App\Area;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\App\EmulationFactory;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Cron\RecurringOrders;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\RecurringInfo;
use Svea\Checkout\Service\SveaRecurringInfo\PlaceOrders;

class RecurringOrdersTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private Data&MockObject $helper;
    private PlaceOrders&MockObject $placeOrders;
    private RecurringInfoRepositoryInterface&MockObject $recurringInfoRepo;
    private EmulationFactory&MockObject $emulationFactory;
    private RecurringOrders $cron;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->helper = $this->createMock(Data::class);
        $this->placeOrders = $this->createMock(PlaceOrders::class);
        $this->recurringInfoRepo = $this->createMock(RecurringInfoRepositoryInterface::class);
        $this->emulationFactory = $this->createMock(EmulationFactory::class);

        $this->cron = new RecurringOrders(
            $this->storeManager,
            $this->helper,
            $this->placeOrders,
            $this->recurringInfoRepo,
            $this->emulationFactory
        );
    }

    public function testPlaceOrdersDoesNothingWhenNoStores(): void
    {
        $this->storeManager->method('getStores')->willReturn([]);

        $this->placeOrders->expects($this->never())->method('placeRecurringOrders');

        $this->cron->placeOrders();
    }

    public function testPlaceOrdersSkipsStoreWhenRecurringPaymentsInactive(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->helper->method('getRecurringPaymentsActive')->with(1)->willReturn(false);

        $this->placeOrders->expects($this->never())->method('placeRecurringOrders');

        $this->cron->placeOrders();
    }

    public function testPlaceOrdersSkipsStoreWhenNoOrdersDueToday(): void
    {
        $store = $this->makeStore(1);
        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->helper->method('getRecurringPaymentsActive')->willReturn(true);
        $this->recurringInfoRepo->method('getByTodaysDate')->with(1)->willReturn([]);

        $this->placeOrders->expects($this->never())->method('placeRecurringOrders');
        $this->emulationFactory->expects($this->never())->method('create');

        $this->cron->placeOrders();
    }

    public function testPlaceOrdersRunsEmulationAndPlacesOrdersForActiveStore(): void
    {
        $store = $this->makeStore(2);
        $recurringInfo = $this->makeRecurringInfo();
        $emulation = $this->createMock(Emulation::class);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->helper->method('getRecurringPaymentsActive')->with(2)->willReturn(true);
        $this->recurringInfoRepo->method('getByTodaysDate')->with(2)->willReturn([$recurringInfo]);
        $this->emulationFactory->method('create')->willReturn($emulation);

        $emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with(2, Area::AREA_FRONTEND, true);
        $emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->placeOrders->expects($this->once())
            ->method('placeRecurringOrders')
            ->with([$recurringInfo]);

        $this->cron->placeOrders();
    }

    public function testPlaceOrdersSavesEachRecurringInfoAfterPlacement(): void
    {
        $store = $this->makeStore(1);
        $info1 = $this->makeRecurringInfo();
        $info2 = $this->makeRecurringInfo();
        $emulation = $this->createMock(Emulation::class);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->helper->method('getRecurringPaymentsActive')->willReturn(true);
        $this->recurringInfoRepo->method('getByTodaysDate')->willReturn([$info1, $info2]);
        $this->emulationFactory->method('create')->willReturn($emulation);

        $this->recurringInfoRepo->expects($this->exactly(2))
            ->method('save')
            ->with($this->logicalOr($info1, $info2));

        $this->cron->placeOrders();
    }

    public function testPlaceOrdersStopsEmulationEvenAfterOrdersArePlaced(): void
    {
        $store = $this->makeStore(1);
        $emulation = $this->createMock(Emulation::class);

        $this->storeManager->method('getStores')->willReturn([$store]);
        $this->helper->method('getRecurringPaymentsActive')->willReturn(true);
        $this->recurringInfoRepo->method('getByTodaysDate')->willReturn([$this->makeRecurringInfo()]);
        $this->emulationFactory->method('create')->willReturn($emulation);

        // Verify emulation is always stopped — order: start first, stop last
        $callOrder = [];
        $emulation->method('startEnvironmentEmulation')
            ->willReturnCallback(function () use (&$callOrder) { $callOrder[] = 'start'; });
        $emulation->method('stopEnvironmentEmulation')
            ->willReturnCallback(function () use (&$callOrder) { $callOrder[] = 'stop'; });

        $this->cron->placeOrders();

        $this->assertSame(['start', 'stop'], $callOrder);
    }

    public function testPlaceOrdersProcessesEachStoreIndependently(): void
    {
        $store1 = $this->makeStore(1);
        $store2 = $this->makeStore(2);
        $emulation = $this->createMock(Emulation::class);

        $this->storeManager->method('getStores')->willReturn([$store1, $store2]);
        $this->helper->method('getRecurringPaymentsActive')->willReturn(true);
        $this->recurringInfoRepo->method('getByTodaysDate')
            ->willReturnCallback(fn(int $storeId) => [$this->makeRecurringInfo()]);
        $this->emulationFactory->method('create')->willReturn($emulation);

        $this->placeOrders->expects($this->exactly(2))->method('placeRecurringOrders');

        $this->cron->placeOrders();
    }

    // --- helpers ---

    private function makeStore(int $id): StoreInterface&MockObject
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($id);
        return $store;
    }

    private function makeRecurringInfo(): RecurringInfo&MockObject
    {
        return $this->getMockBuilder(RecurringInfo::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}

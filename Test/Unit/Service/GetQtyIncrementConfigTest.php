<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;
use Svea\Checkout\Model\QtyIncrementConfig;
use Svea\Checkout\Model\QtyIncrementConfigFactory;
use Svea\Checkout\Service\GetQtyIncrementConfig;

class GetQtyIncrementConfigTest extends TestCase
{
    private StockRegistryInterface&MockObject $stockRegistry;
    private QtyIncrementConfigFactory&MockObject $qtyIncrementFactory;
    private GetQtyIncrementConfig $service;

    protected function setUp(): void
    {
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->qtyIncrementFactory = $this->createMock(QtyIncrementConfigFactory::class);

        $this->service = new GetQtyIncrementConfig(
            $this->stockRegistry,
            $this->qtyIncrementFactory
        );
    }

    public function testExecuteReturnsQtyIncrementConfigInterface(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(10);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getEnableQtyIncrements')->willReturn(false);
        $stockItem->method('getQtyIncrements')->willReturn(1.0);

        $this->stockRegistry->method('getStockItem')->with(10)->willReturn($stockItem);

        $qtyIncrementConfig = $this->getMockBuilder(QtyIncrementConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->qtyIncrementFactory->method('create')->willReturn($qtyIncrementConfig);

        $result = $this->service->execute($product);

        $this->assertInstanceOf(QtyIncrementConfigInterface::class, $result);
    }

    public function testExecuteBuildsConfigFromStockItemValues(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(42);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getEnableQtyIncrements')->willReturn(true);
        $stockItem->method('getQtyIncrements')->willReturn(3.0);

        $this->stockRegistry->method('getStockItem')->with(42)->willReturn($stockItem);

        $qtyIncrementConfig = $this->getMockBuilder(QtyIncrementConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->qtyIncrementFactory->method('create')->willReturn($qtyIncrementConfig);

        $result = $this->service->execute($product);

        $this->assertTrue($result->isEnableQtyIncrements());
        $this->assertSame(3.0, $result->getQtyIncrements());
    }

    public function testExecuteReflectsDisabledQtyIncrementsAndDefaultIncrement(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(7);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getEnableQtyIncrements')->willReturn(false);
        $stockItem->method('getQtyIncrements')->willReturn(1.0);

        $this->stockRegistry->method('getStockItem')->with(7)->willReturn($stockItem);

        $qtyIncrementConfig = $this->getMockBuilder(QtyIncrementConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->qtyIncrementFactory->method('create')->willReturn($qtyIncrementConfig);

        $result = $this->service->execute($product);

        $this->assertFalse($result->isEnableQtyIncrements());
        $this->assertSame(1.0, $result->getQtyIncrements());
    }

    public function testExecuteFetchesStockItemByProductId(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getId')->willReturn(99);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getEnableQtyIncrements')->willReturn(false);
        $stockItem->method('getQtyIncrements')->willReturn(1.0);

        $this->stockRegistry->expects($this->once())
            ->method('getStockItem')
            ->with(99)
            ->willReturn($stockItem);

        $qtyIncrementConfig = $this->getMockBuilder(QtyIncrementConfig::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->qtyIncrementFactory->method('create')->willReturn($qtyIncrementConfig);

        $this->service->execute($product);
    }
}

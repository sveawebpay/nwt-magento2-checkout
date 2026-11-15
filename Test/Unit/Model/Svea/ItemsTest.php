<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Svea;

use Magento\Catalog\Helper\Product\Configuration as ProductConfiguration;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Tax\Model\Calculation;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Helper\Data as SveaHelper;
use Svea\Checkout\Helper\GiftCard;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Svea\Items;

class ItemsTest extends TestCase
{
    private Items $items;

    protected function setUp(): void
    {
        $helper = $this->createStub(SveaHelper::class);
        $helper->method('getMaximumAmountDiff')->willReturn(0);

        $productConfig = $this->createStub(ProductConfiguration::class);
        $productConfig->method('getOptions')->willReturn([]);

        $this->items = new Items(
            $helper,
            $productConfig,
            $this->createStub(Calculation::class),
            $this->createStub(GiftCard::class)
        );
    }

    public function testFixedPriceBundleChildSharingSkuWithStandaloneItemKeepsBothRows(): void
    {
        $standalone = $this->makeItem(1, 'X', 100.00);
        $bundle = $this->makeItem(2, 'B', 300.00, 'bundle', false, [
            $this->makeItem(3, 'X', 50.00),
            $this->makeItem(4, 'Y', 250.00),
        ]);

        $this->items->addItems([$standalone, $bundle]);

        $this->assertRowsMatchGrandTotal(400.00);
        $this->assertSame(['X-1', 'B', 'X-3', 'Y'], $this->getArticleNumbers());
    }

    public function testDynamicBundleChildSharingSkuWithStandaloneItemKeepsBothRows(): void
    {
        $standalone = $this->makeItem(1, 'X', 100.00);
        $bundle = $this->makeItem(2, 'D', 0.00, 'bundle', true, [
            $this->makeItem(3, 'X', 40.00),
            $this->makeItem(4, 'Y', 60.00),
        ]);

        $this->items->addItems([$standalone, $bundle]);

        $this->assertRowsMatchGrandTotal(200.00);
        $this->assertSame(['X-1', 'X-3', 'Y'], $this->getArticleNumbers());
    }

    public function testCartWithoutDuplicateSkusKeepsPlainArticleNumbers(): void
    {
        $standalone = $this->makeItem(1, 'A', 100.00);
        $fixedBundle = $this->makeItem(2, 'B', 300.00, 'bundle', false, [
            $this->makeItem(3, 'X', 50.00),
            $this->makeItem(4, 'Y', 250.00),
        ]);
        $dynamicBundle = $this->makeItem(5, 'D', 0.00, 'bundle', true, [
            $this->makeItem(6, 'Z', 40.00),
            $this->makeItem(7, 'W', 60.00),
        ]);

        $this->items->addItems([$standalone, $fixedBundle, $dynamicBundle]);

        $this->assertRowsMatchGrandTotal(500.00);
        $this->assertSame(['A', 'B', 'X', 'Y', 'Z', 'W'], $this->getArticleNumbers());
    }

    private function assertRowsMatchGrandTotal(float $grandTotal): void
    {
        $rowsTotal = 0;
        foreach ($this->items->getCart() as $row) {
            $rowsTotal += $row->getUnitPrice() * ($row->getQuantity() / 100);
        }

        $this->assertEquals($this->items->addZeroes($grandTotal), $rowsTotal);
        $this->assertSame($this->items, $this->items->validateTotals($grandTotal));
    }

    private function getArticleNumbers(): array
    {
        return array_values(array_map(
            static fn (OrderRow $row) => $row->getArticleNumber(),
            $this->items->getCart()
        ));
    }

    private function makeItem(
        int $id,
        string $sku,
        float $priceInclTax,
        string $productType = 'simple',
        bool $childrenCalculated = false,
        array $children = []
    ): QuoteItem&Stub {
        $item = $this->getStubBuilder(QuoteItem::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getProductType', 'isChildrenCalculated', 'getChildren'])
            ->getStub();
        $item->method('getProductType')->willReturn($productType);
        $item->method('isChildrenCalculated')->willReturn($childrenCalculated);
        $item->method('getChildren')->willReturn($children);
        $item->setData([
            'id' => $id,
            'sku' => $sku,
            'name' => 'Product ' . $sku,
            'qty' => 1,
            'price_incl_tax' => $priceInclTax,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
        ]);
        foreach ($children as $child) {
            $child->setData('parent_item_id', $id);
        }

        return $item;
    }
}

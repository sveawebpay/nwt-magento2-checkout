<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Client\DTO\Order;

use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;

class OrderRowTest extends TestCase
{
    private OrderRow $orderRow;

    protected function setUp(): void
    {
        $this->orderRow = new OrderRow();
    }

    public function testToArrayIncludesAllRequiredFields(): void
    {
        $this->orderRow
            ->setArticleNumber('SKU-001')
            ->setName('Test Product')
            ->setQuantity(2)
            ->setUnitPrice(10000)
            ->setVatPercent(2500);

        $result = $this->orderRow->toArray();

        $this->assertArrayHasKey('ArticleNumber', $result);
        $this->assertArrayHasKey('Name', $result);
        $this->assertArrayHasKey('Quantity', $result);
        $this->assertArrayHasKey('UnitPrice', $result);
        $this->assertArrayHasKey('VatPercent', $result);
        $this->assertArrayHasKey('RowType', $result);
    }

    public function testToArrayOmitsOptionalFieldsWhenUnset(): void
    {
        $this->orderRow
            ->setArticleNumber('SKU-001')
            ->setName('Test Product')
            ->setQuantity(1)
            ->setUnitPrice(5000)
            ->setVatPercent(2500);

        $result = $this->orderRow->toArray();

        $this->assertArrayNotHasKey('Unit', $result);
        $this->assertArrayNotHasKey('DiscountPercent', $result);
        $this->assertArrayNotHasKey('DiscountAmount', $result);
        $this->assertArrayNotHasKey('TemporaryReference', $result);
        $this->assertArrayNotHasKey('RowNumber', $result);
        $this->assertArrayNotHasKey('MerchantData', $result);
    }

    public function testToArrayIncludesOptionalFieldsWhenSet(): void
    {
        $this->orderRow
            ->setArticleNumber('SKU-002')
            ->setName('Product With Options')
            ->setQuantity(3)
            ->setUnitPrice(20000)
            ->setVatPercent(2500)
            ->setUnit('st')
            ->setDiscountPercent(1000)
            ->setDiscountAmount(500)
            ->setTemporaryReference('TMP-REF-42')
            ->setRowNumber(5)
            ->setMerchantData('{"custom":"data"}');

        $result = $this->orderRow->toArray();

        $this->assertSame('st', $result['Unit']);
        $this->assertSame(1000, $result['DiscountPercent']);
        $this->assertSame(500, $result['DiscountAmount']);
        $this->assertSame('TMP-REF-42', $result['TemporaryReference']);
        $this->assertSame(5, $result['RowNumber']);
        $this->assertSame('{"custom":"data"}', $result['MerchantData']);
    }

    public function testDefaultRowTypeIsRow(): void
    {
        $this->assertSame('Row', $this->orderRow->getRowType());
    }

    public function testSetRowTypeIsShippingFeeSetsShippingFeeType(): void
    {
        $result = $this->orderRow->setRowTypeIsShippingFee();

        $this->assertSame('ShippingFee', $this->orderRow->getRowType());
        $this->assertSame($this->orderRow, $result);
    }

    public function testCanCancelRowReturnsTrueWhenActionIsPresent(): void
    {
        $this->orderRow->setActions(['CanCancelRow', 'SomeOtherAction']);

        $this->assertTrue($this->orderRow->canCancelRow());
    }

    public function testCanCancelRowReturnsFalseWhenActionIsAbsent(): void
    {
        $this->orderRow->setActions(['SomeOtherAction']);

        $this->assertFalse($this->orderRow->canCancelRow());
    }

    public function testCanCancelRowReturnsFalseWhenActionsIsEmpty(): void
    {
        $this->orderRow->setActions([]);

        $this->assertFalse($this->orderRow->canCancelRow());
    }

    public function testFullDeliveryDefaultsToTrue(): void
    {
        $this->assertTrue($this->orderRow->getFullDelivery());
    }

    public function testSetFullDeliveryFalseStoresFalse(): void
    {
        $this->orderRow->setFullDelivery(false);

        $this->assertFalse($this->orderRow->getFullDelivery());
    }

    public function testSetArticleNumberReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setArticleNumber('ABC'));
    }

    public function testSetNameReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setName('Widget'));
    }

    public function testSetQuantityReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setQuantity(1));
    }

    public function testSetUnitPriceReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setUnitPrice(9900));
    }

    public function testSetVatPercentReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setVatPercent(2500));
    }

    public function testSetDiscountPercentReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setDiscountPercent(500));
    }

    public function testSetDiscountAmountReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setDiscountAmount(100));
    }

    public function testSetUnitReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setUnit('kg'));
    }

    public function testSetTemporaryReferenceReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setTemporaryReference('REF-1'));
    }

    public function testSetRowNumberReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setRowNumber(3));
    }

    public function testSetMerchantDataReturnsSelf(): void
    {
        $this->assertSame($this->orderRow, $this->orderRow->setMerchantData('data'));
    }

    public function testToJsonReturnsJsonEncodedArray(): void
    {
        $this->orderRow
            ->setArticleNumber('SKU-JSON')
            ->setName('JSON Product')
            ->setQuantity(1)
            ->setUnitPrice(7500)
            ->setVatPercent(2500);

        $json = $this->orderRow->toJSON();
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('SKU-JSON', $decoded['ArticleNumber']);
        $this->assertSame('Row', $decoded['RowType']);
    }

    public function testToArrayReflectsShippingFeeRowType(): void
    {
        $this->orderRow
            ->setArticleNumber('SHIP')
            ->setName('Shipping')
            ->setQuantity(1)
            ->setUnitPrice(4900)
            ->setVatPercent(2500)
            ->setRowTypeIsShippingFee();

        $result = $this->orderRow->toArray();

        $this->assertSame('ShippingFee', $result['RowType']);
    }
}

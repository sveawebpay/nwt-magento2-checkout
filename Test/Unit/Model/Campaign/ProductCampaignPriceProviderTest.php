<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Campaign;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Campaign\ProductCampaignPriceProvider;

class ProductCampaignPriceProviderTest extends TestCase
{
    private ProductCampaignPriceProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ProductCampaignPriceProvider();
    }

    public function testGetPricesReturnsEmptyArrayWhenProductIsNotCatalogProductInstance(): void
    {
        // A mock that implements only ProductInterface, not Magento\Catalog\Model\Product
        $product = $this->createMock(ProductInterface::class);

        $result = $this->provider->getPrices($product);

        $this->assertSame([], $result);
    }

    public function testGetPricesReturnsFinalPriceForSimpleProduct(): void
    {
        $product = $this->makeSimpleProduct(typeId: 'simple', finalPrice: 99.99);

        $result = $this->provider->getPrices($product);

        $this->assertSame([99.99], $result);
    }

    public function testGetPricesReturnsFinalPriceWrappedInArrayForVirtualProduct(): void
    {
        $product = $this->makeSimpleProduct(typeId: 'virtual', finalPrice: 49.50);

        $result = $this->provider->getPrices($product);

        $this->assertSame([49.50], $result);
    }

    public function testGetPricesReturnsSortedPricesForConfigurableProduct(): void
    {
        $children = [
            $this->makeChildProduct(finalPrice: 299.0),
            $this->makeChildProduct(finalPrice: 99.0),
            $this->makeChildProduct(finalPrice: 199.0),
        ];

        $product = $this->makeConfigurableProduct($children);

        $result = $this->provider->getPrices($product);

        $this->assertSame([99.0, 199.0, 299.0], $result);
    }

    public function testGetPricesReturnsMultiplePricesInAscendingOrderForConfigurable(): void
    {
        $children = [
            $this->makeChildProduct(finalPrice: 500.0),
            $this->makeChildProduct(finalPrice: 100.0),
            $this->makeChildProduct(finalPrice: 750.0),
            $this->makeChildProduct(finalPrice: 250.0),
        ];

        $product = $this->makeConfigurableProduct($children);

        $result = $this->provider->getPrices($product);

        $this->assertSame([100.0, 250.0, 500.0, 750.0], $result);
        $this->assertCount(4, $result);
    }

    public function testGetPricesCastsSimpleProductPriceToFloat(): void
    {
        $product = $this->makeSimpleProduct(typeId: 'simple', finalPrice: '129');

        $result = $this->provider->getPrices($product);

        $this->assertIsFloat($result[0]);
        $this->assertSame(129.0, $result[0]);
    }

    public function testGetPricesConfigurableWithSingleChildReturnsSingleElementArray(): void
    {
        $children = [$this->makeChildProduct(finalPrice: 399.0)];
        $product = $this->makeConfigurableProduct($children);

        $result = $this->provider->getPrices($product);

        $this->assertSame([399.0], $result);
    }

    public function testGetPricesReturnsEmptyArrayForProductInterfaceImplementation(): void
    {
        // Explicitly verify that an object implementing only ProductInterface returns []
        // even when a typeId is set — because it is not an instance of Catalog Product
        $product = $this->getMockBuilder(ProductInterface::class)->getMock();

        $result = $this->provider->getPrices($product);

        $this->assertSame([], $result);
    }

    // --- Helpers ---

    /**
     * @param string|float $finalPrice
     */
    private function makeSimpleProduct(string $typeId, mixed $finalPrice): Product&MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getFinalPrice')->willReturn($finalPrice);

        return $product;
    }

    /**
     * @param string|float $finalPrice
     */
    private function makeChildProduct(mixed $finalPrice): Product&MockObject
    {
        $child = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $child->method('getFinalPrice')->willReturn($finalPrice);

        return $child;
    }

    /**
     * @param Product[]&MockObject[] $children
     */
    private function makeConfigurableProduct(array $children): Product&MockObject
    {
        $configurableType = $this->getMockBuilder(Configurable::class)
            ->disableOriginalConstructor()
            ->getMock();

        $configurableType->method('getUsedProducts')->willReturn($children);

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->getMock();

        $product->method('getTypeId')->willReturn('configurable');
        $product->method('getTypeInstance')->willReturn($configurableType);

        return $product;
    }
}

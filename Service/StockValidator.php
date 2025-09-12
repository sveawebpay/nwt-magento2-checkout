<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\CatalogInventory\Api\RegisterProductSaleInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Model\ResourceModel\Stock as ResourceStock;
use Magento\Framework\Exception\LocalizedException;
use Magento\CatalogInventory\Model\StockState;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\CatalogInventory\Model\Stock\Item as StockItem;
use Magento\CatalogInventory\Model\StockStateException;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Service implementing RegisterProductSaleInterface but using it only for validating items are in stock
 * Implementing this interface because then it works with Multi Source Inventory both enabled and disabled
 * Based on:
 * @see \Magento\CatalogInventory\Model\StockManagement
 */
class StockValidator implements RegisterProductSaleInterface
{
    /**
     * @var StockRegistryProviderInterface
     */
    private StockRegistryProviderInterface $stockRegistryProvider;

    /**
     * @var StockState
     */
    private StockState $stockState;

    /**
     * @var StockConfigurationInterface
     */
    private StockConfigurationInterface $stockConfiguration;

    /**
     * @var ResourceStock
     */
    private ResourceStock $resource;

    /**
     * @var StockRegistryStorage
     */
    private StockRegistryStorage $stockRegistryStorage;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepo;

    public function __construct(
        ResourceStock $stockResource,
        StockRegistryProviderInterface $stockRegistryProvider,
        StockState $stockState,
        StockConfigurationInterface $stockConfiguration,
        StockRegistryStorage $stockRegistryStorage,
        ProductRepositoryInterface $productRepo
    ) {
        $this->stockRegistryProvider = $stockRegistryProvider;
        $this->stockState = $stockState;
        $this->stockConfiguration = $stockConfiguration;
        $this->resource = $stockResource;
        $this->stockRegistryStorage = $stockRegistryStorage;
        $this->productRepo = $productRepo;
    }

    /**
     * Validate that items are in stock
     * Does not register a sale despite the name,
     * Needs to keep that name just to implement interface properly
     *
     * @param string[] $items
     * @param int $websiteId
     * @return void
     * @throws StockStateException
     * @throws LocalizedException
     */
    public function registerProductsSale($items, $websiteId = null)
    {
        $this->resource->beginTransaction();
        $websiteId = $this->stockConfiguration->getDefaultScopeId();
        $lockedItems = $this->resource->lockProductsStock(array_keys($items), $websiteId);
        $outOfStock = [];
        foreach ($lockedItems as $lockedItemRecord) {
            $productId = $lockedItemRecord['product_id'];
            $this->stockRegistryStorage->removeStockItem($productId, $websiteId);

            $orderedQty = $items[$productId];
            $stockItem = $this->stockRegistryProvider->getStockItem($productId, $websiteId);

            /** @var StockItem $stockItem */
            $stockItem->setQty($lockedItemRecord['qty']); // update data from locked item
            $canSubtractQty = $stockItem->getItemId() && $this->canSubtractQty($stockItem);
            if (!$canSubtractQty || !$this->stockConfiguration->isQty($lockedItemRecord['type_id'])) {
                continue;
            }

            if (!$this->stockState->checkQty($productId, $orderedQty, $websiteId)
                || !$this->stockState->verifyStock($productId, $websiteId)
            ) {
                $outOfStock[] = $this->getProductName((int)$productId);
            }
        }
        $this->resource->commit();
        if (empty(array_filter($outOfStock))) {
            return;
        }

        throw new StockStateException(
            __('Some of the products are out of stock.')
        );
    }

    /**
     * Check if is possible subtract value from item qty
     *
     * @param StockItemInterface $stockItem
     * @return bool
     */
    private function canSubtractQty(StockItemInterface $stockItem): bool
    {
        return $stockItem->getManageStock() && $this->stockConfiguration->canSubtractQty();
    }

    /**
     * @param int $productId
     * @return string|null
     */
    private function getProductName(int $productId): ?string
    {
        try {
            $product = $this->productRepo->getById($productId);
        } catch (\Exception $e) {
            return null;
        }

        return $product->getName();
    }
}

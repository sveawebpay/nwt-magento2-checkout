<?php

namespace Svea\Checkout\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;
use Svea\Checkout\Api\GetQtyIncrementConfigInterface;
use Svea\Checkout\Model\QtyIncrementConfigFactory;

class GetQtyIncrementConfig implements GetQtyIncrementConfigInterface
{
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var QtyIncrementConfigFactory
     */
    private $qtyIncrementFactory;

    public function __construct(
        StockRegistryInterface $stockRegistry,
        QtyIncrementConfigFactory $qtyIncrementFactory
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->qtyIncrementFactory = $qtyIncrementFactory;
    }

    public function execute(ProductInterface $product): QtyIncrementConfigInterface
    {
        $stockItem = $this->stockRegistry->getStockItem($product->getId());

        $qtyIncrement = $this->qtyIncrementFactory->create();
        $qtyIncrement->setEnableQtyIncrements($stockItem->getEnableQtyIncrements());
        $qtyIncrement->setQtyIncrements($stockItem->getQtyIncrements());
        return $qtyIncrement;
    }
}

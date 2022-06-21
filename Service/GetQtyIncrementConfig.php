<?php

namespace Svea\Checkout\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;
use Svea\Checkout\Api\GetQtyIncrementConfigInterface;
use Svea\Checkout\Model\QtyIncrementConfigFactory;

class GetQtyIncrementConfig implements GetQtyIncrementConfigInterface
{
    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStock;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockConfig;

    /**
     * @var QtyIncrementConfigFactory
     */
    private $qtyIncrementFactory;

    public function __construct(
        DefaultStockProviderInterface $defaultStock,
        GetStockItemConfigurationInterface $getStockConfig,
        QtyIncrementConfigFactory $qtyIncrementFactory
    ) {
        $this->defaultStock = $defaultStock;
        $this->getStockConfig = $getStockConfig;
        $this->qtyIncrementFactory = $qtyIncrementFactory;
    }

    public function execute(ProductInterface $product): QtyIncrementConfigInterface
    {
        $stockConfig = $this->getStockConfig->execute($product->getSku(), $this->defaultStock->getId());
        $qtyIncrement = $this->qtyIncrementFactory->create();
        $qtyIncrement->setEnableQtyIncrements($stockConfig->isEnableQtyIncrements());
        $qtyIncrement->setQtyIncrements($stockConfig->getQtyIncrements());
        return $qtyIncrement;
    }
}

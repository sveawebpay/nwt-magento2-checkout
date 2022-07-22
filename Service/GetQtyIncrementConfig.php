<?php

namespace Svea\Checkout\Service;

use Magento\Catalog\Api\Data\ProductInterface;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;
use Svea\Checkout\Api\GetQtyIncrementConfigInterface;
use Svea\Checkout\Model\QtyIncrementConfigFactory;

class GetQtyIncrementConfig implements GetQtyIncrementConfigInterface
{
    /**
     * @var QtyIncrementConfigFactory
     */
    private $qtyIncrementFactory;

    public function __construct(
        QtyIncrementConfigFactory $qtyIncrementFactory
    ) {
        $this->qtyIncrementFactory = $qtyIncrementFactory;
    }

    public function execute(ProductInterface $product): QtyIncrementConfigInterface
    {
        // MSI disabled - can't use Magento\Inventory*
        $qtyIncrementFromStockItem = $product->getStockItem()->getData('qty_increments');

        $qtyIncrement = $this->qtyIncrementFactory->create();
        $qtyIncrement->setEnableQtyIncrements(true);
        $qtyIncrement->setQtyIncrements($qtyIncrementFromStockItem);
        return $qtyIncrement;
    }
}

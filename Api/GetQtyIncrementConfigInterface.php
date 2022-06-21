<?php

namespace Svea\Checkout\Api;

use Magento\Catalog\Api\Data\ProductInterface;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;

interface GetQtyIncrementConfigInterface
{
    /**
     * Get Qty Increment Config for the provided product
     *
     * @param ProductInterface $product
     * @return QtyIncrementConfigInterface
     */
    public function execute(ProductInterface $product): QtyIncrementConfigInterface;
}

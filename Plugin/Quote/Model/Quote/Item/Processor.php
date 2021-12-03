<?php

namespace Svea\Checkout\Plugin\Quote\Model\Quote\Item;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Processor as OriginalProcessor;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Class Processor
 * @package Svea\Checkout\Plugin\Quote\Model\Product\Option
 */
class Processor
{
    /**
     * @param OriginalProcessor $subject
     * @param callable $proceed
     * @param Item $item
     * @param DataObject $request
     * @param Product $candidate
     */
    public function aroundPrepare(
        OriginalProcessor $subject,
        callable $proceed,
        Item $item,
        DataObject $request,
        Product $candidate
    ) {
        /**
         * We specify qty after we know about parent (for stock)
         */
        if ($request->getResetCount() && !$candidate->getStickWithinParent() && $item->getId() == $request->getId()) {
            $item->setData(CartItemInterface::KEY_QTY, 0);
        }

        if ($item->getQuote()->getSveaClientOrderNumber() === null && ($item->getQty() != $candidate->getCartQty())) {
            $item->addQty($candidate->getCartQty());
        }

        $customPrice = $request->getCustomPrice();
        if (!empty($customPrice)) {
            $item->setCustomPrice($customPrice);
            $item->setOriginalCustomPrice($customPrice);
        }
    }
}

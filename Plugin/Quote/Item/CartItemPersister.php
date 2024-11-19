<?php
namespace Svea\Checkout\Plugin\Quote\Item;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;

class CartItemPersister
{
    public function aroundSave(
        \Magento\Quote\Model\Quote\Item\CartItemPersister $persister,
        \closure $closure,
        CartInterface $quote, CartItemInterface $item
    )
    {
        // If the quote is not active, then skip this item save (probably after order placement)
        if ($quote->getIsActive() === false) {
            return;
        }

        // Else call as usual
        $closure($quote, $item);
    }
}
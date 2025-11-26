<?php

namespace Svea\Checkout\Controller\Order;

class Cart extends Update
{
    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        return $this->_sendResponse([
            'cart',
            'coupon',
            'shipping',
            'shipping_method',
            'messages',
            'svea',
            'newsletter'
        ], true);
    }
}

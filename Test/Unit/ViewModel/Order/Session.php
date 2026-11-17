<?php

declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\ViewModel\Order;

class Session extends \Magento\Checkout\Model\Session
{
    public function getLastOrderId()
    {
        return $this->getData('last_order_id');
    }

    public function getLastRealOrderId()
    {
        return $this->getData('last_real_order_id');
    }

    public function getSveaOrderId()
    {
        return $this->getData('svea_order_id');
    }

    public function unsSveaOrderId()
    {
        return $this->unsetData('svea_order_id');
    }
}

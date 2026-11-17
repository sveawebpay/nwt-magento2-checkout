<?php

namespace Svea\Checkout\Model;

use Magento\Framework\Model\AbstractModel;
use Svea\Checkout\Api\Data\PushInterface;

/**
 * Svea Push model
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @since 100.0.2
 */

class Push extends AbstractModel implements PushInterface
{
    /**
     * Define resource model
     */

    public function _construct()
    {
        $this->_init('Svea\Checkout\Model\Resource\Push');
    }

    public function setId($id)
    {
        $this->setData(self::ENTITY_ID, $id);
        return $this;
    }

    public function setSid($sid)
    {
        $this->setData(self::SVEA_ORDER_ID, $sid);
        return $this;
    }

    public function setCreatedAt($createdAt)
    {
        $this->setData(self::CREATED_AT, $createdAt);
        return $this;
    }

    public function setOrderId($orderId)
    {
        $this->setData(self::MAGENTO_ORDER_ID, $orderId);
        return $this;
    }

    public function getId()
    {
        return $this->getData(self::ENTITY_ID);
    }

    public function getOrderId()
    {
        return $this->getData(self::MAGENTO_ORDER_ID);
    }

    public function getSid()
    {
        return $this->getData(self::SVEA_ORDER_ID);
    }

    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    public function getAge()
    {
        $now  = time();
        $rup  = strtotime($this->getCreatedAt());
        $age  = round(($now-$rup)/60, 2); //minutes
        return $age;
    }
}

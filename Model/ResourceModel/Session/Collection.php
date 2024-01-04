<?php declare(strict_types=1);

namespace Svea\Checkout\Model\ResourceModel\Session;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'entity_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Svea\Checkout\Model\Session::class,
            \Svea\Checkout\Model\ResourceModel\Session::class
        );
    }
}

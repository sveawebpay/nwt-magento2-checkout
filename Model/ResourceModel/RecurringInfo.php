<?php declare(strict_types=1);

namespace Svea\Checkout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class RecurringInfo extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('svea_recurring_info', 'entity_id');
    }
}

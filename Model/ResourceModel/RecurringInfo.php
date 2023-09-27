<?php declare(strict_types=1);

namespace Svea\Checkout\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * @method self setRecurringToken(string $recurringToken)
 * @method string getRecurringToken()
 * @method self setFrequencyOption(string $recurringFrequency)
 * @method string getFrequencyOption()
 * @method self setOriginalOrderId(int $orderId)
 * @method int getRecurringOrderId()
 * @method self setLatestOrderId(int $orderId)
 * @method int getLatestOrderId()
 * @method self setNextOrderDate(string $nextOrderDate)
 * @method string|null getNextOrderDate()
 * @method self setCanceledDate(string $canceledDate)
 * @method string|null getCanceledDate()
 */
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

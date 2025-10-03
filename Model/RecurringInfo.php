<?php declare(strict_types=1);

namespace Svea\Checkout\Model;

use Magento\Framework\Model\AbstractModel;
use Svea\Checkout\Api\Data\RecurringInfoInterface;

/**
 * @method self setRecurringToken(string $recurringToken)
 * @method string getRecurringToken()
 * @method self setOriginalOrderId(int $orderId)
 * @method int getOriginalOrderId()
 * @method self setFrequencyOption(string $recurringFrequency)
 * @method string getFrequencyOption()
 * @method self setNextOrderDate(string $nextOrderDate)
 * @method string|null getNextOrderDate()
 * @method self setCanceledDate(string $canceledDate)
 * @method string|null getCanceledDate()
 * @method self setFailedPayAttempts(int $attempts)
 * @method int getFailedPayAttempts()
 */
class RecurringInfo extends AbstractModel implements RecurringInfoInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel\RecurringInfo::class);
    }
}

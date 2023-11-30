<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Data;

use Magento\Framework\DataObject;

/**
 * Recurring info Data Model. Stored in Quote and Order Payment Additional Info as JSON.
 *
 * @method void setEnabled()
 * @method bool getEnabled()
 * @method void setRecurringToken(string $recurringToken)
 * @method string getRecurringToken()
 * @method void setFrequencyOption(string $recurringFrequency)
 * @method string getFrequencyOption()
 * @method void setRecurringOrderId(string $recurringOrderId)
 * @method string getRecurringOrderId()
 * @method void setStandardOrderId(string $standardOrderId)
 * @method string getStandardOrderId()
 * @method void setRecurringClientOrderId(string $recurringClientOrderId)
 * @method string getRecurringClientOrderId()
 * @method void setStandardClientOrderId()
 * @method string getStandardClientOrderId()
 * @method void setNextOrderDate(string $nextOrderDate)
 * @method string getNextOrderDate()
 * @method void setCanceledDate(string $canceledDate)
 * @method string|null getCanceledDate()
 */
class PaymentRecurringInfo extends DataObject
{
}

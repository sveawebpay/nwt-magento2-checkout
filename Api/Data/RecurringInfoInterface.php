<?php declare(strict_types=1);

namespace Svea\Checkout\Api\Data;

/**
 * @method int getId()
 * @method self setId(int $id)
 * @method self setRecurringToken(string $recurringToken)
 * @method string getRecurringToken()
 * @method self setFrequencyOption(string $recurringFrequency)
 * @method string getFrequencyOption()
 * @method self setOriginalOrderId(int $orderId)
 * @method int getOriginalOrderId()
 * @method self setFrequencyOption(string $frequencyOption)
 * @method string getFrequencyOption()
 * @method self setNextOrderDate(string $nextOrderDate)
 * @method string|null getNextOrderDate()
 * @method self setCanceledDate(string $canceledDate)
 * @method string|null getCanceledDate()
 */
interface RecurringInfoInterface
{
}

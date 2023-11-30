<?php declare(strict_types=1);

namespace Svea\Checkout\Api;

use Magento\Framework\Model\AbstractModel;

interface RecurringInfoRepositoryInterface
{
    /**
     * @param Data\RecurringInfoInterface|AbstractModel $recurringInfo
     * @return void
     * @throws \Exception
     */
    public function save(Data\RecurringInfoInterface $recurringInfo);

    /**
     * @param integer $orderId
     * @return Data\RecurringInfoInterface
     */
    public function getByOriginalOrderId(int $orderId) : Data\RecurringInfoInterface;

    /**
     * @param string $recurringToken
     * @return Data\RecurringInfoInterface
     */
    public function getByRecurringToken(string $recurringToken) : Data\RecurringInfoInterface;

    /**
     * Gets recurring orders scheduled for today.
     * Optionally also filter by storeId.
     *
     * @param int|null $storeId
     * @return Data\RecurringInfoInterface[]
     */
    public function getByTodaysDate(?int $storeId = null): array;
}

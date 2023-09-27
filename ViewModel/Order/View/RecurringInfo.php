<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Order\View;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\App\Request\Http;
use Svea\Checkout\Service\FrequencyOptionDisplay;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;

class RecurringInfo implements ArgumentInterface
{
    use FrequencyOptionDisplay;

    const CANCEL_RECURRING_PATH = 'sveacheckout/recurring/cancel';

    private Http $request;

    private SveaRecurringInfo $recurringInfoService;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private ?Order $currentOrder = null;

    private ?Order $originalOrder = null;

    private ?ModelRecurringInfo $recurringInfo = null;

    public function __construct(
        Http $request,
        SveaRecurringInfo $recurringInfoService,
        RecurringInfoRepositoryInterface $recurringInfoRepo
    ) {
        $this->request = $request;
        $this->recurringInfoService = $recurringInfoService;
        $this->recurringInfoRepo = $recurringInfoRepo;
    }

    /**
     * @param Order $order
     * @return boolean
     */
    public function orderHasRecurringInfo(): bool
    {
        $order = $this->getCurrentOrder();
        $recurringInfo = $this->recurringInfoService->orderGetter($order);
        return !!$recurringInfo->getEnabled();
    }

    /**
     * @return Order|null
     */
    public function getCurrentOrder(): ?Order
    {
        if (null === $this->currentOrder) {
            $orderId = $this->request->getParam('order_id');
            $this->currentOrder = $this->recurringInfoService->loadOrder((int)$orderId);
        }

        return $this->currentOrder;
    }

    /**
     * Load and get the subscription's original order (may be the same as the current order)
     *
     * @return Order
     */
    public function getOriginalOrder(): Order
    {
        if (null === $this->originalOrder) {
            $recurringInfo = $this->getRecurringInfo();
            $originalOrderId = (int)$recurringInfo->getOriginalOrderId();
            $this->originalOrder = $this->recurringInfoService->loadOrder($originalOrderId);
        }

        return $this->originalOrder;
    }

    /**
     * @return ModelRecurringInfo
     */
    public function getRecurringInfo(): ModelRecurringInfo
    {
        if (null === $this->recurringInfo) {
            $order = $this->getCurrentOrder();
            $paymentRecurringInfo = $this->recurringInfoService->orderGetter($order);
            $token = $paymentRecurringInfo->getRecurringToken();
            $this->recurringInfo = $this->recurringInfoRepo->getByRecurringToken($token);
        }
        return $this->recurringInfo;
    }

    public function getCancelRecurringPath(): string
    {
        return self::CANCEL_RECURRING_PATH;
    }
}

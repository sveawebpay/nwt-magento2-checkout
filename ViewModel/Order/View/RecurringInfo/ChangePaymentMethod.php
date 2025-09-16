<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Order\View\RecurringInfo;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Svea\Checkout\Service\SveaRecurringInfo;

class ChangePaymentMethod implements ArgumentInterface
{
    private SveaRecurringInfo $recurringInfoService;

    public function __construct(
        SveaRecurringInfo $recurringInfoService
    ) {
        $this->recurringInfoService = $recurringInfoService;
    }

    /**
     * @return string
     */
    public function getSnippet(): string
    {
        return (string)$this->recurringInfoService->getStoredChangePaymentSnippet();
    }
}

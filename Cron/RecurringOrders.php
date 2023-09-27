<?php declare(strict_types=1);

namespace Svea\Checkout\Cron;

use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Service\SveaRecurringInfo;
use Magento\Framework\App\State;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\App\Area;

class RecurringOrders
{
    private SveaRecurringInfo $sveaRecurringInfo;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private State $appState;

    private Emulation $emulation;

    public function __construct(
        SveaRecurringInfo $sveaRecurringInfo,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        State $appState,
        Emulation $emulation
    ) {
        $this->sveaRecurringInfo = $sveaRecurringInfo;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->appState = $appState;
        $this->emulation = $emulation;
    }

    /**
     * Places recurring orders for today
     *
     * @return void
     */
    public function placeOrders(): void
    {
        $recurringInfos = $this->recurringInfoRepo->getByTodaysDate();
        $this->appState->setAreaCode(Area::AREA_FRONTEND);
        foreach ($recurringInfos as $recurringInfo) {
            $originalOrder = $this->sveaRecurringInfo->loadOrder((int)$recurringInfo->getOriginalOrderId());
            $this->emulation->startEnvironmentEmulation($originalOrder->getStoreId(), Area::AREA_FRONTEND);
            $this->sveaRecurringInfo->placeRecurringOrder($recurringInfo);
            $this->recurringInfoRepo->save($recurringInfo);
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}

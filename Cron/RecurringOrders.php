<?php declare(strict_types=1);

namespace Svea\Checkout\Cron;

use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Service\SveaRecurringInfo\PlaceOrders;
use Svea\Checkout\Helper\Data;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\App\Area;

class RecurringOrders
{
    private StoreManagerInterface $storeManager;

    private Data $helper;

    private PlaceOrders $placeOrder;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private State $appState;

    private Emulation $emulation;

    public function __construct(
        StoreManagerInterface $storeManager,
        Data $helper,
        PlaceOrders $placeOrder,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        State $appState,
        Emulation $emulation
    ) {
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->placeOrder = $placeOrder;
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
        $this->appState->setAreaCode(Area::AREA_FRONTEND);
        $stores = $this->storeManager->getStores();

        foreach ($stores as $store) {
            $storeId = (int)$store->getId();

            if (!$this->helper->getRecurringPaymentsActive($storeId)) {
                continue;
            }

            $recurringInfos = $this->recurringInfoRepo->getByTodaysDate($storeId);
            if (count($recurringInfos) < 1) {
                continue;
            }

            // Start store emulation, then place orders for that store
            $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
            $this->placeOrder->placeRecurringOrders($recurringInfos);
            foreach ($recurringInfos as $recurringInfo) {
                $this->recurringInfoRepo->save($recurringInfo);
            }
            // End store emulation
            $this->emulation->stopEnvironmentEmulation();
        }
    }
}

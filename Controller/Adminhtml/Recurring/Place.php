<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action;
use Magento\Framework\App\Area;
use Magento\Store\Model\StoreManagerInterface;
use Svea\Checkout\Service\SveaRecurringInfo\PlaceOrders;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Helper\Data;
use Magento\Store\Model\App\Emulation;

/**
 * Places subscription orders for today
 */
class Place extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::place_recurring';

    private StoreManagerInterface $storeManager;

    private Data $helper;

    private PlaceOrders $placeOrders;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private Emulation $emulation;

    public function __construct(
        Action\Context $context,
        StoreManagerInterface $storeManager,
        Data $helper,
        PlaceOrders $placeOrders,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        Emulation $emulation
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->helper = $helper;
        $this->placeOrders = $placeOrders;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->emulation = $emulation;
    }

    public function execute()
    {
        $stores = $this->storeManager->getStores();
        $results = [];
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
            $this->placeOrders->placeRecurringOrders($recurringInfos);
            foreach ($recurringInfos as $recurringInfo) {
                $this->recurringInfoRepo->save($recurringInfo);
                $results[] = $this->placeOrders->fetchResult($recurringInfo->getRecurringToken());
            }
            // End store emulation
            $this->emulation->stopEnvironmentEmulation();
        }

        // Summarize results
        $errors = 0;
        $successes = 0;
        foreach ($results as $result) {
            if ($result['success'] === false) {
                $errors++;
                continue;
            }

            $successes++;
        }

        if ($errors + $successes === 0) {
            $this->messageManager->addNoticeMessage(
                __('Found no recurring orders to place.')
            );

            return $this->redirectToDashboard();
        }

        if ($errors > 0) {
            $this->messageManager->addErrorMessage(
                __('%1 recurring orders resulted in errors. Check the error log.', $errors)
            );
        }

        if ($successes > 0) {
            $this->messageManager->addSuccessMessage(
                __('%1 recurring orders were placed.', $successes)
            );
        }

        return $this->redirectToDashboard();
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface
     */
    private function redirectToDashboard(): \Magento\Framework\App\ResponseInterface
    {
        return $this->_redirect($this->getUrl('admin/dashboard'));
    }
}

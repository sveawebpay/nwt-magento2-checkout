<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Adminhtml\Recurring;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Redirect;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Service\SveaRecurringInfo;

/**
 * Cancel recurring payment Controller for Admins
 */
class Cancel extends Action
{
    const ADMIN_RESOURCE = 'Svea_Checkout::cancel_recurring';

    private SveaRecurringInfo $recurringInfoService;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    public function __construct(
        Action\Context $context,
        SveaRecurringInfo $recurringInfoService,
        RecurringInfoRepositoryInterface $recurringInfoRepo
    ) {
        parent::__construct($context);
        $this->recurringInfoService = $recurringInfoService;
        $this->recurringInfoRepo = $recurringInfoRepo;
    }

    public function execute()
    {
        $token = $this->getRequest()->getParam('token');
        $orderId = $this->getRequest()->getParam('order_id');

        try {
            $recurringInfo = $this->recurringInfoRepo->getByRecurringToken($token);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Recurring info not found'));
            return $this->redirectBackToOrderView();
        }

        try {
            $this->recurringInfoService->cancel($recurringInfo);
            $this->recurringInfoRepo->save($recurringInfo);
        } catch (\Exception $e) {
            $message = 'An error occurred when trying to cancel this recurring payment.';
            $this->messageManager->addErrorMessage(__($message));
            return $this->redirectBackToOrderView();
        }

        $this->messageManager->addSuccessMessage(__('Recurring payment has been cancelled.'));
        return $this->redirectBackToOrderView();
    }

    /**
     * @return Redirect
     */
    private function redirectBackToOrderView(): Redirect
    {
        return $this->resultRedirectFactory->create()->setPath(
            'sales/order/view',
            ['order_id' => $this->getRequest()->getParam('order_id')]
        );
    }
}

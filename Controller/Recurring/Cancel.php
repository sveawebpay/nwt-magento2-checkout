<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Recurring;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\RecurringInfo;

/**
 * Cancel recurring payment Controller for Customers
 */
class Cancel implements HttpPostActionInterface
{
    private Http $request;

    private OrderViewAuthorizationInterface $orderAuthorization;

    private MessageManager $messageManager;

    private SveaRecurringInfo $recurringInfoService;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private RedirectFactory $redirectFactory;

    public function __construct(
        Http $request,
        OrderViewAuthorizationInterface $orderAuthorization,
        MessageManager $messageManager,
        SveaRecurringInfo $recurringInfoService,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        RedirectFactory $redirectFactory
    ) {
        $this->request = $request;
        $this->orderAuthorization = $orderAuthorization;
        $this->messageManager = $messageManager;
        $this->recurringInfoService = $recurringInfoService;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->redirectFactory = $redirectFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $token = $this->request->getParam('token');
        $orderId = $this->request->getParam('order_id');
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->recurringInfoService->loadOrder((int)$orderId);
            $recurringInfo = $this->recurringInfoRepo->getByRecurringToken($token);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Recurring Info not found'));
            return $resultRedirect->setPath('sales/order/history');
        }

        if (!$this->orderAuthorization->canView($order)) {
            $this->messageManager->addErrorMessage(__('You don\'t have permission do this'));
            return $resultRedirect->setPath('sales/order/history');
        }

        try {
            $this->recurringInfoService->cancel($recurringInfo);
            $this->recurringInfoRepo->save($recurringInfo);
        } catch (\Exception $e) {
            $message =
                'An error occurred when trying to cancel your subscription.'
                . ' Please contact customer service for assistance.';

                $this->messageManager->addErrorMessage(__($message));
                return $this->redirectBackToOrderView();
        }

        $this->messageManager->addSuccessMessage(__('Your subscription is now cancelled'));
        return $this->redirectBackToOrderView();
    }

    /**
     * @return Redirect
     */
    private function redirectBackToOrderView(): Redirect
    {
        return $this->redirectFactory->create()->setPath(
            'sales/order/view',
            ['order_id' => $this->request->getParam('order_id')]
        );
    }
}

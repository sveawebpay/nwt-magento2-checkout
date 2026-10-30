<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Recurring;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Sales\Controller\AbstractController\OrderViewAuthorizationInterface;
use Magento\Csp\Helper\CspNonceProvider;
use Magento\Framework\View\Result\PageFactory;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Helper\Data;

/**
 * Change Payment Method for a Recurring Order
 */
class Changepaymentmethod implements HttpGetActionInterface
{
    private Http $request;

    private OrderViewAuthorizationInterface $orderAuthorization;

    private MessageManager $messageManager;

    private Data $config;

    private TokenClient $tokenClient;

    private SveaRecurringInfo $recurringInfoService;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private CspNonceProvider $cspNonceProvider;

    private RedirectFactory $redirectFactory;

    private PageFactory $resultPageFactory;

    public function __construct(
        Http $request,
        OrderViewAuthorizationInterface $orderAuthorization,
        MessageManager $messageManager,
        Data $config,
        TokenClient $tokenClient,
        SveaRecurringInfo $recurringInfoService,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        CspNonceProvider $cspNonceProvider,
        RedirectFactory $redirectFactory,
        PageFactory $resultPageFactory
    ) {
        $this->request = $request;
        $this->orderAuthorization = $orderAuthorization;
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->tokenClient = $tokenClient;
        $this->recurringInfoService = $recurringInfoService;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->cspNonceProvider = $cspNonceProvider;
        $this->redirectFactory = $redirectFactory;
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $token = (string)$this->request->getParam('token');
        $orderId = $this->request->getParam('order_id');
        $resultRedirect = $this->redirectFactory->create();

        if (!$token || !$orderId) {
            $this->messageManager->addErrorMessage(__('Invalid Request'));
            return $resultRedirect->setPath('sales/order/history');
        }

        try {
            $order = $this->recurringInfoService->loadOrder((int)$orderId);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Recurring Info not found'));
            return $resultRedirect->setPath('sales/order/history');
        }

        if (!$this->orderAuthorization->canView($order)) {
            $this->messageManager->addErrorMessage(__('You don\'t have permission do this'));
            return $resultRedirect->setPath('sales/order/history');
        }

        // Act on the subscription of the order the customer may view, never on one picked by the token alone
        $recurringInfo = $this->recurringInfoRepo->getByOriginalOrderId((int)$order->getEntityId());
        if (!hash_equals((string)$recurringInfo->getRecurringToken(), $token)) {
            $this->messageManager->addErrorMessage(__('Recurring Info not found'));
            return $resultRedirect->setPath('sales/order/history');
        }

        try {
            $termsUrl = $this->config->getTermsUrl();
            $snippet = $this->tokenClient->changePaymentMethod($recurringInfo->getRecurringToken(), $termsUrl);
            $this->recurringInfoService->setStoredChangePaymentSnippet($this->addNonce($snippet));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __('We couldn\'t fulfill this request. Please contact customer service')
            );
            return $resultRedirect->setPath('sales/order/history');
        }

        return $this->resultPageFactory->create();
    }

    /**
     * @param string $snippet
     * @return string
     */
    private function addNonce(string $snippet): string
    {
        $generatedNonce = $this->cspNonceProvider->generateNonce();
        $snippet = preg_replace_callback('/<script(.*?)>/si', function ($matches) use ($generatedNonce) {
            if (strpos($matches[1], 'nonce=') === false && strpos($matches[1], 'src=') === false) {
                return '<script' . $matches[1] . ' nonce="' . $generatedNonce . '">';
            }
            return $matches[0];
        }, $snippet);
        return $snippet;
    }
}

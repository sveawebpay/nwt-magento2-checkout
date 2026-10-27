<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Recurring;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RawFactory;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Service\SveaRecurringInfo;

/**
 * Push Controller used for Recurring Payment Orders
 */
class Push implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private Http $request;

    private TokenClient $tokenClient;

    private RawFactory $resultFactory;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private SveaRecurringInfo $recurringInfoService;

    public function __construct(
        Http $request,
        TokenClient $tokenClient,
        RawFactory $resultJsonFactory,
        ?RecurringInfoRepositoryInterface $recurringInfoRepo = null,
        ?SveaRecurringInfo $recurringInfoService = null
    ) {
        $this->request = $request;
        $this->tokenClient = $tokenClient;
        $this->resultFactory = $resultJsonFactory;
        $this->recurringInfoRepo = $recurringInfoRepo
            ?? ObjectManager::getInstance()->get(RecurringInfoRepositoryInterface::class);
        $this->recurringInfoService = $recurringInfoService
            ?? ObjectManager::getInstance()->get(SveaRecurringInfo::class);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $orderId = (int)$this->request->getParam('sid');
        $token = (string)$this->request->getParam('token');
        $result = $this->resultFactory->create();

        // The token goes into the path of a request signed with the merchant credentials, so it must be
        // one of our own subscriptions. The format check keeps path characters out before the lookup.
        if (!preg_match('/^[A-Za-z0-9-]+$/', $token)) {
            return $result->setHttpResponseCode(404);
        }
        $recurringInfo = $this->recurringInfoRepo->getByRecurringToken($token);
        if ($recurringInfo->getRecurringToken() !== $token) {
            return $result->setHttpResponseCode(404);
        }

        // Use the store of the subscription, not the storeid from the request
        $originalOrder = $this->recurringInfoService->loadOrder((int)$recurringInfo->getOriginalOrderId());
        $this->tokenClient->resetCredentials((int)$originalOrder->getStoreId());
        $this->tokenClient->getTokenOrder($orderId, $token);

        $result->setHttpResponseCode(200);
        return $result;
    }

    /**
     * Svea calls this server to server, without a form key. The request is
     * authenticated by the recurring token instead.
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

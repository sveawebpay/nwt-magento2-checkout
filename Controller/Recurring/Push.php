<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Recurring;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RawFactory;
use Svea\Checkout\Model\Client\Api\TokenClient;

/**
 * Push Controller used for Recurring Payment Orders
 */
class Push implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private Http $request;

    private TokenClient $tokenClient;

    private RawFactory $resultFactory;

    public function __construct(
        Http $request,
        TokenClient $tokenClient,
        RawFactory $resultJsonFactory
    ) {
        $this->request = $request;
        $this->tokenClient = $tokenClient;
        $this->resultFactory = $resultJsonFactory;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $orderId = (int)$this->request->getParam('sid');
        $token = (string)$this->request->getParam('token');
        $storeId = (int)$this->request->getParam('storeid');

        $this->tokenClient->resetCredentials($storeId);
        $tokenOrder = $this->tokenClient->getTokenOrder($orderId, $token);
        $result = $this->resultFactory->create();
        $result->setContents(
            sprintf('Validated Order %s, status %s', $tokenOrder->getOrderId(), $tokenOrder->getStatus())
        );
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

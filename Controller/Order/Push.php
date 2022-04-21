<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Svea\Checkout\Controller\Checkout;
use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\PushRepository;

class Push extends Checkout
{

    /**
     * @var $pushRepo PushRepository
     */
    protected $pushRepo;

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->jsonResultFactory->create();

        $orderId = $this->getRequest()->getParam('sid');
        $sveaHash = $this->getRequest()->getParam('hash');

        if ($this->checkoutSession->getOrderPushInProgress() || $this->checkMagentoOrderBySveaId($orderId)) {
            $result->setHttpResponseCode(200);
            return $result;
        }

        $this->pushRepo = $this->pushRepositoryFactory->create();

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        try {
            $this->checkoutSession->setOrderPushInProgress(true);
            $orderId = $this->tryToCreateOrder($orderId, $sveaHash);

            $result->setHttpResponseCode($orderId ? 200 : 400);
            $this->checkoutSession->setOrderPushInProgress(false);
            return $result;
        } catch (\Exception $e) {
            $this->checkoutSession->setOrderPushInProgress(false);
            $result->setHttpResponseCode(404);
            return $result;
        }
    }

    /**
     * @param $sveaOrderId
     *
     * @return bool
     */
    private function checkMagentoOrderBySveaId($sveaOrderId)
    {
        $orderCollection = $this->sveaCheckoutContext->getOrderCollectionFactory()->create();
        $ordersCount = $orderCollection
            ->addFieldToFilter('svea_order_id', ['eq' => $sveaOrderId])
            ->load()
            ->count();

        return $ordersCount > 0;
    }

    /**
     * @param $orderId
     * @param $sveaHash
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function tryToCreateOrder($orderId, $sveaHash)
    {

        try {
            $sveaOrder = $this->loadSveaOrder($orderId);
        } catch (\Exception $e) {
            throw $e;
        }

        // the push is created request in PushOrder
        try  {
            $push = $this->pushRepo->get($sveaOrder->getOrderId());
            $orderIdExists = $push->getOrderId() ? true : false;
        }catch (\Exception $e) {
            // ignore
            $this->getSveaCheckout()->getLogger()->error("Svea Push: Found no push.");

            return true;
        }

        // we are already done, stop it!
        if ($orderIdExists) {
            return $push->getOrderId();
        }

        // try to create order here, when status is final, and we haven't created an order!
        if ($sveaOrder->getStatus() === "Final" && !$orderIdExists) {
            try {
                $order = $this->createMagentoOrder($sveaOrder, $sveaHash);
            } catch (\Exception $e) {
                throw $e;
            }

            // we are almost done!
            // save order id to push that we are done!
            try {
                $push = $this->pushRepo->get($sveaOrder->getOrderId());
                $push->setOrderId($order->getId());
                $this->pushRepo->save($push);
            } catch (\Exception $e) {

            }

            return $order->getId();
        }

        return false;
    }

    /**
     * @param $sveaOrderId
     *
     * @return GetOrderResponse
     * @throws CheckoutException
     */
    protected function loadSveaOrder($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        try {
            $sveaOrder = $checkout->getSveaPaymentHandler()->loadSveaOrderById($sveaOrderId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Push: The svea order with ID: " . $sveaOrderId . " was not found in svea.");
                throw new CheckoutException(__("Found no Svea Order for this session. Please refresh the site or clear your cookies."));
            } else {
                $checkout->getLogger()->error("Push: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Push: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                throw new CheckoutException(__("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin."));
            }
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Push: Something went wrong. Might have been the request parser. Order ID: " . $sveaOrderId . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Something went wrong... Contact site admin."));
        }

        return $sveaOrder;
    }


    /**
     * @param GetOrderResponse $sveaOrder
     * @param $sveaHash
     * @return Order
     * @throws \Exception
     */
    protected function createMagentoOrder(GetOrderResponse $sveaOrder, $sveaHash)
    {
        try {
            // load quote if it exists
            $quote = $this->loadQuote($sveaOrder->getMerchantData()->getQuoteId());

            // compare the hashes, so no one tries to create an order without our permission
            if ($quote->getSveaHash() !== $sveaHash) {
                throw new \Exception("invalid svea hash, order might have been updated");
            }

        } catch (\Exception $e) {
            throw $e;
        }

        
        // here we create the magento order
        $quote->getPayment()->setAdditionalInformation('svea_payment_method', $sveaOrder->getPaymentType());
        try {
            // we try to create the order now ;)
            $order = $this->placeOrder($sveaOrder, $quote);
        } catch (\Exception $e) {
            throw $e;
        }
        
        return $order;
    }


    /**
     * @param GetOrderResponse $sveaOrder
     * @param Quote $quote
     * @return Order
     * @throws CheckoutException
     */
    protected function placeOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {
        try {
            /** @var $order Order */
            $order = $this->getSveaCheckout()->placeOrder($sveaOrder, $quote);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Push Order: Could not place order. Svea Order ID: " . $sveaOrder->getOrderId() . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Could not place the order."));
        }

        return $order;
    }

    /**
     * @param $quoteId
     * @return Quote
     */
    protected function loadQuoteById($quoteId)
    {
        return $this->quoteFactory->create()->loadByIdWithoutStore($quoteId);
    }


    /**
     * @param $quoteId int
     * @return Quote|void
     * @throws CheckoutException
     */
    protected function loadQuote($quoteId)
    {
        try {
            $quote = $this->loadQuoteById($quoteId);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Push Order: We found no quote for this Svea order.");
            throw new CheckoutException(__("Found no quote object for this Svea order ID."));
        }

        return $quote;
    }

    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function loadOrder($orderId)
    {
        return $this->sveaCheckoutContext->getOrderRepository()->get($orderId);
    }

    /**
     * Only works for >= 2.3.0
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
    */
}

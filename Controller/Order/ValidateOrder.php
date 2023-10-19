<?php

namespace Svea\Checkout\Controller\Order;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Svea\Checkout\Model\CheckoutException;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;

class ValidateOrder extends Update
{
    public function execute()
    {
        $sveaOrderId = $this->getRequest()->getParam('sid');
        $sveaHash = $this->getRequest()->getParam('hash'); // for security!

        $checkout = $this->getSveaCheckout();
        $checkout->setCheckoutContext($this->sveaCheckoutContext);

        $result = $this->jsonResultFactory->create();

        // one step at the time, all exceptions will be caught
        try {
            // check if svea order id is correct
            $this->validateSveaOrderId($sveaOrderId);

            // load svea order if it exists
            $sveaOrder = $this->loadSveaOrder($sveaOrderId);
        } catch (CheckoutException $e) {
            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => $e->getMessage(), 'Valid' => false]);
            return $result;
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order Error: " . $e->getMessage());

            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => "Could not load svea order", 'Valid' => false]);
            return $result;
        }

        $this->getSveaCheckout()->getLogger()->info(sprintf("Validate Order: Loaded Svea order. ID: %d Status: %s Payment Type: %s ", $sveaOrder->getOrderId(), $sveaOrder->getStatus(), $sveaOrder->getPaymentType()));

        // check if there is a push! we will save the mapping in database, svea order id and magento order id
        $pushRepo = $this->pushRepositoryFactory->create();
        $push = null;
        try {
            $push = $pushRepo->get($sveaOrderId);
            if ($push->getOrderId()) {
                try {
                    $order = $this->loadOrder($push->getOrderId());
                    if ($order->getIncrementId()) {
                        $checkout->getLogger()->error(sprintf("Validate Order: A push already exists for svea order id (%s). Respond with Valid=true and ClientOrderNumber=%s", $sveaOrderId, $order->getIncrementId()));
                        return $result->setData(['Valid' => true, 'ClientOrderNumber' => $order->getIncrementId()]);
                    }
                } catch (\Exception $e2) {
                    // do nothing
                }
            }
        } catch (NoSuchEntityException $e) {
            // ignore we will create a new push entity below after validation!
        }

        try {
            // load quote if it exists
            $quote = $this->loadQuote($sveaOrder->getMerchantData()->getQuoteId());

            // compare the hashes, so no one tries to create an order without our permission
            if ($quote->getSveaHash() !== $sveaHash) {
                $checkout->getLogger()->error(sprintf("Validate Order: The quote hash (%s) does not match the request hash (%s).", $quote->getSveaHash(), $sveaHash));
                throw new CheckoutException(__("Invalid Quote hash"));
            }

            // check if everything is valid
            $this->validateOrder($sveaOrder, $quote);
        } catch (CheckoutException $e) {
            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => $e->getMessage(), 'Valid' => false]);
            return $result;
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order Error: " . $e->getMessage());

            $result->setHttpResponseCode(400);
            $result->setData(['errorMessage' => "Could not place order", 'Valid' => false]);
            return $result;
        }

        // we save the push now after the validation!
        if ($push === null) {
            try {
                $pushRepo->save($this->createNewPushObject($sveaOrderId));
            } catch (CouldNotSaveException $e2) {
                $checkout->getLogger()->error("Validate Order Error, save Push: " . $e2->getMessage());

                $result->setHttpResponseCode(400);
                $result->setData(['errorMessage' => _("Could not place order. It might already been saved."), 'Valid' => false]);
                return $result;
            }
        }

        // we log this as well!
        $responseData = ['Valid' => true, 'ClientOrderNumber' => $quote->getData('svea_client_order_id')];
        $checkout->getLogger()->info("Validate Order: Successfully created order and push:");
        $checkout->getLogger()->info(json_encode($responseData));
        $result->setHttpResponseCode(200);

        return $result->setData($responseData);
    }

    /**
     * @param $sveaOrderId
     * @throws CheckoutException
     */
    protected function validateSveaOrderId($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        if (!$sveaOrderId) {
            $checkout->getLogger()->error("Validate Order: Found no svea order ID.");
            throw new CheckoutException(__("Found no svea order id."));
        }

        if (!is_numeric($sveaOrderId)) {
            $checkout->getLogger()->error("Validate Order: The Svea Order ID is invalid!");
            throw new CheckoutException(__("The Svea Order ID is invalid."));
        }
    }

    /**
     * @param $sveaOrderId
     * @return \Svea\Checkout\Model\Client\DTO\GetOrderResponse|void
     * @throws CheckoutException
     */
    public function loadSveaOrder($sveaOrderId)
    {
        $checkout = $this->getSveaCheckout();
        $storeId = $this->getRequest()->getParam('store_id');

        try {
            $sveaOrder = $checkout->getSveaPaymentHandler()->loadSveaOrderById($sveaOrderId, false, $storeId);
        } catch (ClientException $e) {
            if ($e->getHttpStatusCode() == 404) {
                $checkout->getLogger()->error("Validate Order: The svea order with ID: " . $sveaOrderId . " was not found in svea.");
                throw new CheckoutException(__("Found no Svea Order for this session. Please refresh the site or clear your cookies."));
            } else {
                $checkout->getLogger()->error("Validate Order: Something went wrong when we tried to fetch the order ID from Svea. Http Status code: " . $e->getHttpStatusCode());
                $checkout->getLogger()->error("Validate Order: Error message:" . $e->getMessage());
                $checkout->getLogger()->debug($e->getResponseBody());

                throw new CheckoutException(__("Something went wrong when we tried to retrieve the order from Svea. Please try again or contact an admin."));
            }
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order: Something went wrong. Might have been the request parser. Order ID: " . $sveaOrderId . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Something went wrong... Contact site admin."));
        }

        return $sveaOrder;
    }

    /**
     * @param $quoteId int
     * @return Quote|void
     * @throws CheckoutException
     */
    public function loadQuote($quoteId)
    {
        try {
            $quote = $this->loadQuoteById($quoteId);
        } catch (\Exception $e) {
            $this->getSveaCheckout()->getLogger()->error("Validate Order: We found no quote for this Svea order.");
            throw new CheckoutException(__("Found no quote object for this Svea order ID."));
        }

        return $quote;
    }

    /**
     * @param $sveaOrder GetOrderResponse
     * @param $quote Quote
     * @return void
     * @throws CheckoutException
     */
    public function validateOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {
        $checkout = $this->getSveaCheckout();

        if ($sveaOrder->getShippingAddress() === null) {
            $checkout->getLogger()->error("Validate Order: Consumer has no shipping address.");
            throw new CheckoutException(__("Please add shipping information."));
        }

        try {
            if (!$quote->isVirtual() && !$quote->getShippingAddress()->getShippingMethod()) {
                $checkout->getLogger()->error("Validate Order: Consumer has not chosen a shipping method.");
                throw new CheckoutException(__("Please choose a shipping method."));
            }
        } catch (\Exception $e) {
            $checkout->getLogger()->error("Validate Order: Something went wrong... Order ID: " . $sveaOrder->getOrderId() . "... Error message:" . $e->getMessage());
            throw new CheckoutException(__("Something went wrong... Contact site admin."));
        }

        $sveaLastTotal = $sveaOrder->getMerchantData()->getTotal();
        if ((float) $quote->getGrandTotal() !== $sveaLastTotal) {
            $checkout->getLogger()->error("Validate Order: Totals not matching. Expected ". $sveaLastTotal. ", has ". $quote->getGrandTotal() ." Svea Order ID: " . $sveaOrder->getOrderId());
            throw new CheckoutException(__("Totals not matching."));
        }
    }

    /**
     * @param $quoteId
     * @return Quote
     */
    public function loadQuoteById($quoteId)
    {
        return $this->quoteFactory->create()->loadByIdWithoutStore($quoteId);
    }

    /**
     * @param $sveaOrderId
     * @return \Svea\Checkout\Api\Data\PushInterface
     */
    public function createNewPushObject($sveaOrderId)
    {
        $currentTime = null;
        try {
            $currentTime =  (new \DateTime())->format(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT);
        } catch (\Exception $e) {
            // do nothing
        }

        $push = $this->pushInterfaceFactory->create();
        $push->setSid($sveaOrderId);
        $push->setCreatedAt($currentTime);
        return $push;
    }

    /**
     * @param $orderId
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    protected function loadOrder($orderId)
    {
        return $this->sveaCheckoutContext->getOrderRepository()->get($orderId);
    }
}

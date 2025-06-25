<?php


namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\CancelOrderAmount;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\CreatePaymentChargeResponse;
use Svea\Checkout\Model\Client\DTO\CreditRows;
use Svea\Checkout\Model\Client\DTO\GetOrderInfoResponse;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\RefundNewCreditRow;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\DTO\RefundPaymentAmount;
use Svea\Checkout\Model\Client\OrderManagementClient;

class OrderManagement extends OrderManagementClient
{

    /**
     * @param $paymentId
     * @return GetOrderInfoResponse
     * @throws ClientException
     */
    public function getOrder($paymentId)
    {
        try {
            $response = $this->get("/api/v1/orders/" . $paymentId);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return new GetOrderInfoResponse($response);
    }

    /**
     * Used before a delivery is made, if needed.
     *
     * @param OrderRow $row
     * @param $paymentId
     * @throws ClientException
     * @return int
     */
    public function addOrderRow(OrderRow $row, $paymentId)
    {
        try {
            $response = $this->post("/api/v1/orders/" . $paymentId . "/rows", $row);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        $data = json_encode($response, true);
        if (is_array($data) && isset($data['OrderRowId'])) {
            return $data['OrderRowId'];
        } else {
            throw new \Exception("Row ID not returned. Something went wrong.");
        }
    }

    /**
     * Used before a delivery is made, if needed.

     *
     * @param OrderRow $row
     * @param $paymentId
     * @param $rowNr
     * @throws ClientException
     */
    public function updateOrderRow(OrderRow $row, $paymentId, $rowNr)
    {
        try {
            $this->patch("/api/v1/orders/" . $paymentId . "/rows/" . $rowNr, $row);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }


    /**
     * @param CancelOrder $payment
     * @param string $paymentId
     * @throws ClientException
     * @return void
     */
    public function cancelOrder(CancelOrder $payment, $paymentId)
    {
        try {
            $this->patch("/api/v1/orders/" . $paymentId, $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param CancelOrderAmount $payment
     * @param string $paymentId
     * @throws ClientException
     * @return void
     */
    public function cancelOrderAmount(CancelOrderAmount $payment, $paymentId)
    {
        try {
            $this->patch("/api/v1/orders/" . $paymentId, $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * Cancel rows for given order ID and row IDs
     *
     * @param int $orderId
     * @param int[] $orderRowIds
     * @return void
     */
    public function cancelOrderRows($orderId, array $orderRowIds): void
    {
        $request = $this->apiContext->getGenericRequestFactory()->create();
        $request->setData(['OrderRowIds' => $orderRowIds]);
        $this->patch(sprintf('/api/v1/orders/%s/rows/cancelOrderRows', $orderId), $request);
    }

    /**
     * Cancel row for given order ID and row ID
     *
     * @param int $orderId
     * @param int $rowId
     * @return void
     */
    public function cancelOrderRow($orderId, $rowId): void
    {
        $request = $this->apiContext->getGenericRequestFactory()->create();
        $request->setData(['IsCancelled' => true]);
        $this->patch(sprintf('/api/v1/orders/%s/rows/%s', $orderId, $rowId), $request);
    }


    /**
     * @param DeliverOrder $payment
     * @param string $orderId
     * @throws ClientException
     * @return CreatePaymentChargeResponse
     */
    public function deliverOrder(DeliverOrder $payment, $orderId)
    {
        try {
           $this->post("/api/v1/orders/" . $orderId . "/deliveries", $payment);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        try {
            $location = $this->getLastResponse()->getHeader("Location")[0];
        } catch (\Exception $exception) {
            $location = "";
        }

        return new CreatePaymentChargeResponse($location);
    }


    /**
     * @param RefundPayment $creditRow
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return void
     */
    public function refundPayment(RefundPayment $creditRow, $orderId, $deliveryId)
    {
        try {
            $this->post("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId . "/credits", $creditRow);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param RefundNewCreditRow $creditRow
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return void
     */
    public function refundNewCreditRow(RefundNewCreditRow $creditRow, $orderId, $deliveryId)
    {
        try {
            $this->post("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId . "/credits", $creditRow);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }


    /**
     * @param RefundPaymentAmount $creditAmount
     * @param string $orderId
     * @param string $deliveryId
     * @throws ClientException
     * @return void
     */
    public function refundPaymentAmount(RefundPaymentAmount $creditAmount, $orderId, $deliveryId)
    {
        try {
            $this->patch("/api/v1/orders/" . $orderId . "/deliveries/" . $deliveryId, $creditAmount);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }
    }

    /**
     * @param string $orderId
     * @param string $deliveryId
     * @return void
     */
    public function refundRows(string $orderId, string $deliveryId, array $rowCreditingOptions): void
    {
        $request = new CreditRows();
        foreach ($rowCreditingOptions as $rowCreditingOption) {
            $request->addRowCreditingOption((int)$rowCreditingOption['orderRowId'], floatval($rowCreditingOption['quantity']));
        }
        $this->post(
            sprintf(
                'api/v1/orders/%s/deliveries/%s/credits/credits',
                $orderId,
                $deliveryId
            ),
            $request
        );
    }

    /**
     * @param $taskId
     * @return array
     * @throws ClientException
     */
    public function getTask($taskId)
    {
        try {
            $response = $this->get("/api/v1/queue/" . $taskId);
        } catch (ClientException $e) {
            // handle?
            throw $e;
        }

        return json_decode($response, true);
    }
}
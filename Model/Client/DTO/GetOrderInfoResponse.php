<?php
namespace Svea\Checkout\Model\Client\DTO;

use Svea\Checkout\Model\Client\DTO\Order\Address;
use Svea\Checkout\Model\Client\DTO\Order\Customer;
use Svea\Checkout\Model\Client\DTO\Order\GetOrder;
use Svea\Checkout\Model\Client\DTO\Order\Gui;
use Svea\Checkout\Model\Client\DTO\Order\IdentityFlags;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Helper\Data as SveaHelper;

class GetOrderInfoResponse
{

    /**
     * The order is open and active. This includes partially delivered orders
     */
    const ORDER_STATUS_OPEN = "Open";

    /**
     * The order is fully delivered
     */
    const ORDER_STATUS_DELIVERED = "Delivered";

    /**
     * The order is fully cancelled
     */
    const ORDER_STATUS_CANCELLED = "Cancelled";

    /**
     * The payment for this order has failed
     */
    const ORDER_STATUS_FAILED = "Failed";

    /**
     * The order does not have a set Payment Method
     */
    const ORDER_STATUS_PROCESSING = "Processing";

    const PAYMENT_TYPE_CARD = "Card";
    const PAYMENT_TYPE_INVOICE = "Invoice";
    const PAYMENT_TYPE_PAYMENT_PLAN = "PaymentPlan";
    const PAYMENT_TYPE_ACCOUNT_CREDIT = "AccountCredit";
    const PAYMENT_TYPE_DIRECT_BANK = "DirectBank";

    const ACTION_CAN_DELIVER_ORDER = "CanDeliverOrder";
    const ACTION_CAN_DELIVER_ORDER_PARTIALLY = "“CanDeliverOrderPartially”";
    const ACTION_CAN_CANCEL_ORDER = "CanCancelOrder";
    const ACTION_CAN_CANCEL_ORDER_AMOUNT = "CanCancelAmount";


    private $_data;

    /**
     * @var int $id
     */
    protected $id;

    /** @var int $orderAmount */
    protected $orderAmount;

    /** @var string $orderStatus */
    protected $orderStatus;

    /** @var string */
    protected $paymentCreditStatus;

    /** @var string */
    protected $paymentType;

    protected $actions = [];

    /** @var $deliveries GetDeliveryResponse[] */
    protected $deliveries = [];

    /** @var OrderRow[] $cartItems */
    protected $cartItems;


    /**
     * CreatePaymentResponse constructor.
     * @param $response string
     */
    public function __construct($response = "")
    {
        if ($response == null) {
            return;
        }

        if (is_string($response)) {
            $data = json_decode($response, true);
        } else {
            // we think its an array!
            $data = $response;
        }

        $this->_data = $data;


        $actions = $this->get("Actions");
        $actions = is_array($actions) ? $actions : [];

        $this->setId($this->get("Id"));
        $this->setOrderAmount($this->get("OrderAmount"));
        $this->setOrderStatus($this->get("OrderStatus"));
        $this->setPaymentType($this->get("PaymentType"));
        $this->setPaymentCreditStatus($this->get("PaymentCreditStatus"));
        $this->setActions($actions);

        $this->setCartItems([]);
        if (isset($data['OrderRows'])) {
            $items = $data['OrderRows'];
            $orderRows = [];
            foreach ($items as $item) {
                $orderRow = new OrderRow();

                // fill
                $orderRow
                    ->setArticleNumber($item['ArticleNumber'])
                    ->setName($item['Name'])
                    ->setQuantity($item['Quantity'])
                    ->setUnitPrice($item['UnitPrice'])
                    ->setUnit($item['Unit'])
                    ->setDiscountPercent($item['DiscountPercent'])
                    ->setVatPercent($item['VatPercent'])
                    ->setRowNumber($item['OrderRowId']);

                // add to array
                $orderRows[] = $orderRow;
            }

            $this->setCartItems($orderRows);
        }

        $resDeliveries = $this->get("Deliveries");
        $resDeliveries = is_array($resDeliveries) ? $resDeliveries : [];
        $deliveries = [];
        foreach ($resDeliveries as $delivery) {
            $deliveries[] = new GetDeliveryResponse($delivery);
        }

        $this->setDeliveries($deliveries);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return GetOrderInfoResponse
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrderAmount()
    {
        return $this->orderAmount;
    }

    /**
     * @param int $orderAmount
     * @return GetOrderInfoResponse
     */
    public function setOrderAmount($orderAmount)
    {
        $this->orderAmount = $orderAmount;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderStatus()
    {
        return $this->orderStatus;
    }

    /**
     * @param string $orderStatus
     * @return GetOrderInfoResponse
     */
    public function setOrderStatus($orderStatus)
    {
        $this->orderStatus = $orderStatus;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentCreditStatus()
    {
        return $this->paymentCreditStatus;
    }

    /**
     * @param string $paymentCreditStatus
     * @return GetOrderInfoResponse
     */
    public function setPaymentCreditStatus($paymentCreditStatus)
    {
        $this->paymentCreditStatus = $paymentCreditStatus;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentType()
    {
        return $this->paymentType;
    }

    /**
     * @param string $paymentType
     * @return GetOrderInfoResponse
     */
    public function setPaymentType($paymentType)
    {
        $this->paymentType = $paymentType;
        return $this;
    }

    /**
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     * @return GetOrderInfoResponse
     */
    public function setActions($actions)
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @return OrderRow[]
     */
    public function getCartItems()
    {
        return $this->cartItems;
    }

    /**
     * @param OrderRow[] $cartItems
     * @return GetOrderInfoResponse
     */
    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
        return $this;
    }

    public function canCancel()
    {
        return in_array(self::ACTION_CAN_CANCEL_ORDER, $this->getActions());
    }

    /**
     * @return bool
     */
    public function canCancelAmount()
    {
        return in_array(self::ACTION_CAN_CANCEL_ORDER_AMOUNT, $this->getActions());
    }


    public function canDeliver()
    {
        return in_array(self::ACTION_CAN_DELIVER_ORDER, $this->getActions());
    }

    public function canDeliverPartially()
    {
        return in_array(self::ACTION_CAN_DELIVER_ORDER_PARTIALLY, $this->getActions());
    }



    public function canRefund()
    {
        return $this->getFirstRefundableDelivery() !== null;
    }

    public function getFirstRefundableDelivery()
    {
        foreach ($this->getDeliveries() as $delivery) {
            if ($delivery->canRefund()) {
                return $delivery;
            }
        }

        return null;
    }

    public function getFirstDeliveredDelivery()
    {
        foreach ($this->getDeliveries() as $delivery) {
          //  if ($delivery->getS()) {
                return $delivery;
          //  }
        }

        return null;
    }


    /**
     * @return GetDeliveryResponse[]
     */
    public function getDeliveries()
    {
        return $this->deliveries;
    }

    /**
     * @param array $deliveries
     * @return GetOrderInfoResponse
     */
    public function setDeliveries($deliveries)
    {
        $this->deliveries = $deliveries;
        return $this;
    }

    /**
     * @return null|OrderRow
     */
    public function getInvoiceFeeRow()
    {
        foreach ($this->getCartItems() as $item) {
            if ($item->getArticleNumber() == SveaHelper::INVOICE_FEE_ARTICLE_NUMBER) {
                return $item;
            }
        }

        return null;
    }

    public function getOrderRowIds()
    {
        $ids = [];
        foreach ($this->getCartItems() as $item)
        {
            $ids[] = $item->getRowNumber();
        }

        return $ids;
    }

    private function get($key)
    {
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }

        return null;
    }

    /** @return mixed */
    public function getHttpResponse()
    {
        return $this->_data;
    }

}
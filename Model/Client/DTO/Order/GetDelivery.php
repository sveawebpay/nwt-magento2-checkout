<?php
namespace Svea\Checkout\Model\Client\DTO\Order;

use Svea\Checkout\Helper\Data as SveaHelper;

class GetDelivery
{

    const ACTION_CAN_REFUND_AMOUNT = "CanCreditAmount";
    const ACTION_CAN_REFUND_ROWS = "CanCreditOrderRows";


    const ACTION_CAN_CANCEL_ORDER = 'CanCancelOrder';
    const ACTION_CAN_CANCEL_ORDER_AMOUNT = 'CanCancelAmount';

    /** @var OrderRow[] $cartItems */
    protected $cartItems;

    protected $cartActions = [];

    /** @var int|null */
    protected $invoiceId;

    protected $actions = [];
    
    /** @var $id int */
    protected $id;

    /**
     * @var $deliveryAmount int
     */
    protected $deliveryAmount;

    /**
     * @return array
     */
    public function getActions()
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     * @return GetDelivery
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
        return is_array($this->cartItems) ? $this->cartItems : [];
    }

    /**
     * @return OrderRow[]
     */
    public function getCreditableItems()
    {
        $items = [];
        foreach ($this->getCartItems() as $item)
        {
            $actions = $this->getCartActionsByRow($item->getRowNumber());
            if (!in_array("CanCreditRow", $actions)) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    public function getCreditableRowsIds()
    {
        $items = $this->getCreditableItems();
        $rowsIds = [];
        foreach ($items as $item)
        {
            $rowsIds[] = $item->getRowNumber();
        }

        return $rowsIds;
    }

    /**
     * @param OrderRow[] $cartItems
     * @return GetDelivery
     */
    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
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

    /**
     * @param array $cartActions
     * @return GetDelivery
     */
    public function setCartActions($cartActions)
    {
        $this->cartActions = $cartActions;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * @param int|null $invoiceId
     * @return GetDelivery
     */
    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;
        return $this;
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
     * @return GetDelivery
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getDeliveryAmount()
    {
        return $this->deliveryAmount;
    }

    /**
     * @param int $deliveryAmount
     * @return GetDelivery
     */
    public function setDeliveryAmount($deliveryAmount)
    {
        $this->deliveryAmount = $deliveryAmount;
        return $this;
    }



    /**
     * @return bool
     */
    public function canRefund()
    {
        return $this->canRefundAmount() || $this->canRefundRows() || $this->canRefundAmount();
    }

    public function canRefundRows()
    {
        return in_array(self::ACTION_CAN_REFUND_ROWS, $this->getActions());
    }

    public function canRefundAmount()
    {
        return in_array(self::ACTION_CAN_REFUND_AMOUNT, $this->getActions());
    }

    /**
     * This should be used same way as canRefundAmount() but using another endpoint and request data...
     *
     * @return bool
     */
    public function canRefundNewRow()
    {
        return in_array("CanCreditNewRow", $this->getActions());
    }
    
    public function canDeliveryRefundByAmount()
    {
        return $this->canRefundAmount() || $this->canRefundNewRow();
    }



    /**
     * @return bool
     */
    public function canCancelAmount()
    {
        return in_array(self::ACTION_CAN_CANCEL_ORDER_AMOUNT, $this->getActions());
    }

    /**
     * @return string|null
     */
    public function getRefundType()
    {
        if ($this->canDeliveryRefundByAmount()) {
            return "amount";
        }

        if ($this->canRefundRows()) {
            return "rows";
        }
        return null;
    }

    /**
     * @return array
     */
    protected function getCartActions()
    {
        return $this->cartActions;
    }

    /**
     * @param $rowNr
     * @return array
     */
    protected function getCartActionsByRow($rowNr)
    {
        foreach ($this->getCartActions() as $nr => $actions)
        {
            if ($nr == $rowNr) {
                return $actions;
            }
        }

        return [];
    }

}
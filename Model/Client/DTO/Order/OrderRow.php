<?php
namespace Svea\Checkout\Model\Client\DTO\Order;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow\ShippingInformation;

class OrderRow extends AbstractRequest
{
    const ROW_TYPE_STANDARD = 'Row';
    const ROW_TYPE_SHIPPINGFEE = 'ShippingFee';
    const ACTION_CAN_CANCEL_ROW = 'CanCancelRow';

    /**
     * Articlenumber as a string, can contain letters and numbers. ,
     * @var $ArticleNumber string
     */
    protected $ArticleNumber;

    /**
     * @var $Name string
     */
    protected $Name;

    /**
     * Quantity of the product. 1-9 digits. ,
     * @var $Quantity int
     */
    protected $Quantity;

    /**
     * Price of the product including VAT. 1-13 digits, can be negative.
     * @var $UnitPrice int
     */
    protected $UnitPrice;

    /**
     *  Optional
     *
     * The discountpercent of the product.
     *   Examples:
     *   0-9900. No fractions.0 = 0%100 = 1%1000 = 10%9900 = 99% ,
     * @var $DiscountPercent int
     */
    protected $DiscountPercent;

    /**
     * Optional
     *
     * The discount amount of the product. ,
     * @var $DiscountAmount int
     */
    protected $DiscountAmount;

    /**
     *
     * The VAT percentage of the current product. Valid vat percentage for that country. ,
     * @var $VatPercent int
     */
    protected $VatPercent;

    /**
     * Optional
     *
     * The unit type, e.g., “st”, “pc”, “kg” etc. ,
     * @var $Unit string
     */
    protected $Unit;

    /**
     * Optional
     *
     * Can be used when creating or updating an order.
     * The returned rows will have their corresponding temporaryreference as they were given in the indata.
     * It will not be stored and will not be returned in GetOrder. ,
     * @var $TemporaryReference string
     */
    protected $TemporaryReference;

    /**
     * Optional
     *
     * The row number the row will have in the Webpay system ,
     * @var $RowNumber int
     */
    protected $RowNumber;

    /**
     * Optional
     *
     * Metadata visible to the store
     * @var $MerchantData string
     */
    protected $MerchantData;

    /**
     * Row Type - 'Row' or 'ShippingFee'
     *
     * @var string
     */
    protected $RowType = self::ROW_TYPE_STANDARD;

    /**
     * @var ShippingInformation
     */
    protected $ShippingInformation;

    /**
     * @var array
     */
    protected array $actions;

    /**
     * Used to determine if all items of row should be delivered or refunded
     * Not included in data sent in API call
     *
     * @var boolean
     */
    protected bool $fullDelivery = true;

    /**
     * @return string
     */
    public function getArticleNumber()
    {
        return $this->ArticleNumber;
    }

    /**
     * @param string $ArticleNumber
     * @return OrderRow
     */
    public function setArticleNumber($ArticleNumber)
    {
        $this->ArticleNumber = $ArticleNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->Name;
    }

    /**
     * @param string $Name
     * @return OrderRow
     */
    public function setName($Name)
    {
        $this->Name = $Name;
        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->Quantity;
    }

    /**
     * @param int $Quantity
     * @return OrderRow
     */
    public function setQuantity($Quantity)
    {
        $this->Quantity = $Quantity;
        return $this;
    }

    /**
     * @return int
     */
    public function getUnitPrice()
    {
        return $this->UnitPrice;
    }

    /**
     * @param int $UnitPrice
     * @return OrderRow
     */
    public function setUnitPrice($UnitPrice)
    {
        $this->UnitPrice = $UnitPrice;
        return $this;
    }

    /**
     * @return int
     */
    public function getDiscountPercent()
    {
        return $this->DiscountPercent;
    }

    /**
     * @param int $DiscountPercent
     * @return OrderRow
     */
    public function setDiscountPercent($DiscountPercent)
    {
        $this->DiscountPercent = $DiscountPercent;
        return $this;
    }

    /**
     * @return int
     */
    public function getDiscountAmount()
    {
        return $this->DiscountAmount;
    }

    /**
     * @param int $DiscountAmount
     * @return OrderRow
     */
    public function setDiscountAmount($DiscountAmount)
    {
        $this->DiscountAmount = $DiscountAmount;
        return $this;
    }

    /**
     * @return int
     */
    public function getVatPercent()
    {
        return $this->VatPercent;
    }

    /**
     * @param int $VatPercent
     * @return OrderRow
     */
    public function setVatPercent($VatPercent)
    {
        $this->VatPercent = $VatPercent;
        return $this;
    }

    /**
     * @return string
     */
    public function getUnit()
    {
        return $this->Unit;
    }

    /**
     * @param string $Unit
     * @return OrderRow
     */
    public function setUnit($Unit)
    {
        $this->Unit = $Unit;
        return $this;
    }

    /**
     * @return string
     */
    public function getTemporaryReference()
    {
        return $this->TemporaryReference;
    }

    /**
     * @param string $TemporaryReference
     * @return OrderRow
     */
    public function setTemporaryReference($TemporaryReference)
    {
        $this->TemporaryReference = $TemporaryReference;
        return $this;
    }

    /**
     * @return int
     */
    public function getRowNumber()
    {
        return $this->RowNumber;
    }

    /**
     * @param int $RowNumber
     * @return OrderRow
     */
    public function setRowNumber($RowNumber)
    {
        $this->RowNumber = $RowNumber;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantData()
    {
        return $this->MerchantData;
    }

    /**
     * @param string $MerchantData
     * @return OrderRow
     */
    public function setMerchantData($MerchantData)
    {
        $this->MerchantData = $MerchantData;
        return $this;
    }

    public function setRowTypeIsShippingFee()
    {
        $this->RowType = self::ROW_TYPE_SHIPPINGFEE;
        return $this;
    }

    public function getRowType()
    {
        return $this->RowType;
    }

    /**
     * @param bool $val
     * @return void
     */
    public function setFullDelivery(bool $val): void
    {
        $this->fullDelivery = $val;
    }

    /**
     * @return bool
     */
    public function getFullDelivery(): bool
    {
        return $this->fullDelivery;
    }

    /**
     * Get the value of ShippingInformation
     *
     * @return  ShippingInformation
     */
    public function getShippingInformation()
    {
        return $this->ShippingInformation;
    }

    /**
     * Set the value of ShippingInformation
     *
     * @param  ShippingInformation  $ShippingInformation
     *
     * @return  self
     */
    public function setShippingInformation(ShippingInformation $shippingInformation)
    {
        $this->ShippingInformation = $shippingInformation;
        return $this;
    }

    /**
     * @return array
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param array $actions
     * @return GetOrderInfoResponse
     */
    public function setActions($actions): self
    {
        $this->actions = $actions;
        return $this;
    }

    public function canCancelRow(): bool
    {
        return in_array(self::ACTION_CAN_CANCEL_ROW, $this->getActions());
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {

        $data = [
            'ArticleNumber' => $this->getArticleNumber(),
            'Name' => $this->getName(),
            'Quantity' => $this->getQuantity(),
            'UnitPrice' => $this->getUnitPrice(),
            'VatPercent' => $this->getVatPercent(),
            'RowType' => $this->getRowType(),
        ];

        if ($this->getUnit()) {
            $data['Unit'] = $this->getUnit();
        }

        if ($this->getDiscountPercent()) {
            $data['DiscountPercent'] = $this->getDiscountPercent();
        }

        if ($this->getDiscountAmount()) {
            $data['DiscountAmount'] = $this->getDiscountAmount();
        }

        if ($this->getTemporaryReference()) {
            $data['TemporaryReference'] = $this->getTemporaryReference();
        }

        if ($this->getRowNumber()) {
            $data['RowNumber'] = $this->getRowNumber();
        }

        if ($this->getMerchantData()) {
            $data['MerchantData'] = $this->getMerchantData();
        }

        if ($this->getShippingInformation()) {
            $data['ShippingInformation'] = $this->getShippingInformation()->toArray();
        }

        return $data;
    }
}
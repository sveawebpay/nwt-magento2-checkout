<?php
namespace Svea\Checkout\Model\Client\DTO\Order;

use Svea\Checkout\Model\Client\DTO\MerchantDataResponse;
use Svea\Checkout\Helper\Data as SveaHelper;

class GetOrder
{

    protected $validStatuses = [
        'Cancelled',
        'Created',
        'Final'
    ];

    /**
     * https://checkoutapi.svea.com/docs/html/reference/web-api/orders-api/createorder/api-orders-post.htm
     * The final payment method for the order. Will only have a value when the order is finalized, otherwise null.
     * @var $PaymentType string
     */
    protected $PaymentType;

    /** @var $OrderId int */
    protected $OrderId;

    /** @var $EmailAddress string */
    protected $EmailAddress;

    /** @var $PhoneNumber string */
    protected $PhoneNumber;

    /** @var $Gui Gui */
    protected $Gui;

    /** @var $BillingAddress Address */
    protected $BillingAddress;

    /** @var $ShippingAddress Address */
    protected $ShippingAddress;

    /** @var $Customer Customer */
    protected $Customer;

    /** @var $Status string */
    protected $Status;

    /** @var $CustomerReference string */
    protected $CustomerReference;

    /**
     * True = Svea will buy this invoice.
     * False = Svea will not buy this invoice.
     * null = Selected payment method is not Invoice.
     * @var $SveaWillBuyOrder bool
     */
    protected $SveaWillBuyOrder;

    /** @var $countryCode string */
    protected $countryCode;

    /** @var $currency string */
    protected $currency;

    /** @var $locale string */
    protected $locale;

    /** @var $clientOrderNumber string */
    protected $clientOrderNumber;

    /** @var $merchantSettings MerchantSettings */
    protected $merchantSettings;

    /** @var OrderRow[] */
    protected $cartItems;

    /** @var $presetValues PresetValue[] */
    protected $presetValues;

    /** @var IdentityFlags */
    protected $identityFlags;

    /** @var $partnerKey string */
    protected $partnerKey;

    /** @var $merchantData MerchantDataResponse */
    protected $merchantData;

    /**
     * @return array
     */
    public function getValidStatuses()
    {
        return $this->validStatuses;
    }

    /**
     * @param array $validStatuses
     * @return GetOrder
     */
    public function setValidStatuses($validStatuses)
    {
        $this->validStatuses = $validStatuses;
        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentType()
    {
        return $this->PaymentType;
    }

    /**
     * @param string $PaymentType
     * @return GetOrder
     */
    public function setPaymentType($PaymentType)
    {
        $this->PaymentType = $PaymentType;
        return $this;
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return $this->OrderId;
    }

    /**
     * @param int $OrderId
     * @return GetOrder
     */
    public function setOrderId($OrderId)
    {
        $this->OrderId = $OrderId;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmailAddress()
    {
        return $this->EmailAddress;
    }

    /**
     * @param string $EmailAddress
     * @return GetOrder
     */
    public function setEmailAddress($EmailAddress)
    {
        $this->EmailAddress = $EmailAddress;
        return $this;
    }

    /**
     * @return string
     */
    public function getPhoneNumber()
    {
        return $this->PhoneNumber;
    }

    /**
     * @param string $PhoneNumber
     * @return GetOrder
     */
    public function setPhoneNumber($PhoneNumber)
    {
        $this->PhoneNumber = $PhoneNumber;
        return $this;
    }

    /**
     * @return Gui
     */
    public function getGui()
    {
        return $this->Gui;
    }

    /**
     * @param Gui $Gui
     * @return GetOrder
     */
    public function setGui($Gui)
    {
        $this->Gui = $Gui;
        return $this;
    }

    /**
     * @return Address
     */
    public function getBillingAddress()
    {
        return $this->BillingAddress;
    }

    /**
     * @param Address $BillingAddress
     * @return GetOrder
     */
    public function setBillingAddress($BillingAddress)
    {
        $this->BillingAddress = $BillingAddress;
        return $this;
    }

    /**
     * @return Address
     */
    public function getShippingAddress()
    {
        return $this->ShippingAddress;
    }

    /**
     * @param Address $ShippingAddress
     * @return GetOrder
     */
    public function setShippingAddress($ShippingAddress)
    {
        $this->ShippingAddress = $ShippingAddress;
        return $this;
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        return $this->Customer;
    }

    /**
     * @param Customer $Customer
     * @return GetOrder
     */
    public function setCustomer($Customer)
    {
        $this->Customer = $Customer;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->Status;
    }

    /**
     * @param string $Status
     * @return GetOrder
     */
    public function setStatus($Status)
    {
        $this->Status = $Status;
        return $this;
    }

    /**
     * @return string
     */
    public function getCustomerReference()
    {
        return $this->CustomerReference;
    }

    /**
     * @param string $CustomerReference
     * @return GetOrder
     */
    public function setCustomerReference($CustomerReference)
    {
        $this->CustomerReference = $CustomerReference;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSveaWillBuyOrder()
    {
        return $this->SveaWillBuyOrder;
    }

    /**
     * @param bool $SveaWillBuyOrder
     * @return GetOrder
     */
    public function setSveaWillBuyOrder($SveaWillBuyOrder)
    {
        $this->SveaWillBuyOrder = $SveaWillBuyOrder;
        return $this;
    }

    /**
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     * @return GetOrder
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     * @return GetOrder
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     * @return GetOrder
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientOrderNumber()
    {
        return $this->clientOrderNumber;
    }

    /**
     * @param string $clientOrderNumber
     * @return GetOrder
     */
    public function setClientOrderNumber($clientOrderNumber)
    {
        $this->clientOrderNumber = $clientOrderNumber;
        return $this;
    }

    /**
     * @return MerchantSettings
     */
    public function getMerchantSettings()
    {
        return $this->merchantSettings;
    }

    /**
     * @param MerchantSettings $merchantSettings
     * @return GetOrder
     */
    public function setMerchantSettings($merchantSettings)
    {
        $this->merchantSettings = $merchantSettings;
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
     * @param OrderRow[] $cartItems
     * @return GetOrder
     */
    public function setCartItems($cartItems)
    {
        $this->cartItems = $cartItems;
        return $this;
    }

    /**
     * @return PresetValue[]
     */
    public function getPresetValues()
    {
        return $this->presetValues;
    }

    /**
     * @param PresetValue[] $presetValues
     * @return GetOrder
     */
    public function setPresetValues($presetValues)
    {
        $this->presetValues = $presetValues;
        return $this;
    }

    /**
     * @return IdentityFlags
     */
    public function getIdentityFlags()
    {
        return $this->identityFlags;
    }

    /**
     * @param IdentityFlags $identityFlags
     * @return GetOrder
     */
    public function setIdentityFlags($identityFlags)
    {
        $this->identityFlags = $identityFlags;
        return $this;
    }

    /**
     * @return string
     */
    public function getPartnerKey()
    {
        return $this->partnerKey;
    }

    /**
     * @param string $partnerKey
     * @return GetOrder
     */
    public function setPartnerKey($partnerKey)
    {
        $this->partnerKey = $partnerKey;
        return $this;
    }

    /**
     * @return MerchantDataResponse
     */
    public function getMerchantData()
    {
        return $this->merchantData;
    }

    /**
     * @param MerchantDataResponse $merchantData
     * @return GetOrder
     */
    public function setMerchantData($merchantData)
    {
        $this->merchantData = $merchantData;
        return $this;
    }



    // Helpers!


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
    
}
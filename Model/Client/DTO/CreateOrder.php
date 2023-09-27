<?php
namespace Svea\Checkout\Model\Client\DTO;

use Svea\Checkout\Model\Client\DTO\Order\IdentityFlags;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class CreateOrder extends AbstractRequest
{

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

    /** @var Order\IdentityFlags */
    protected $identityFlags;

    /** @var $partnerKey string */
    protected $partnerKey;

    /** @var $merchantData string */
    protected $merchantData;

    /** @var Order\ShippingInformation */
    protected $shippingInformation;

    /** @var bool */
    protected $recurring = false;

    /**
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * @param string $countryCode
     * @return CreateOrder
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
     * @return CreateOrder
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
     * @return CreateOrder
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
     * @return CreateOrder
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
     * @return CreateOrder
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
        return $this->cartItems;
    }

    /**
     * @param OrderRow[] $cartItems
     * @return CreateOrder
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
     * @return CreateOrder
     */
    public function setPresetValues($presetValues)
    {
        $this->presetValues = $presetValues;
        return $this;
    }

    /**
     * @return Order\IdentityFlags
     */
    public function getIdentityFlags()
    {
        return $this->identityFlags;
    }

    /**
     * @param Order\IdentityFlags $identityFlags
     * @return CreateOrder
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
     * @return CreateOrder
     */
    public function setPartnerKey($partnerKey)
    {
        $this->partnerKey = $partnerKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getMerchantData()
    {
        return $this->merchantData;
    }

    /**
     * @param string $merchantData
     * @return CreateOrder
     */
    public function setMerchantData($merchantData)
    {
        $this->merchantData = $merchantData;
        return $this;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {
        $data = [
            'countryCode' => $this->getCountryCode(),
            'currency' => $this->getCurrency(),
            'locale' => $this->getLocale(),
            'clientOrderNumber' => $this->getClientOrderNumber(),
            'merchantSettings' => $this->getMerchantSettings()->toArray(),
            'merchantData' => $this->getMerchantData(),
            'recurring' => $this->getRecurring()
        ];

        $flags = $this->getIdentityFlags();
        if ($flags instanceof IdentityFlags) {
            $data['identityFlags'] = $flags->toArray();
        }

        if ($this->getPartnerKey()) {
            $data['partnerKey'] = $this->getPartnerKey();
        }

        $items = $this->getCartItems();
        if (is_array($items)) {
            $cartItems = [];
            foreach ($items as $item) {
                /** @var OrderRow $item */
                $cartItems[] = $item->toArray();
            }

            $data['Cart'] = ['items' => $cartItems];
        }

        $preset = $this->getPresetValues();
        if (is_array($preset)) {
            $presetValues = [];
            foreach ($preset as $presetObj) {
                /** @var PresetValue $presetObj */
                $presetValues[] = $presetObj->toArray();
            }

            $data['presetValues'] = $presetValues;
        }

        if ($this->getShippingInformation()) {
            $data['ShippingInformation'] = $this->getShippingInformation()->toArray();
        }

        return $data;
    }

    /**
     * Get the value of shippingInformation
     *
     * @return Order\ShippingInformation
     */
    public function getShippingInformation()
    {
        return $this->shippingInformation;
    }

    /**
     * Set the value of shippingInformation
     *
     * @param Order\ShippingInformation $shippingInformation
     * @return self
     */
    public function setShippingInformation(Order\ShippingInformation $shippingInformation)
    {
        $this->shippingInformation = $shippingInformation;
        return $this;
    }

    /**
     * Get the value of recurring
     */
    public function getRecurring()
    {
        return $this->recurring;
    }

    /**
     * Set the value of recurring
     *
     * @param bool $recurring
     * @return self
     */
    public function setRecurring($recurring)
    {
        $this->recurring = $recurring;
        return $this;
    }
}

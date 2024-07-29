<?php

namespace Svea\Checkout\Helper;

use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Cart
 * @package Svea\Checkout\Helper
 */
class Cart extends AbstractHelper
{

    /**
     * svea_checkout/crosssell/
     */
    const XML_PATH_CROSSSELL = 'svea_checkout/crosssell/';
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var FormKey
     */
    protected $formKey;

    /**
     * Cart constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param FormKey $formKey
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        FormKey $formKey
    )
    {
        $this->storeManager = $storeManager;
        $this->formKey = $formKey;
        parent::__construct($context);
    }

    /**
     * Current Currency code
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function isDisplayCrosssell($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CROSSSELL . 'display_crosssell',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param null $store
     * @return mixed
     */
    public function getNumberOfCrosssellProducts($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CROSSSELL . 'crosssell_limit',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

     /**
     * @param null $store
     * @return mixed
     */
    public function getCrosssellHeadline($store = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CROSSSELL . 'crosssell_headline',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @return mixed
     */
    public function getFormKey()
    {
        return $this->formKey;
    }
}
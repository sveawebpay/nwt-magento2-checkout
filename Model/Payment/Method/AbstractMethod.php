<?php
namespace Svea\Checkout\Model\Payment\Method;


/**
 * Svea Checkout Abstract Payment method
 */
abstract class AbstractMethod extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $_helper;

    /** @var \Svea\Checkout\Model\Svea\Items $itemsHandler */
    protected $itemsHandler;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    protected $_currencyFactory;

    /** @var \Svea\Checkout\Model\Svea\Order $sveaHandler */
    protected $sveaHandler;



    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Svea\Checkout\Model\Svea\Items $itemsHandler
     * @param \Svea\Checkout\Model\Svea\Order $sveaHandler
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Model\Svea\Items $itemsHandler,
        \Svea\Checkout\Model\Svea\Order $sveaHandler,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null, //not required params need to be at the end of the list, else Cannot instantiate abstract class Magento\Framework\Model\ResourceModel\AbstractResource
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,

        array $data = []
    ) {
        $this->_helper = $helper;
        $this->_currencyFactory = $currencyFactory;

        $this->itemsHandler = $itemsHandler;
        $this->sveaHandler = $sveaHandler;


        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
    }




}

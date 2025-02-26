<?php

namespace Svea\Checkout\Model;

use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Model\Quote\ShippingAssignment\ShippingAssignmentProcessor;
use Magento\Quote\Model\Quote;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Model\Session;
use Svea\Checkout\Model\SessionFactory;
use Svea\Checkout\Model\ResourceModel\Session as SessionResource;
use Svea\Checkout\Model\ResourceModel\SessionFactory as SessionResourceFactory;

/**
 * Class CheckoutContext
 *
 * @package Svea\Checkout\Model
 */
class CheckoutContext
{
    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var \Svea\Checkout\Logger\Logger
     */
    protected $logger;

    /** @var \Svea\Checkout\Model\Svea\Order $sveaOrderHandler */
    protected $sveaOrderHandler;

    /** @var \Magento\Sales\Api\OrderCustomerManagementInterface */
    protected $orderCustomerManagement;

    /** @var \Magento\Newsletter\Model\Subscriber $Subscriber */
    protected $subscriber;

    /** @var \Svea\Checkout\Model\Svea\Locale $sveaLocale */
    protected $sveaLocale;

    /** @var CheckoutOrderNumberReference $sveaCheckoutReferenceHelper */
    protected $sveaCheckoutReferenceHelper;

    /** @var \Magento\Sales\Api\OrderRepositoryInterface $orderRepository */
    protected $orderRepository;

    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var AddressInterfaceFactory
     */
    private $addressInterfaceFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var SveaShippingInfo
     */
    private SveaShippingInfo $sveaShippingInfoService;

    /**
     * @var CartExtensionFactory
     */
    private CartExtensionFactory $cartExtensionFactory;

    /**
     * @var ShippingAssignmentProcessor
     */
    private ShippingAssignmentProcessor $shippingAssignmentProcessor;

    /**
     * @var SveaRecurringInfo
     */
    private SveaRecurringInfo $sveaRecurringInfo;

    /**
     * @var SessionFactory
     */
    private SessionFactory $sessionFactory;

    /**
     * @var SessionResourceFactory
     */
    private SessionResourceFactory $sessionResourceFactory;

    /**
     * @var array
     */
    private array $serviceContainers = [];

    private ?SessionResource $sessionResource = null;

    /**
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Svea\Checkout\Model\Svea\Order $sveaOrderHandler
     * @param \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper
     * @param \Svea\Checkout\Logger\Logger $logger
     * @param \Svea\Checkout\Model\Svea\Locale $sveaLocale
     * @param \Magento\Sales\Api\OrderCustomerManagementInterface $orderCustomerManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Newsletter\Model\Subscriber $subscriber
     * @param \Magento\Customer\Api\AddressRepositoryInterface $addressRepository
     * @param AddressInterfaceFactory $addressInterfaceFactory
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param SveaShippingInfo $sveaShippingInfoService
     * @param CartExtensionFactory $cartExtensionFactory
     * @param ShippingAssignmentProcessor $shippingAssignmentProcessor
     * @param SveaRecurringInfo $sveaRecurringInfo
     * @param SessionFactory $sessionFactory
     * @param SessionResourceFactory $sessionResourceFactory
     */
    public function __construct(
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Model\Svea\Order $sveaOrderHandler,
        \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper,
        \Svea\Checkout\Logger\Logger $logger,
        \Svea\Checkout\Model\Svea\Locale $sveaLocale,
        \Magento\Sales\Api\OrderCustomerManagementInterface $orderCustomerManagement,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        AddressInterfaceFactory $addressInterfaceFactory,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        SveaShippingInfo $sveaShippingInfoService,
        CartExtensionFactory $cartExtensionFactory,
        ShippingAssignmentProcessor $shippingAssignmentProcessor,
        SveaRecurringInfo $sveaRecurringInfo,
        SessionFactory $sessionFactory,
        SessionResourceFactory $sessionResourceFactory,
        array $serviceContainers = []
    ) {
        $this->helper        = $helper;
        $this->logger = $logger;
        $this->sveaOrderHandler = $sveaOrderHandler;
        $this->sveaLocale = $sveaLocale;
        $this->sveaCheckoutReferenceHelper = $sveaCheckoutReferenceHelper;
        $this->orderCustomerManagement = $orderCustomerManagement;
        $this->subscriber = $subscriber;
        $this->orderRepository = $orderRepository;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->addressRepository = $addressRepository;
        $this->addressInterfaceFactory = $addressInterfaceFactory;
        $this->sveaShippingInfoService = $sveaShippingInfoService;
        $this->cartExtensionFactory = $cartExtensionFactory;
        $this->shippingAssignmentProcessor = $shippingAssignmentProcessor;
        $this->sveaRecurringInfo = $sveaRecurringInfo;
        $this->sessionFactory = $sessionFactory;
        $this->sessionResourceFactory = $sessionResourceFactory;
        $this->serviceContainers = $serviceContainers;
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @return \Svea\Checkout\Logger\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /** @return \Svea\Checkout\Model\Svea\Order */
    public function getSveaOrderHandler()
    {
        return $this->sveaOrderHandler;
    }

    /**
     * @return \Magento\Sales\Api\OrderCustomerManagementInterface
     */
    public function getOrderCustomerManagement()
    {
        return $this->orderCustomerManagement;
    }

    /**
     * @return \Magento\Newsletter\Model\Subscriber
     */
    public function getSubscriber()
    {
        return $this->subscriber;
    }

    /**
     * @return Svea\Locale
     */
    public function getSveaLocale()
    {
        return $this->sveaLocale;
    }

    /**
     * @return CheckoutOrderNumberReference
     */
    public function getSveaCheckoutReferenceHelper()
    {
        return $this->sveaCheckoutReferenceHelper;
    }

    /**
     * @return \Magento\Sales\Api\OrderRepositoryInterface
     */
    public function getOrderRepository()
    {
        return $this->orderRepository;
    }

    /**
     * @return \Magento\Customer\Api\AddressRepositoryInterface
     */
    public function getAddressRepository(): \Magento\Customer\Api\AddressRepositoryInterface
    {
        return $this->addressRepository;
    }

    /**
     * @return AddressInterfaceFactory
     */
    public function getAddressInterfaceFactory(): AddressInterfaceFactory
    {
        return $this->addressInterfaceFactory;
    }
    /**
     * @return \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    public function getOrderCollectionFactory(): \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
    {
        return $this->orderCollectionFactory;
    }

    public function getSveaShippingInfoService(): SveaShippingInfo
    {
        return $this->sveaShippingInfoService;
    }

    public function getCartExtensionFactory(): CartExtensionFactory
    {
        return $this->cartExtensionFactory;
    }

    public function getShippingAssignmentProcessor(): ShippingAssignmentProcessor
    {
        return $this->shippingAssignmentProcessor;
    }

    public function getRecurringInfoService(): SveaRecurringInfo
    {
        return $this->sveaRecurringInfo;
    }

    /**
     * Fetches Svea Session object by the unique identifiers:
     *  Quote ID, Country ID, and Recurring flag
     *  Returns object with empty ID if Session does not exist yet
     *
     * @param Quote $checkoutSession
     * @return Session
     */
    public function fetchSveaSession(Quote $quote): Session
    {
        $session = $this->sessionFactory->create();
        if (!$quote->getId() || !$quote->getBillingAddress()->getCountryId()) {
            return $session;
        }

        $recurringActive = $this->helper->getRecurringPaymentsActive();
        $recurringInfo = $this->getRecurringInfoService()->quoteGetter($quote);
        $this->getSessionResource()->loadByIdentifiers(
            $session,
            (int)$quote->getId(),
            (string)$quote->getBillingAddress()->getCountryId(),
            ($recurringActive && $recurringInfo->getEnabled())
        );

        return $session;
    }

    /**
     * @param Session $session
     * @return void
     */
    public function saveSveaSession(Session $session): void
    {
        $resource = $this->getSessionResource();
        $resource->save($session);
    }

    /**
     * Get Service Container by name
     *
     * @param string $name
     * @return array|null
     */
    public function getServiceContainer(string $name): ?array
    {
        return $this->serviceContainers[$name] ?? null;
    }

    /**
     * @return SessionResource
     */
    private function getSessionResource(): SessionResource
    {
        if ($this->sessionResource === null) {
            $this->sessionResource = $this->sessionResourceFactory->create();
        }

        return $this->sessionResource;
    }
}

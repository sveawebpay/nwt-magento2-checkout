<?php declare(strict_types=1);

namespace Svea\Checkout\Service\SveaRecurringInfo;

use Magento\Sales\Model\AdminOrder\Create;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\DataObject\Copy;
use Magento\Checkout\Model\ShippingInformationFactory;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\StateException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\UrlInterface;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;
use Svea\Checkout\Helper\Data;
use \Psr\Log\LoggerInterface;

/**
 * Service class for placing recurring orders
 */
class PlaceOrders
{
    private Create $orderCreate;

    private QuoteFactory $quoteFactory;

    private QuoteManagement $quoteManagement;

    private QuoteRepository $quoteRepo;

    private CustomerRepositoryInterface $customerRepo;

    private Copy $objectCopyService;

    private ShippingInformationFactory $shipInfoFactory;

    private ShippingInformationManagement $shipInfoManagement;

    private SveaRecurringInfo $sveaRecurringInfo;

    private SveaShippingInfo $sveaShippingInfo;

    private TokenClient $tokenClient;

    private LoggerInterface $logger;

    private Data $config;

    private UrlInterface $urlBuilder;

    private TransportBuilder $transportBuilder;

    private array $results = [];

    public function __construct(
        Create $orderCreate,
        QuoteFactory $quoteFactory,
        QuoteManagement $quoteManagement,
        QuoteRepository $quoteRepo,
        CustomerRepositoryInterface $customerRepo,
        Copy $objectCopyService,
        ShippingInformationFactory $shipInfoFactory,
        ShippingInformationManagement $shipInfoManagement,
        SveaRecurringInfo $sveaRecurringInfo,
        SveaShippingInfo $sveaShippingInfo,
        TokenClient $tokenClient,
        LoggerInterface $logger,
        Data $config,
        UrlInterface $urlBuilder,
        TransportBuilder $transportBuilder
    ) {
        $this->orderCreate = $orderCreate;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->customerRepo = $customerRepo;
        $this->quoteRepo = $quoteRepo;
        $this->objectCopyService = $objectCopyService;
        $this->shipInfoFactory = $shipInfoFactory;
        $this->shipInfoManagement = $shipInfoManagement;
        $this->sveaRecurringInfo = $sveaRecurringInfo;
        $this->sveaShippingInfo = $sveaShippingInfo;
        $this->tokenClient = $tokenClient;
        $this->logger = $logger;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->transportBuilder = $transportBuilder;
    }

    /**
     * Place recurring orders
     *
     * @param ModelRecurringInfo[] $recurringInfos
     */
    public function placeRecurringOrders(array $recurringInfos): void
    {
        foreach ($recurringInfos as $recurringInfo) {
            $token = $recurringInfo->getRecurringToken();
            $this->results[$token] = [];
            $this->commit($recurringInfo);
        }
    }

    /**
     * @param string $token
     * @return array
     */
    public function fetchResult(string $token): array
    {
        return $this->results[$token] ?? [];
    }

    /**
     * Commit a single recurring order and store the result
     *
     * @param ModelRecurringInfo $recurringInfo
     * @return void
     */
    private function commit(ModelRecurringInfo $recurringInfo): void
    {
        $recurringToken = (string)$recurringInfo->getRecurringToken();
        $orderId = $recurringInfo->getOriginalOrderId();
        try {
            $order = $this->sveaRecurringInfo->loadOrder((int)$orderId);
        } catch (\Exception $e) {
            $this->logError(
                $recurringToken,
                [sprintf('The original order with entity_id %s could not be loaded.', $orderId)]
            );
            $this->results[$recurringToken] = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            return;
        }

        // Populate quote payment with order payment data
        /** @var Order $order */
        $this->orderCreate->setQuote($this->quoteFactory->create());
        $this->orderCreate->initFromOrder($order);
        $quote = $this->orderCreate->getQuote();
        $orderCustomerId = $order->getCustomerId();
        if (!!$orderCustomerId) {
            $customer = $this->customerRepo->getById($orderCustomerId);
            $quote->setCustomer($customer);
            $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
        }
        $quote->setStoreId($order->getStoreId());
        $quote->reserveOrderId();
        $this->objectCopyService->copyFieldsetToTarget(
            'sales_convert_order_payment',
            'to_quote_payment',
            $order->getPayment(),
            $quote->getPayment()
        );

        // Update recurring info for new Quote
        $this->sveaRecurringInfo->scheduleNextRecurringOrder($quote);
        $quoteRecurringInfo = $this->sveaRecurringInfo->quoteGetter($quote);

        // We must save quote lots of times here, looks messy but otherwise it doesn't work properly
        $this->sveaRecurringInfo->quoteSetter($quote, $quoteRecurringInfo);
        $this->quoteRepo->save($quote);

        // 1. Create recurring order in Svea
        try {
            $this->tokenClient->createRecurringOrder($recurringToken, $quote);
        } catch (\Exception $e) {
            $this->handleFailedPayAttempts($recurringInfo, $order);
            $this->logError(
                $recurringToken,
                [$e->getMessage()]
            );
            $this->results[$recurringToken] = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            return;
        }

        // 2. Create order in Magento
        try {
            $this->saveQuoteShippingInfo($quote, $order);
            $this->quoteRepo->save($quote);
            $orderId = $this->quoteManagement->placeOrder($quote->getId());
            $recurringInfo->setNextOrderDate($quoteRecurringInfo->getNextOrderDate());
            $recurringInfo->setFailedPayAttempts(0);
        } catch (\Exception $e) {
            $this->logError(
                $recurringToken,
                [$e->getMessage()]
            );
            $this->results[$recurringToken] = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            return;
        }

        $logMessage = sprintf(
            '[RecurringPayment Success] Order successfully placed using token: %s. Order ID: %d',
            $recurringToken,
            $orderId
        );
        $this->logger->info($logMessage);
        $this->results[$recurringToken] = [
            'success' => true,
            'message' => $logMessage
        ];
    }

    /**
     * Transfer shipping info from order to new Quote
     *
     * @param Quote $quote
     * @param string $methodCode
     * @param string $carrier
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    private function saveQuoteShippingInfo(Quote $quote, Order $order): void
    {
        if ($order->getIsVirtual()) {
            return;
        }

        $shippingMethod = $order->getShippingMethod(true);
        $carrier = $shippingMethod->getCarrierCode();

        if (strpos($carrier, Carrier::CODE) !== false) {
            $this->sveaShippingInfo->setExcludeSveaShipping(false);
            $this->sveaShippingInfo->setInQuote($quote, $this->sveaShippingInfo->getFromOrder($order)->getData());
        }

        $method = $shippingMethod->getMethod();
        $shipInfo = $this->shipInfoFactory->create();
        $shipInfo->setBillingAddress($quote->getBillingAddress());
        $shipInfo->setShippingAddress($quote->getShippingAddress());
        $shipInfo->setShippingCarrierCode($carrier);
        $shipInfo->setShippingMethodCode(strtolower($method));
        $this->shipInfoManagement->saveAddressInformation($quote->getId(), $shipInfo);
    }

    /**
     * Log error messages
     *
     * @param string $token
     * @param array $messages
     * @return void
     */
    public function logError(string $token, array $messages): void
    {
        $this->logger->error(sprintf('[RecurringPayment Error Start. Token: %s]', $token));
        foreach ($messages as $message) {
            $this->logger->error($message);
        }
        $this->logger->error(sprintf('[RecurringPayment Error End. Token: %s]', $token));
    }

    /**
     * Check max failed pay attempts for how to handle the order
     *
     * @param ModelRecurringInfo $recurringInfo
     * @param Order $order
     * @return void
     */
    private function handleFailedPayAttempts(ModelRecurringInfo $recurringInfo, Order $order): void
    {
        $storeId = (int)$order->getStoreId();
        // Skip if not configured
        $failedPayEscalation = $this->config->getRecurringFailedPayEscalation($storeId);
        if ($failedPayEscalation < 1) {
            return;
        }

        // If less than max allowed attempts re-schedule for the next day
        $newFailedPayAttempts = $recurringInfo->getFailedPayAttempts() + 1;
        $recurringInfo->setFailedPayAttempts($newFailedPayAttempts);
        $recurringInfo->setNextOrderDate(date('Y-m-d', strtotime('+ 1 day')));
        if ($newFailedPayAttempts < $failedPayEscalation) {
            return;
        }

        $atEscalationlevel = (int)floor($newFailedPayAttempts / $failedPayEscalation);
        $atExactEscalationLevel = $atEscalationlevel === ($newFailedPayAttempts / $failedPayEscalation);
        $autoCancel = $this->config->getRecurringCancelAfterSecondEscalation($storeId);

        if ($atEscalationlevel === 3 && $atExactEscalationLevel && $autoCancel) {
            $this->sveaRecurringInfo->cancel($recurringInfo);
            return;
        }

        // If escalation at this point is over 2 (which means auto cancel is off), do nothing
        if ($atEscalationlevel > 2) {
            return;
        }

        if (!$atExactEscalationLevel) {
            return;
        }

        // When escalation level is at 1 or 2 we send email
        $senderEmail = $this->config->getRecurringFailedSenderEmail($storeId);
        $senderName  = $this->config->getRecurringFailedSenderName($storeId);
        $this->transportBuilder->addTo($order->getCustomerEmail());
        $templateVars = [
            'order' => $order,
            'store' => $order->getStore(),
            'order_data' => [
                'customer_name' => $order->getCustomerName(),
                'increment_id' => $order->getIncrementId(),
                'order_url' => $this->urlBuilder->getUrl(
                    'sales/order/view',
                    [
                        'order_id' => $order->getId(),
                        '_secure' => true
                    ]
                )
            ]
        ];
        $templateOptions = [
            'area' => Area::AREA_FRONTEND,
            'store' => $storeId,
        ];
        $this->transportBuilder->setFromByScope(['email' => $senderEmail, 'name' => $senderName], $storeId);
        $this->transportBuilder->setTemplateOptions($templateOptions);

        if ($atEscalationlevel === 2 && $autoCancel) {
            $templateVars['auto_cancel'] = 1;
        }

        // If max attempts have been reached, send email to customer
        $this->transportBuilder->setTemplateIdentifier($this->config->getRecurringFailedEmailTemplate($storeId));
        $this->transportBuilder->setTemplateVars($templateVars);
        try {
            $this->transportBuilder->getTransport()->sendMessage();
        } catch (\Exception $e) {
            return;
        }
    }
}

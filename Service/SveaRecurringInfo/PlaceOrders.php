<?php declare(strict_types=1);

namespace Svea\Checkout\Service\SveaRecurringInfo;

use Magento\Sales\Model\AdminOrder\Create;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Framework\DataObject\Copy;
use Magento\Checkout\Model\ShippingInformationFactory;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\StateException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Service\SveaRecurringInfo;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;
use Svea\Checkout\Model\Data\PaymentRecurringInfo;
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

    private Copy $objectCopyService;

    private ShippingInformationFactory $shipInfoFactory;

    private ShippingInformationManagement $shipInfoManagement;

    private SveaRecurringInfo $sveaRecurringInfo;

    private SveaShippingInfo $sveaShippingInfo;

    private TokenClient $tokenClient;

    private LoggerInterface $logger;

    private array $results = [];

    public function __construct(
        Create $orderCreate,
        QuoteFactory $quoteFactory,
        QuoteManagement $quoteManagement,
        QuoteRepository $quoteRepo,
        Copy $objectCopyService,
        ShippingInformationFactory $shipInfoFactory,
        ShippingInformationManagement $shipInfoManagement,
        SveaRecurringInfo $sveaRecurringInfo,
        SveaShippingInfo $sveaShippingInfo,
        TokenClient $tokenClient,
        LoggerInterface $logger
    ) {
        $this->orderCreate = $orderCreate;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->quoteRepo = $quoteRepo;
        $this->objectCopyService = $objectCopyService;
        $this->shipInfoFactory = $shipInfoFactory;
        $this->shipInfoManagement = $shipInfoManagement;
        $this->sveaRecurringInfo = $sveaRecurringInfo;
        $this->sveaShippingInfo = $sveaShippingInfo;
        $this->tokenClient = $tokenClient;
        $this->logger = $logger;
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
        $this->orderCreate->setQuote($this->quoteFactory->create());
        $this->orderCreate->initFromOrder($order);
        $quote = $this->orderCreate->getQuote();
        $quote->reserveOrderId();
        $quote->setStoreId($order->getStoreId());
        $this->objectCopyService->copyFieldsetToTarget(
            'sales_convert_order_payment',
            'to_quote_payment',
            $order->getPayment(),
            $quote->getPayment()
        );

        // Update recurring info for new Quote
        $this->sveaRecurringInfo->scheduleNextRecurringOrder($quote);
        $quoteRecurringInfo = $this->sveaRecurringInfo->quoteGetter($quote);
        $this->unsetForNextOrder($quoteRecurringInfo);
        $this->sveaRecurringInfo->quoteSetter($quote, $quoteRecurringInfo);
        $this->quoteRepo->save($quote);

        try {
            $this->saveQuoteShippingInfo($quote, $order);
            $this->tokenClient->createRecurringOrder($recurringToken, $quote);
            $orderId = $this->quoteManagement->placeOrder($quote->getId());
            $recurringInfo->setNextOrderDate($quoteRecurringInfo->getNextOrderDate());
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
     * Remove data irrelevant for new recurring order
     *
     * @param PaymentRecurringInfo $recurringInfo
     * @return void
     */
    private function unsetForNextOrder(PaymentRecurringInfo $recurringInfo): void
    {
        $recurringInfo->setStandardOrderId(null);
        $recurringInfo->setStandardClientOrderNumber(null);
        $recurringInfo->setRecurringOrderId(null);
        $recurringInfo->setRecurringClientOrderNumber(null);
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
}

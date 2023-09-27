<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\AlreadyExistsException;
use Svea\Checkout\Model\Data\PaymentRecurringInfoFactory;
use Svea\Checkout\Model\Data\PaymentRecurringInfo;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\RecurringInfoFactory as ModelFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Framework\DataObject\Copy;
use Magento\Checkout\Model\ShippingInformationFactory;
use Magento\Checkout\Model\ShippingInformationManagement;
use \Psr\Log\LoggerInterface;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;

class SveaRecurringInfo
{
    const RECURRING_PAYMENT_INFO_KEY = 'svea_recurring_info';

    private PaymentRecurringInfoFactory $paymentRecurringInfoFactory;

    private TokenClient $tokenClient;

    private OrderRepository $orderRepo;

    private Create $orderCreate;

    private QuoteFactory $quoteFactory;

    private QuoteManagement $quoteManagement;

    private Copy $objectCopyService;

    private ShippingInformationFactory $shipInfoFactory;

    private ShippingInformationManagement $shipInfoManagement;

    private SveaShippingInfo $sveaShippingInfo;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private ModelFactory $recurringInfoModelFactory;

    private LoggerInterface $logger;

    public function __construct(
        PaymentRecurringInfoFactory $paymentRecurringInfoFactory,
        TokenClient $tokenClient,
        OrderRepository $orderRepo,
        Create $orderCreate,
        QuoteFactory $quoteFactory,
        QuoteManagement $quoteManagement,
        Copy $objectCopyService,
        ShippingInformationFactory $shipInfoFactory,
        ShippingInformationManagement $shipInfoManagement,
        SveaShippingInfo $sveaShippingInfo,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        ModelFactory $recurringInfoModelFactory,
        LoggerInterface $logger
    ) {
        $this->paymentRecurringInfoFactory = $paymentRecurringInfoFactory;
        $this->tokenClient = $tokenClient;
        $this->orderRepo = $orderRepo;
        $this->orderCreate = $orderCreate;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->objectCopyService = $objectCopyService;
        $this->shipInfoFactory = $shipInfoFactory;
        $this->shipInfoManagement = $shipInfoManagement;
        $this->sveaShippingInfo = $sveaShippingInfo;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->recurringInfoModelFactory = $recurringInfoModelFactory;
        $this->logger = $logger;
    }

    /**
     * Get data object with Recurring Payment Info from quote
     *
     * @param Quote $quote
     * @return PaymentRecurringInfo
     */
    public function quoteGetter(Quote $quote): PaymentRecurringInfo
    {
        $payment = $quote->getPayment();
        $dataArray = $payment->getAdditionalInformation(self::RECURRING_PAYMENT_INFO_KEY);
        $recurringInfo = $this->paymentRecurringInfoFactory->create();
        if (!is_array($dataArray)) {
            return $recurringInfo;
        }

        $recurringInfo->setData($dataArray);
        return $recurringInfo;
    }

    /**
     * Sets Recurring Payment Info to quote
     *
     * @param Quote $quote
     * @param PaymentRecurringInfo $dataObject
     * @return void
     */
    public function quoteSetter(Quote $quote, PaymentRecurringInfo $dataObject): void
    {
        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(self::RECURRING_PAYMENT_INFO_KEY, $dataObject->getData());
    }

    /**
     * Get data object with Recurring Payment Info from order
     *
     * @param Order $order
     * @return PaymentRecurringInfo
     */
    public function orderGetter(Order $order): PaymentRecurringInfo
    {
        $payment = $order->getPayment();
        $additionalInfo = $payment->getAdditionalInformation();
        $dataArray = $additionalInfo[self::RECURRING_PAYMENT_INFO_KEY] ?? null;
        $recurringInfo = $this->paymentRecurringInfoFactory->create();
        if (null === $dataArray || !is_array($dataArray)) {
            return $recurringInfo;
        }

        $recurringInfo->setData($dataArray);
        return $recurringInfo;
    }

    /**
     * Get data object with Recurring Payment Info from Order Payment Info object
     *
     * @param InfoInterface $paymentInfo
     * @return ModelRecurringInfo
     */
    public function paymentInfoGetter(InfoInterface $paymentInfo): ModelRecurringInfo
    {
        $dataArray = $paymentInfo->getAdditionalInformation(self::RECURRING_PAYMENT_INFO_KEY);
        $modelRecurringInfo = $this->recurringInfoModelFactory->create();
        if (!is_array($dataArray)) {
            return $modelRecurringInfo;
        }

        $paymentRecurringInfo = $this->paymentRecurringInfoFactory->create()->setData($dataArray);
        if (!$paymentRecurringInfo->getEnabled()) {
            return $modelRecurringInfo;
        }

        return $this->recurringInfoRepo->getByRecurringToken((string)$paymentRecurringInfo->getRecurringToken());
    }

    /**
     * Cancel recurring payment
     *
     * @param ModelRecurringInfo $recurringInfo
     * @return void
     * @throws ClientException
     */
    public function cancel(ModelRecurringInfo $recurringInfo): void
    {
        $this->tokenClient->cancelToken($recurringInfo->getRecurringToken());
        $recurringInfo->setCanceledDate(date('Y-m-d'));
        $recurringInfo->setNextOrderDate(null);
    }

    /**
     * Load order accessor
     *
     * @param integer $orderId
     * @return OrderInterface
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function loadOrder(int $orderId): OrderInterface
    {
        return $this->orderRepo->get($orderId);
    }

    /**
     * Save order accessor
     *
     * @param Order $order
     * @return void
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     */
    public function saveOrder(Order $order): void
    {
        $this->orderRepo->save($order);
    }

    /**
     * Schedules next recurring order using a Quote
     *
     * @param Quote $quote
     * @param string|null $recurringToken
     * @return void
     */
    public function scheduleNextRecurringOrder(Quote $quote, ?string $recurringToken = null): void
    {
        $recurringInfo = $this->quoteGetter($quote);
        if (null !== $recurringToken) {
            $recurringInfo->setRecurringToken($recurringToken);
        }
        $nextOrderDate = $this->getNextOrderDate($recurringInfo);
        $recurringInfo->setNextOrderDate($nextOrderDate);
        $this->quoteSetter($quote, $recurringInfo);
    }

    /**
     * Place recurring order
     *
     * @param ModelRecurringInfo $recurringInfo
     * @return void
     */
    public function placeRecurringOrder(ModelRecurringInfo $recurringInfo): void
    {
        $recurringToken = (string)$recurringInfo->getRecurringToken();
        $orderId = $recurringInfo->getOriginalOrderId();
        try {
            $order = $this->loadOrder((int)$orderId);
        } catch (\Exception $e) {
            $this->logError(
                $recurringToken,
                [sprintf('The original order with entity_id %s could not be loaded.', $orderId)]
            );
            return;
        }

        $this->orderCreate->setQuote($this->quoteFactory->create());
        $this->orderCreate->initFromOrder($order);

        // Populate quote payment with order payment data
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
        $this->scheduleNextRecurringOrder($quote);
        $quoteRecurringInfo = $this->quoteGetter($quote);
        $this->unsetForNextOrder($quoteRecurringInfo);
        $this->quoteSetter($quote, $quoteRecurringInfo);

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
            return;
        }

        $logMessage = sprintf(
            '[RecurringPayment Success] Order successfully placed using token: %s. Order ID: %d',
            $recurringToken,
            $orderId
        );
        $this->logger->info($logMessage);
    }

    /**
     * @param Order $order
     * @return void
     * @throws \Exception
     */
    public function saveNewOrderRecurringInfo(Order $order): void
    {
        $recurringInfoData = $this->orderGetter($order);
        $recurringInfoModel = $this->recurringInfoModelFactory->create();
        $recurringInfoModel->setOriginalOrderId($order->getId());
        $recurringInfoModel->setRecurringToken($recurringInfoData->getRecurringToken());
        $recurringInfoModel->setNextOrderDate($recurringInfoData->getNextOrderDate());
        $recurringInfoModel->setFrequencyOption($recurringInfoData->getFrequencyOption());
        $this->recurringInfoRepo->save($recurringInfoModel);
    }

    /**
     * Get next order date based on selected frequency option
     *
     * @param RecurringInfo $recurringInfo
     * @return string
     */
    private function getNextOrderDate(PaymentRecurringInfo $recurringInfo): string
    {
        $frequencyOption = (string)$recurringInfo->getFrequencyOption();
        $parts = explode('|', $frequencyOption);
        list($frequency, $unit) = $parts;
        if ($frequency > 1) {
            $unit .= 's';
        }

        return date('Y-m-d', strtotime('+' . $frequency . ' ' . $unit));
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
    private function logError(string $token, array $messages): void
    {
        $this->logger->error(sprintf('[RecurringPayment Error Start. Token: %s]', $token));
        foreach ($messages as $message) {
            $this->logger->error($message);
        }
        $this->logger->error(sprintf('[RecurringPayment Error End. Token: %s]', $token));
    }
}

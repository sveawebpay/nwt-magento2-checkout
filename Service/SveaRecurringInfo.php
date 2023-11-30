<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\AlreadyExistsException;
use Svea\Checkout\Model\Data\PaymentRecurringInfoFactory;
use Svea\Checkout\Model\Data\PaymentRecurringInfo;
use Svea\Checkout\Model\Client\Api\TokenClient;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\RecurringInfoFactory as ModelFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderRepository;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\RecurringInfo as ModelRecurringInfo;

class SveaRecurringInfo
{
    const RECURRING_PAYMENT_INFO_KEY = 'svea_recurring_info';

    private PaymentRecurringInfoFactory $paymentRecurringInfoFactory;

    private TokenClient $tokenClient;

    private OrderRepository $orderRepo;

    private RecurringInfoRepositoryInterface $recurringInfoRepo;

    private ModelFactory $recurringInfoModelFactory;

    public function __construct(
        PaymentRecurringInfoFactory $paymentRecurringInfoFactory,
        TokenClient $tokenClient,
        OrderRepository $orderRepo,
        RecurringInfoRepositoryInterface $recurringInfoRepo,
        ModelFactory $recurringInfoModelFactory
    ) {
        $this->paymentRecurringInfoFactory = $paymentRecurringInfoFactory;
        $this->tokenClient = $tokenClient;
        $this->orderRepo = $orderRepo;
        $this->recurringInfoRepo = $recurringInfoRepo;
        $this->recurringInfoModelFactory = $recurringInfoModelFactory;
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
}

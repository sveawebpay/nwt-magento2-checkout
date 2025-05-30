<?php

namespace Svea\Checkout\Model\Svea;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Csp\Helper\CspNonceProvider;
use Svea\Checkout\Model\Client\Api\Checkout;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelOrder;
use Svea\Checkout\Model\Client\DTO\CancelOrderAmount;
use Svea\Checkout\Model\Client\DTO\CreateOrder;
use Svea\Checkout\Model\Client\DTO\DeliverOrder;
use Svea\Checkout\Model\Client\DTO\GetDeliveryResponse;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\Address;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformationFactory;
use Svea\Checkout\Model\Client\DTO\RefundNewCreditRow;
use Svea\Checkout\Model\Client\DTO\RefundPayment;
use Svea\Checkout\Model\Client\DTO\RefundPaymentAmount;
use Svea\Checkout\Model\Client\DTO\UpdateOrderCart;
use Svea\Checkout\Model\Svea\Data\PresetValues\Factory as PresetValuesFactory;
use Svea\Checkout\Model\Client\DTO\Order\OrderValidationFactory;
use Svea\Checkout\Api\Data\HasOrderValidationInterface;
use \Exception as BaseException;

class Order
{

    /**
     * @var Items $items
     */
    protected $items;

    /**
     * @ar Locale $_locale;
     */
    protected $_locale;

    /**
     * @var \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi
     */
    protected $checkoutApi;

    /**
     * @var \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi
     */
    protected $orderManagementApi;

    /**
     * @var \Svea\Checkout\Helper\Data $helper
     */
    protected $helper;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $_countryFactory;

    /** @var \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper */
    protected $sveaCheckoutReferenceHelper;

    protected $iframeSnippet = null;

    /**
     * @var PresetValuesFactory
     */
    private $presetValuesProviderFactory;

    /**
     * @var ShippingInformationFactory
     */
    private $shippingInfoFactory;

    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var OrderValidationFactory
     */
    private OrderValidationFactory $orderValidationFactory;

    /**
     * @var CspNonceProvider
     */
    private CspNonceProvider $cspNonceProvider;

    /**
     * Order constructor.
     *
     * @param \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi
     * @param Checkout $checkoutApi
     * @param \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper
     * @param \Svea\Checkout\Helper\Data $helper
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param PresetValuesFactory $presetValuesProviderFactory
     * @param Items $itemsHandler
     * @param Locale $locale
     * @param ShippingInformationFactory $shippingInfoFactory
     * @param ProductRepositoryInterface $productRepo
     * @param OrderValidationFactory $orderValidationFactory
     * @param CspNonceProvider $cspNonceProvider
     */
    public function __construct(
        \Svea\Checkout\Model\Client\Api\OrderManagement $orderManagementApi,
        \Svea\Checkout\Model\Client\Api\Checkout $checkoutApi,
        \Svea\Checkout\Model\CheckoutOrderNumberReference $sveaCheckoutReferenceHelper,
        \Svea\Checkout\Helper\Data $helper,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        PresetValuesFactory $presetValuesProviderFactory,
        Items $itemsHandler,
        Locale $locale,
        ShippingInformationFactory $shippingInfoFactory,
        ProductCollectionFactory $productCollectionFactory,
        OrderValidationFactory $orderValidationFactory,
        CspNonceProvider $cspNonceProvider
    ) {
        $this->helper = $helper;
        $this->items = $itemsHandler;
        $this->checkoutApi = $checkoutApi;
        $this->sveaCheckoutReferenceHelper = $sveaCheckoutReferenceHelper;
        $this->orderManagementApi = $orderManagementApi;
        $this->_countryFactory  = $countryFactory;
        $this->_locale = $locale;
        $this->presetValuesProviderFactory = $presetValuesProviderFactory;
        $this->shippingInfoFactory = $shippingInfoFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->orderValidationFactory = $orderValidationFactory;
        $this->cspNonceProvider = $cspNonceProvider;
    }

    /** @var $_quote Quote */
    protected $_quote;

    /**
     * @throws LocalizedException
     * @return $this
     */
    public function assignQuote(Quote $quote, $validate = true)
    {
        if ($validate) {
            if (!$quote->hasItems()) {
                throw new LocalizedException(__('Empty Cart'));
            }
            if ($quote->getHasError()) {
                throw new LocalizedException(__('Cart has errors, cannot checkout.'));
            }
        }

        $this->_quote = $quote;
        return $this;
    }

    /**
     * @param Quote $quote
     * @return GetOrderResponse
     * @throws ClientException
     * @throws BaseException
     */
    public function initNewSveaCheckoutPaymentByQuote(\Magento\Quote\Model\Quote $quote)
    {
        $paymentResponse = $this->createNewSveaPayment($quote, true);
        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
        return $paymentResponse;
    }

    /**
     * @param $newSignature
     * @param $currentSignature
     * @return bool
     */
    public function checkIfPaymentShouldBeUpdated($newSignature, $currentSignature)
    {

        // if the current signature is not set, then we must update payment
        if ($currentSignature == "" || $currentSignature == null) {
            return true;
        }

        // if the signatures doesn't match, it must mean that the quote has been changed!
        if ($newSignature != $currentSignature) {
            return true;
        }

        // nothing happened to the quote, we dont need to update payment at svea!
        return false;
    }

    /**
     * @param Quote $quote
     * @param $paymentId
     * @return void
     * @throws ClientException
     */
    public function updateCheckoutPaymentByQuoteAndOrderId(Quote $quote, $paymentId)
    {
        $items = $this->items->generateOrderItemsFromQuote($quote);
        $items = $this->items->fixCartItems($items);

        $payment = new UpdateOrderCart();
        $payment->setItems($items);
        $payment->setMerchantData($this->generateMerchantData($quote));
        if ($this->helper->getSveaShippingActive($quote->getStore()->getId())) {
            $info = $this->shippingInfoFactory->create()->generateFromQuote($quote);
            $payment->setShippingInformation($info);
        }

        $this->handleOrderValidation($quote, $payment);
        $paymentResponse = $this->checkoutApi->updateOrder($payment, $paymentId);

        $this->setIframeSnippet($paymentResponse->getGui()->getSnippet());
    }

    /**
     * @param Quote $quote
     * @return string
     */
    protected function generateMerchantData(Quote $quote)
    {
        return json_encode([
            "quote_id" => $this->getRefHelper()->getQuoteId(),
            "client_order_number" => $this->getRefHelper()->getClientOrderNumber(),
            "total" => $quote->getGrandTotal(),
        ]);
    }

    /**
     * This function will create a new svea payment.
     * The payment ID which is returned in the response will be added to the SVEA javascript API, to load the payment iframe.
     *
     * @param Quote $quote
     * @param bool $reloadCredentials
     * @return GetOrderResponse
     * @throws ClientException
     * @throws BaseException
     */
    protected function createNewSveaPayment(Quote $quote, $reloadCredentials = false)
    {
        $countryCode = $quote->getBillingAddress()->getCountryId();
        $allowedCountries = $this->getLocale()->getAllowedCountries();
        if ($this->helper->getInternationalFlowActive()) {
            $allowedCountries = $this->helper->getGeneralAllowedCountries();
        }

        if (!in_array($countryCode, $allowedCountries)) {
            throw new BaseException("The country is not supported.");
        }

        $sveaHash = $this->getRefHelper()->getSveaHash();

        $isTestMode = $this->helper->isTestMode();

        // Always generate a new reference for new svea payment
        $this->getRefHelper()->generateClientOrderNumberToQuote();
        $refId = $this->getRefHelper()->getClientOrderNumber();

        // generate items
        $items = $this->items->generateOrderItemsFromQuote($quote);
        $items = $this->items->fixCartItems($items);

        // set merchant settings, urls
        $merchantUrls = new MerchantSettings();
        $merchantUrls->setStoreId($quote->getStoreId());
        $merchantUrls->setCheckoutUri($this->helper->getCheckoutUrl());

        $merchantUrls->setTermsUri($this->helper->getTermsUrl());

        $confirmationUrl = $this->helper->getConfirmationUrl($sveaHash, $quote->getId());
        $pushUri = $this->helper->getPushUrl($sveaHash);
        $validationUri = $this->helper->getValidationUrl($sveaHash);

        // set callback urls and confirmation url
        $merchantUrls->setConfirmationUri($confirmationUrl);
        $merchantUrls->setPushUri($pushUri);
        $merchantUrls->setCheckoutValidationCallBackUri($validationUri);

        // get partner key
        $partnerKey = $this->helper->getPartnerKey();

        // we generate the order here, amount and items
        $paymentOrder = new CreateOrder();

        $paymentOrder->setLocale($this->getLocale()->getLocaleByCountryCode($countryCode));
        $paymentOrder->setCountryCode($countryCode);
        $paymentOrder->setCurrency($quote->getStore()->getCurrentCurrencyCode());
        $paymentOrder->setClientOrderNumber($refId);
        $paymentOrder->setMerchantData($this->generateMerchantData($quote));
        $paymentOrder->setMerchantSettings($merchantUrls);
        $paymentOrder->setCartItems($items);
        if ($this->helper->getSveaShippingActive($quote->getStoreId())) {
            $info = $this->shippingInfoFactory->create()->generateFromQuote($quote);
            $paymentOrder->setShippingInformation($info);
        }

        if ($partnerKey && !empty($partnerKey)) {
            $paymentOrder->setPartnerKey($partnerKey);
        }

        $this->handleOrderValidation($quote, $paymentOrder);

        $presetValuesProvider = $this->presetValuesProviderFactory->getProvider($isTestMode);
        $paymentOrder->setPresetValues($presetValuesProvider->getData());

        $this->handleRecurringStatus($quote, $paymentOrder);

        if ($reloadCredentials) {
            $this->checkoutApi->resetCredentials($quote->getStoreId());
        }

        // now call the api
        return $this->checkoutApi->createNewOrder($paymentOrder);
    }

    /**
     * @param GetOrderResponse $payment
     * @param Address $address
     * @param null $countryIdFallback
     * @return array
     */
    public function convertSveaAddressToMagentoAddress(GetOrderResponse $payment, Address $address)
    {
        if ($address=== null) {
            return [];
        }

        $streets = [];
        if (is_array($address->getAddressLines()) && !empty($address->getAddressLines())) {
            $streets = $address->getAddressLines();
        } else {
            $streets[] = $address->getStreetAddress();
        }

        if (!empty($address->getCoAddress())) {
            $streets[] = $address->getCoAddress();
        }

        if ($fullname = $payment->getShippingAddress()->getFullName() ?: []) {
            $fullname = explode(' ', $fullname);
        }

        $data = [
            'firstname' => $address->getFirstName() ?: ($fullname[0] ?? null),
            'lastname' => $address->getLastName() ?: ($fullname[1] ?? null),
            'telephone' => $payment->getPhoneNumber(),
            'email' => $payment->getEmailAddress(),
            'street' => $streets,
            'city' => $address->getCity(),
            'postcode' => $address->getPostalCode(),
            'country_id' => $payment->getCountryCode(),
        ];

        if ($address->getPostalCode()) {
            $data['postcode'] = $address->getPostalCode();
        }

        if ($payment->getCustomer()->getIsCompany()) {
            $data['company'] = $payment->getBillingAddress()->getFullName();
            if ($data['firstname'] == '') {
                $data['firstname'] = $data['company'];
            }
        }

        return $data;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @throws ClientException
     * @throws LocalizedException
     */
    public function cancelSveaPayment(\Magento\Payment\Model\InfoInterface $payment)
    {
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');
        if ($sveaOrderId) {

            // we reload the credentials using the right store view
            $this->orderManagementApi->resetCredentials($payment->getOrder()->getStoreId());

            $this->tryToCancelSveaOrder($sveaOrderId);
        } else {
            throw new LocalizedException(
                __('You need an svea payment ID to void.')
            );
        }
    }

    /**
     * @param $sveaOrderId
     * @throws ClientException
     * @throws LocalizedException
     */
    public function tryToCancelSveaOrder($sveaOrderId)
    {
        try {
            // we need order row ids, so we load the order from svea!
            $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not load svea order'));
        }

        if ($sveaOrder->canCancel() || $sveaOrder->canCancelAmount()) {
            // cancel it now!
            if ($sveaOrder->canCancel()) {
                $this->cancelSveaPaymentById($sveaOrderId);
            } else {
                $this->cancelSveaPaymentByIdAndAmount($sveaOrderId, $sveaOrder->getOrderAmount());
            }
        } else {

            // NOT ALL orders are cancelable, direct payments which gets an delivery directly in their system must be refunded instead!

            if ($sveaOrder->canRefund()) {
                $deliveryToRefund = $sveaOrder->getFirstRefundableDelivery();

                if (!$deliveryToRefund) {
                    throw new LocalizedException(
                        __('Could not cancel order. Not marked as cancelable in Svea, and its missing deliveries!')
                    );
                }

                switch ($deliveryToRefund->getRefundType()) {
                    case "rows":
                        // if we can refund we do it instead!
                        $paymentObj = new RefundPayment();
                        $paymentObj->setOrderRowIds($deliveryToRefund->getCreditableRowsIds());

                        // try to refund it now!
                        $this->orderManagementApi->refundPayment($paymentObj, $sveaOrderId, $deliveryToRefund->getId());
                        break;
                    case "amount":
                        $this->tryToRefundByAmount($sveaOrderId, $deliveryToRefund, $deliveryToRefund->getDeliveryAmount(), 0);
                        break;
                    default:
                        throw new LocalizedException(
                            __('Could not cancel order. Not marked as cancelable in Svea, and its missing deliveries!')
                        );
                }
            } else {
                throw new LocalizedException(
                    __('Could not cancel order. Not marked as cancelable in Svea!')
                );
            }
        }
    }

    /**
     * @param $sveaOrderId
     * @throws ClientException
     */
    public function cancelSveaPaymentById($sveaOrderId)
    {
        $this->orderManagementApi->cancelOrder($this->generateCancelOrderObject(), $sveaOrderId);
    }

    /**
     * @param $sveaOrderId
     * @param $amount
     * @throws ClientException
     */
    public function cancelSveaPaymentByIdAndAmount($sveaOrderId, $amount)
    {
        $payment = new CancelOrderAmount();
        $payment->setCancelledAmount($amount);
        $this->orderManagementApi->cancelOrderAmount($payment, $sveaOrderId);
    }

    protected function generateCancelOrderObject()
    {
        $obj = new CancelOrder();
        $obj->setIsCancelled(true);
        return $obj;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @throws ClientException
     * @throws LocalizedException
     */
    public function captureSveaPayment(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');
        if ($sveaOrderId) {

            /** @var Invoice $invoice */
            $invoice = $payment->getCapturedInvoice(); // we get this from Observer\PaymentCapture
            if (!$invoice) {
                throw new LocalizedException(__('Cannot capture online, no invoice set'));
            }

            // we reload the credentials using the right store view
            $this->orderManagementApi->resetCredentials($invoice->getOrder()->getStoreId());

            $precision = 8;
            $isFullDelivery = abs(round($invoice->getGrandTotal(), $precision) - round((float)$invoice->getOrder()->getGrandTotal(), $precision)) < PHP_FLOAT_EPSILON;
            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order.'));
            }

            $canDeliver = $sveaOrder->canDeliver() || $sveaOrder->canDeliverPartially();
            if (!$canDeliver) {
                // so we guess its a direct payment, since you cant deliver this order.
                // we save some info if client wants to refund later!

                $delivery = $sveaOrder->getFirstDeliveredDelivery();
                if ($delivery) {
                    // we set the id here so we can refund it later :)
                    $payment->setAdditionalInformation('svea_delivery_id', $delivery->getId());
                    $payment->setTransactionId($delivery->getId());
                    $invoice->setTransactionId($delivery->getId());
                    return;
                }

                // If no delivery exists then this order is currently not deliverable for other reasons,
                // Likely payment review is required due to high invoice amount
                throw new LocalizedException(
                    __(
                        'Can not capture. Svea has not marked the order as Deliverable. Review the order in Svea.'
                    )
                );
            }

            $paymentObj = new DeliverOrder();
            if ($isFullDelivery) {
                $rowsToDeliver = [];
            } else {
                // generate items
                $this->items->addSveaItemsByInvoice($invoice);

                // lets att the invoice fee if it exists!
                if ($invoiceFeeRow = $sveaOrder->getInvoiceFeeRow()) {
                    $this->items->addInvoiceFeeItem($invoiceFeeRow);
                    $orderPayment = $invoice->getOrder()->getPayment();
                    $additionalInfo = $orderPayment->getAdditionalInformation();
                    $additionalInfo['svea_invoice_fee_invoiced'] = true;
                    $orderPayment->setAdditionalInformation($additionalInfo);
                }

                // We validate the items before we send them to Svea. This might throw an exception!
                try {
                    $this->items->validateTotals($invoice->getGrandTotal());
                } catch (\Exception $e) {
                    throw new LocalizedException(__("Total amount not matching.", $e));
                }

                $rowsToDeliver = $this->items->getMatchingRows($sveaOrder->getCartItems(), $this->items->getCart(), false);
                if (!$this->items->itemsMatching($rowsToDeliver, $this->items->getCart())) {
                    // we must update/add items not matching!
                    // since the order may contain shipping method and discount that varies depending on the grand total
                    // we will need to update ALL items
                    foreach ($rowsToDeliver as $key => $sveaItem) {
                        // this should never happen... but if it does for whatever reason, we fix it
                        if ($sveaItem->getQuantity() == 0) {
                            unset($rowsToDeliver[$key]);
                            continue;
                        }

                        try {
                            $item = $this->items->getMagentoRowBySveaItem($sveaItem, $this->items->getCart());

                            $updateRow = new OrderRow();
                            $updateRow->setName($item->getName())
                                ->setArticleNumber($item->getArticleNumber())
                                ->setQuantity($item->getQuantity())
                                ->setUnitPrice($item->getUnitPrice())
                                ->setVatPercent($item->getVatPercent())
                                ->setDiscountPercent($item->getDiscountPercent())
                                ->setUnit($item->getUnit());

                            $this->orderManagementApi->updateOrderRow($updateRow, $sveaOrderId, $sveaItem->getRowNumber());
                            $item->setRowNumber($sveaItem->getRowNumber());
                            $rowsToDeliver[$key] = $item;
                        } catch (LocalizedException $e) {
                            throw $e;
                        } catch (\Exception $e) {
                            throw new LocalizedException(__("Could not to a partial delivery, couldn't update row at Svea. Please do it manually. %1", $e->getMessage()));
                        }
                    }

                    // here we loop and add all missing products!
                    $itemsToAdd = $this->items->getMissingItems($rowsToDeliver, $this->items->getCart());
                    foreach ($itemsToAdd as $item) {
                        try {
                            $addRow = new OrderRow();
                            $addRow->setName($item->getName())
                                ->setArticleNumber($item->getArticleNumber())
                                ->setQuantity($item->getQuantity())
                                ->setUnitPrice($item->getUnitPrice())
                                ->setVatPercent($item->getVatPercent())
                                ->setDiscountPercent($item->getDiscountPercent())
                                ->setUnit($item->getUnit());

                            $rowId = $this->orderManagementApi->addOrderRow($addRow, $sveaOrderId);
                            $addRow->setRowNumber($rowId);

                            $rowsToDeliver[] = $addRow;
                        } catch (\Exception $e) {
                            throw new LocalizedException(__("Could not to a partial delivery, couldn't add missing row at Svea. Please do it manually. Error %1", $e->getMessage()));
                        }
                    }
                }
            }

            // capture/deliver it now!
            $paymentObj->setOrderRowIds($this->items->getOrderRowNumbers($rowsToDeliver));
            $paymentObj->setRowDeliveryOptions($this->items->getOrderRowDeliveryOptions($rowsToDeliver));
            $response = $this->orderManagementApi->deliverOrder($paymentObj, $sveaOrderId);

            // save queue_id, we need it later! if a refund will be made
            $payment->setAdditionalInformation('svea_queue_id', $response->getQueueId());
            $payment->setTransactionId($response->getQueueId());
            $invoice->setTransactionId($response->getQueueId());
        } else {
            throw new LocalizedException(__('You need an svea payment ID to capture.'));
        }
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param $amount
     * @throws ClientException
     * @throws LocalizedException
     */
    public function refundSveaPayment(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $deliveryId = $payment->getAdditionalInformation('svea_delivery_id');
        $queueId = $payment->getAdditionalInformation('svea_queue_id');
        $sveaOrderId = $payment->getAdditionalInformation('svea_order_id');

        if ($sveaOrderId && ($queueId || $deliveryId)) {

            // we reload the credentials using the right store view
            $this->orderManagementApi->resetCredentials($payment->getCreditMemo()->getStoreId());

            try {
                // we need order row ids, so we load the order from svea!
                $sveaOrder = $this->orderManagementApi->getOrder($sveaOrderId);
            } catch (\Exception $e) {
                throw new LocalizedException(__('Could not load svea order'));
            }

            $deliveryToRefund = null;
            if ($queueId) {

                // not sure if this is good, but we have the  queue_id, and can retrieve the delivery from it!
                // or we could just loop through $sveaOrder->getDeliveries() and take the first one... that would be one less api call!
                $responseArray = $this->orderManagementApi->getTask($queueId);
                if (isset($responseArray['Status']) && $responseArray['Status'] === "InProgress") {
                    throw new LocalizedException(__("This delivery is still in progress. Try again soon."));
                }

                if (!isset($responseArray['Deliveries'][0])) {
                    throw new LocalizedException(__("Found no deliveries to refund on. Please refund offline, and do the rest manually in Svea."));
                }

                $deliveryArray = $responseArray['Deliveries'][0];
                $deliveryToRefund = new GetDeliveryResponse($deliveryArray);
            } else {
                foreach ($sveaOrder->getDeliveries() as $delivery) {
                    if ($delivery->getId() == $deliveryId) {
                        $deliveryToRefund = $delivery;
                        break;
                    }
                }
            }

            // wasn't found :/
            if (!$deliveryToRefund) {
                throw new LocalizedException(__("Found no deliveries to refund on. Please refund offline, and do the rest manually in Svea."));
            }

            // the creditmemo from the payment/invoice
            /** @var Creditmemo $creditMemo */
            $creditMemo = $payment->getCreditMemo();

            $creditMemoTotal = $creditMemo->getGrandTotal();
            $invoiceFeeRow = $deliveryToRefund->getInvoiceFeeRow();

            // convert credit memo to svea items!
            $this->items->addSveaItemsByCreditMemo($creditMemo);

            // we only use this to see if its a full refund or not
            $creditAbleSveaRows = $deliveryToRefund->getCreditableItems();

            // when the delivery can't refund but can cancel
            if (empty($creditAbleSveaRows) && (!$deliveryToRefund->canRefund() || $sveaOrder->canCancelAmount())) {
                // sometimes we can't know if its a full refund, since when you only can cancel amount, getCreditableItems will be empty!
                $isFullRefund = $this->isFullRefund($this->items->getCart(), $deliveryToRefund->getCartItems());
            } else {
                $isFullRefund = $this->isFullRefund($this->items->getCart(), $creditAbleSveaRows);
            }

            // we only refund invoice fee if its a full refund!
            if ($isFullRefund) {
                // lets add the invoice fee if it exists, since its a full refund!
                if ($invoiceFeeRow) {
                    $this->items->addInvoiceFeeItem($invoiceFeeRow);
                }
            } else {

                // if not a full refund and there is a invoice fee, it has to be added as an adjustment fee!
                if ($invoiceFeeRow) {
                    $invoiceFee = ($invoiceFeeRow->getUnitPrice() / 100);

                    // invoice fee is never removed from svea in partial refunds, because some issues we have in magento
                    if ($creditMemo->getAdjustmentNegative() < $invoiceFee) {
                        throw new LocalizedException(__('This is a partial credit memo. You have to add an adjustment fee that is the same amount as the svea invoice fee.'));
                    }
                }
            }

            // We validate the items before we send them to Svea. This might throw an exception!
            $this->items->validateTotals($creditMemoTotal);
            $rowsToRefund = $this->items->getMatchingRows($deliveryToRefund->getCartItems(), $this->items->getCart());

            // if its a partial refund, containing discount!
            if (!$isFullRefund && $this->items->containsDiscount($rowsToRefund)) {

                // if we can count how much the maximum amount possible to credit in svea, then this could work, and it has the correct flags
                $amountToCredit = $this->fixPrice($creditMemo->getGrandTotal());
                if ($deliveryToRefund->canDeliveryRefundByAmount() || $sveaOrder->canCancelAmount()) {
                    if ($deliveryToRefund->canDeliveryRefundByAmount()) {
                        $this->tryToRefundByAmount($sveaOrderId, $deliveryToRefund, $amountToCredit, $this->items->getMaxVat());
                    } else {
                        $this->cancelDeliveryAmount($sveaOrderId, $amountToCredit);
                    }

                    return;
                } else {
                    throw new LocalizedException(__("We can't do partial refunds on this invoice. Please refund offline, and do the rest manually in Svea."));
                }
            }

            // we try to refund by amount if items are not matching, i.e you want to redfund 1 quantity when you have 2.
            // or we try to cancel by amount!

            try {
                $itemQuantityMatching = $this->items->itemsMatching($rowsToRefund, $this->items->getCart(), true);

                // if quantities are not matching, and we can refund amount, we do it!
                if ($deliveryToRefund->canDeliveryRefundByAmount() && !$itemQuantityMatching) {
                    // we calculate the amount to send to svea, according to the rows existing in the svea delivery and magento!
                    $amountToCredit = $this->fixPrice($creditMemo->getGrandTotal());
                    $this->tryToRefundByAmount($sveaOrderId, $deliveryToRefund, $amountToCredit, $this->items->getMaxVat());
                    return;
                }

                // if quantities not matching and we can cancel order amount, we do it!
                if ($sveaOrder->canCancelAmount() && !$itemQuantityMatching) {
                    $amountToCancel = $this->items->getAmountByItems($rowsToRefund);
                    $this->cancelDeliveryAmount($sveaOrderId, $amountToCancel);
                    return;
                }

                // if we cant do a refund at all, but we can cancel amount, we do it!
                if (!$deliveryToRefund->canRefund() && $sveaOrder->canCancelAmount()) {
                    $amountToCancel = $this->items->getAmountByItems($rowsToRefund);
                    $this->cancelDeliveryAmount($sveaOrderId, $amountToCancel);
                    return;
                }

                if (!$deliveryToRefund->canRefund() && !$sveaOrder->canCancelAmount()) {
                    throw new BaseException(__("Can't refund this invoice, found o refund or cancel flag. Please refund offline, and do the rest manually in Svea."));
                }

                if (!$itemQuantityMatching && !$deliveryToRefund->canDeliveryRefundByAmount()) {
                    throw new BaseException(__("Can't do a partial refund for this invoice."));
                }
            } catch (\Exception $e) {
                throw new LocalizedException(__($e->getMessage()));
            }

            if ($deliveryToRefund->canDeliveryRefundByAmount()) {
                // we should refound amount

                $amountToCredit = $this->fixPrice($creditMemo->getGrandTotal());
                $this->tryToRefundByAmount($sveaOrderId, $deliveryToRefund, $amountToCredit, $this->items->getMaxVat());
            } elseif (!$deliveryToRefund->canDeliveryRefundByAmount() && $deliveryToRefund->canRefund()) {
                // we should refund rows;
                $paymentObj = new RefundPayment();
                $paymentObj->setOrderRowIds($this->items->getOrderRowNumbers($rowsToRefund));

                // try to refund it now!
                $this->orderManagementApi->refundPayment($paymentObj, $sveaOrderId, $deliveryToRefund->getId());
            } else {
                throw new LocalizedException(
                    __('Could not refund invoice. This delivery is not marked as refundable in Svea.')
                );
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Missing Svea ID or delivery id. Please handle this manually.')
            );
        }
    }

    /**
     * @param $sveaOrderId
     * @param GetDeliveryResponse $delivery
     * @param $amountToCredit
     * @throws ClientException
     */
    protected function tryToRefundByAmount($sveaOrderId, GetDeliveryResponse $delivery, $amountToCredit, $maxVat)
    {
        if ($amountToCredit > $delivery->getDeliveryAmount()) {
            $amountToCredit = $delivery->getDeliveryAmount();
        }

        if ($delivery->canRefundAmount()) {
            $paymentObj = new RefundPaymentAmount();
            $paymentObj->setCreditedAmount($amountToCredit);
            $this->orderManagementApi->refundPaymentAmount($paymentObj, $sveaOrderId, $delivery->getId());
            return;
        }

        if ($delivery->canRefundNewRow()) {
            $paymentObj = new RefundNewCreditRow();
            $paymentObj->setName(__("Refund"));
            $paymentObj->setUnitPrice($amountToCredit);
            $paymentObj->setVatPercent($maxVat * 100);

            $this->orderManagementApi->refundNewCreditRow($paymentObj, $sveaOrderId, $delivery->getId());
            return;
        }
    }

    /**
     * @param $sveaOrderId
     * @param $amount
     * @throws LocalizedException
     */
    public function cancelDeliveryAmount($sveaOrderId, $amount)
    {
        $paymentObj = new CancelOrderAmount();
        $paymentObj->setCancelledAmount($amount);
        try {
            $this->orderManagementApi->cancelOrderAmount($paymentObj, $sveaOrderId);
        } catch (\Exception $e) {
            throw new LocalizedException(__("Can't cancel delivery amount. Use the Offline button and do the rest manually in Svea."));
        }
    }

    /**
     * @param $creditMemoItems array
     * @param $deliveryItems array
     * @return bool
     */
    protected function isFullRefund($creditMemoItems, $deliveryItems)
    {
        $refs = [];
        foreach ($creditMemoItems as $creditMemoItem) {
            $refs[$creditMemoItem->getArticleNumber()] = $creditMemoItem;
        }

        foreach ($deliveryItems as $item) {
            /** @var $item OrderRow */
            if ($item->getName() === "InvoiceFee") {
                continue;
            }

            if (!array_key_exists($item->getArticleNumber(), $refs)) {
                return false;
            }
            /** @var $creditMemo OrderRow */
            $creditMemo = $refs[$item->getArticleNumber()];
            if ($creditMemo->getQuantity() != $item->getQuantity()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $paymentId
     * @return GetOrderResponse
     * @throws ClientException
     */
    public function loadSveaOrderById($paymentId, $saveIframe = false, $storeId = null)
    {
        if ($storeId) {
            $this->checkoutApi->resetCredentials($storeId);
        }

        $order =  $this->checkoutApi->getOrder($paymentId);
        if ($saveIframe) {
            $this->setIframeSnippet($order->getGui()->getSnippet());
        }

        return $order;
    }

    /**
     * @param $price
     * @return float|int
     */
    protected function fixPrice($price)
    {
        return $price * 100;
    }

    /**
     * @return Checkout
     */
    public function getPaymentApi()
    {
        return $this->checkoutApi;
    }

    public function setIframeSnippet($snippet)
    {
        // Set nonce attribute on any inline script in the snippet
        $generatedNonce = $this->cspNonceProvider->generateNonce();
        $snippet = preg_replace_callback('/<script(.*?)>/si', function ($matches) use ($generatedNonce) {
            if (strpos($matches[1], 'nonce=') === false && strpos($matches[1], 'src=') === false) {
                return '<script' . $matches[1] . ' nonce="' . $generatedNonce . '">';
            }
            return $matches[0];
        }, $snippet);

        $this->iframeSnippet = $snippet;
    }

    public function getIframeSnippet()
    {
        return $this->iframeSnippet;
    }

    public function getLocale()
    {
        return $this->_locale;
    }

    public function getRefHelper()
    {
        return $this->sveaCheckoutReferenceHelper;
    }

    /**
     * Set recurring flag on data object based on config and quote payment contents
     *
     * @param Quote $quote
     * @param CreateOrder $paymentOrder
     */
    private function handleRecurringStatus(Quote $quote, CreateOrder $paymentOrder)
    {
        if (!$this->helper->getRecurringPaymentsActive()) {
            return;
        }

        $payment = $quote->getPayment();
        $sveaRecurringInfo = $payment->getAdditionalInformation('svea_recurring_info') ?? [];
        $recurringEnabled = $sveaRecurringInfo['enabled'] ?? false;

        $paymentOrder->setRecurring(!!$recurringEnabled);
    }

    /**
     * Adds minimum age if we either have a global minimum age set or products with minimum age
     *
     * @param Quote $quote
     * @param CreateOrder $paymentOrder
     * @return void
     */
    private function handleOrderValidation(Quote $quote, HasOrderValidationInterface $paymentOrder): void
    {
        $storeId = $quote->getStoreId();
        if (!$this->helper->getMinimumAgeRestrictionActive($storeId)) {
            return;
        }

        $globalMinimumAge = $this->helper->getGlobalMinimumAge($storeId);
        $validation = $this->orderValidationFactory->create();
        if ($globalMinimumAge > 0) {
            $validation->setMinAge($globalMinimumAge);
            $paymentOrder->setValidation($validation);
            return;
        }

        // Use a product collection to find highest minimum age value in the cart
        $productIds = $quote->getItemsCollection()->getColumnValues('product_id');
        $productCollection = $this->productCollectionFactory->create();
        $productCollection
            ->addFieldToFilter('entity_id', ['in' => $productIds])
            ->addAttributeToSelect('svea_minimum_age')
        ;

        $highestMinimumAge = (int)max($productCollection->getColumnValues('svea_minimum_age'));
        if ($highestMinimumAge === 0) {
            return;
        }

        $validation->setMinAge($highestMinimumAge);
        $paymentOrder->setValidation($validation);
    }
}

<?php

namespace Svea\Checkout\Model;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\GetOrderResponse;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Helper\Data as SveaHelper;
use \Exception as BaseException;
use Svea\Checkout\Model\Shipping\Carrier;

class Checkout extends Onepage
{
    protected $_paymentMethod = 'sveacheckout';

    /** @var CheckoutContext $context */
    protected $context;

    protected $_allowedCountries;

    protected $_doNotMarkCartDirty  = false;

    /**
     * @param CheckoutContext $context
     */
    public function setCheckoutContext(CheckoutContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->context->getHelper();
    }

    /**
     * @return CheckoutOrderNumberReference
     */
    public function getRefHelper()
    {
        return $this->context->getSveaCheckoutReferenceHelper();
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->_logger;
    }

    /**
     * @param bool $reloadIfCurrencyChanged
     * @param bool $reloadIfCountryChanged
     *
     * @return $this
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function initCheckout()
    {
        if (!($this->context instanceof CheckoutContext)) {
            throw new BaseException("Svea Context must be set first!");
        }

        $quote  = $this->getQuote();
        $this->checkCart();

        //init checkout
        $customer = $this->getCustomerSession();
        if ($customer->getId()) {
            $quote->assignCustomer($customer->getCustomerDataObject()); //this will set also primary billing/shipping address as billing address
            $quote->setCustomer($customer->getCustomerDataObject());
        }

        $this->setAddressDefaults();

        $payment = $quote->getPayment();

        //force payment method  to our payment method
        $paymentMethod     = $payment->getMethod();
        if (!$paymentMethod || $paymentMethod != $this->_paymentMethod) {
            $payment->unsMethodInstance()->setMethod($this->_paymentMethod);
        }

        // Set shipping method. It's required!
        $selectedShippingMethod = $this->checkAndChangeShippingMethod();
        if ($selectedShippingMethod === false) {
            throw new LocalizedException(__('Missing shipping method.'));
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getQuoteSignature()
    {
        return $this->getHelper()->generateHashSignatureByQuote($this->getQuote());
    }

    /**
     * @return bool
     * @throws CheckoutException
     */
    public function checkCart()
    {
        $quote = $this->getQuote();

        //@see OnePage::initCheckout
        if ($quote->getIsMultiShipping()) {
            $quote->setIsMultiShipping(false)->removeAllAddresses();
        }

        if (!$this->getHelper()->isEnabled()) {
            $this->throwRedirectToCartException('The Svea Checkout is not enabled, please use an alternative checkout method.');
        }

        if (!$this->getAllowedCountries()) {
            $this->throwRedirectToCartException('The Svea Checkout is NOT available (no allowed country), please use an alternative checkout method.');
        }

        if (!$quote->hasItems()) {
            $this->throwRedirectToCartException('There are no items in your cart.');
        }

        if ($quote->getHasError()) {
            $this->throwRedirectToCartException('The cart contains errors.');
        }

        if (!$quote->validateMinimumAmount()) {
            $error = $this->getHelper()->getStoreConfig('sales/minimum_order/error_message');
            if (!$error) {
                $error = 'Subtotal must exceed minimum order amount.';
            }

            $this->throwRedirectToCartException($error);
        }

        if ($quote->getGrandTotal() <= 0) {
            $this->throwRedirectToCartException("Subtotal cannot be 0. Please choose another payment method.");
        }

        return true;
    }

    /**
     * Sets default country and post code on quote addresses if needed
     *
     * @return void
     */
    private function setAddressDefaults(): void
    {
        $quote = $this->getQuote();
        $shippingAddress = $quote->getShippingAddress();
        $billingAddress = $quote->getBillingAddress();
        $billingAddressDataSet = !!$billingAddress->getCountryId() && !!$billingAddress->getPostcode();
        $shippingAddressDataSet = true;
        $mainAddress = $shippingAddress;
        if (!$quote->isVirtual()) {
            $mainAddress = $billingAddress;
            $shippingAddressDataSet = !!$shippingAddress->getCountryId() && !!$shippingAddress->getPostcode();
        }

        if ($billingAddressDataSet && $shippingAddressDataSet) {
            return;
        }

        $country = $mainAddress->getCountry() ?? $this->getHelper()->getDefaultCountry();
        $blankAddress = $this->getBlankAddress($country);

        $billingAddress->addData($blankAddress);
        if (!$quote->isVirtual()) {
            $shippingAddress->addData($blankAddress)->setCollectShippingRates(true);
            $extAttributes = $quote->getExtensionAttributes();
            if (null === $extAttributes) {
                $extAttributes = $this->context->getCartExtensionFactory()->create();
            }
    
            $extAttributes->setShippingAssignments([$this->context->getShippingAssignmentProcessor()->create($quote)]);
            $quote->setExtensionAttributes($extAttributes);
        }
    }

    public function checkAndChangeShippingMethod()
    {
        $quote = $this->getQuote();
        if ($quote->isVirtual()) {
            return true;
        }

        // Svea shipping is handled later on instead
        if ($this->context->getHelper()->getSveaShippingActive()) {
            return true;
        }

        //this is needed by shipping method with minimum amount
        $shipping = $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        $allRates = $shipping->getAllShippingRates();

        if (!count($allRates)) {
            return false;
        }

        $rates = [];
        foreach ($allRates as $rate) {
            /** @var $rate Quote\Address\Rate  **/
            $rates[$rate->getCode()] = $rate->getCode();
        }

        // check if selected shipping method exists
        $method = $shipping->getShippingMethod();
        if ($method && isset($rates[$method])) {
            return $method;
        }

        // check if default shipping method exists, use it then!
        $method = $this->getHelper()->getDefaultShippingMethod();
        if ($method && isset($rates[$method])) {
            $shipping->setShippingMethod($method);
            return $method;
        }

        // fallback, use first shipping method found
        $rate = $allRates[0];
        $method = $rate->getCode();
        $shipping->setShippingMethod($method);
        return $method;
    }

    /**
     * @param string $country
     * @return array
     */
    private function getBlankAddress(string $country): array
    {
        $localeHelper =  $this->context->getSveaLocale();
        $defaultData = $localeHelper->getDefaultDataByCountryCode($country);
        $defaultPostcode = $defaultData['PostalCode'] ?? '';
        $blankAddress = [
            'customer_address_id' => 0,
            'save_in_address_book' => 0,
            'same_as_billing' => 0,
            'street' => '',
            'city' => '',
            'postcode' => $defaultPostcode,
            'region_id' => '',
            'country_id' => $country
        ];
        return $blankAddress;
    }

    /**
     * @return array
     */
    private function getAllowedCountries()
    {
        if (null === $this->_allowedCountries) {
            $this->_allowedCountries = $this->getHelper()->getCountries();
            if ($this->getHelper()->getInternationalFlowActive()) {
                $this->_allowedCountries = $this->getHelper()->getGeneralAllowedCountries();
            }
        }

        return $this->_allowedCountries;
    }

    /**
     * @return $this
     * @throws CheckoutException
     * @throws LocalizedException
     */
    public function initSveaCheckout()
    {
        $quote = $this->getQuote();
        $this->initSveaSession();
        $this->setSveaShippingDefault();

        // we need a reserved order id, since we need to send the order id to svea in validateOrder.
        if (!$quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }
        $sveaHandler = $this->getSveaPaymentHandler()->assignQuote($quote); // this will also validate the quote!

        // a signature is a md5 hashed value of the customer quote. Using this we can store the hash in session and compare the values
        $newSignature = $this->getHelper()->generateHashSignatureByQuote($quote);

        //check session for Svea Order Id
        $sessionSveaOrderId = (int)$this->getRefHelper()->getSveaOrderId();
        $sveaOrderId = $sessionSveaOrderId;
        $quoteSveaOrderId = (int)$quote->getSveaOrderId();

        // check if we already have started a payment flow with svea
        if ($sveaOrderId && !$this->getRefHelper()->paymentIsExpired() && $sessionSveaOrderId === $quoteSveaOrderId) {
            try {

                // here we should check if we need to update the svea order!
                if ($sveaHandler->checkIfPaymentShouldBeUpdated($newSignature, $this->getRefHelper()->getQuoteSignature())) {
                    // try to update svea order data
                    $sveaHandler->updateCheckoutPaymentByQuoteAndOrderId($quote, $sveaOrderId);

                    // Update new svea quote signature!
                    $this->getRefHelper()->setQuoteSignature($newSignature);
                } else {

                    // if we should update the order, we also set the svea iframe here
                    $sveaOrder = $sveaHandler->loadSveaOrderById($sveaOrderId, true);

                    // do some validations!
                    // if the svea order status is final, and the client order number matches with the current quote
                    // we will cancel this svea order and throw an exception ( a new svea order will be created),
                    $this->validateCheckoutSveaOrder($sveaOrder);
                }
            } catch (\Exception $e) {

                // We log this!
                $this->getLogger()->error("Trying to create an new order because we could not Update Svea Checkout Payment for ID: {$sveaOrderId}, Error: {$e->getMessage()} (see exception.log)");
                $this->getLogger()->error($e);
                // If we couldn't update the svea order flow for any reason, we try to create an new one...

                // remove sessions, remove client order number
                $this->getRefHelper()->unsetSessions();

                // will help us reassure client order number will be unique
                $this->getRefHelper()->addToSequence();

                try {
                    // this will create an api call to svea and initiaze an new payment
                    $sveaOrder = $this->initValidOrder($quote);
                    $this->handleNewOrderData($quote, $sveaOrder, $newSignature);
                } catch (\Exception $e2) {
                    $this->getLogger()->error("Could not create an new order again. " . $e2->getMessage());
                    $this->getLogger()->error($e2);

                    $this->throwRedirectToCartException("An error occurred, try again.", $e2);
                }
            }
        } else {
            // when a customer visits checkout first time

            try {
                $sveaOrder = $this->initValidOrder($quote);
                $this->handleNewOrderData($quote, $sveaOrder, $newSignature);
            } catch (\Exception $e) {
                $this->getLogger()->error("Could not create an new order: " . $e->getMessage());
                $this->getLogger()->error($e);

                // remove sessions, remove client order number
                $this->getRefHelper()->unsetSessions();

                // will help us reassure client order number will be unique
                $this->getRefHelper()->addToSequence();

                $this->throwRedirectToCartException("An error occurred, try again.", $e);
            }
        }

        return $this;
    }

    /**
     * @param Quote $quote
     * @throws ClientException
     * @throws BaseException
     */
    private function initAndValidateSveaOrder(Quote $quote): GetOrderResponse
    {
        $sveaHandler = $this->getSveaPaymentHandler();
        // this will create an api call to svea and initiaze a new payment
        $sveaOrder = $sveaHandler->initNewSveaCheckoutPaymentByQuote($quote);
        $this->validateCheckoutSveaOrder($sveaOrder);
        return $sveaOrder;
    }

    /**
     * Runs recursively until we have a valid order
     *
     * @param Quote $quote
     * @return GetOrderResponse
     * @throws ClientException
     * @throws BaseException
     */
    private function initValidOrder(Quote $quote): GetOrderResponse
    {
        try {
            $sveaOrder = $this->initAndValidateSveaOrder($quote);
        } catch (OrderValidationException $e) {
            // remove sessions, remove client order number
            $this->getRefHelper()->unsetSessions();
            // will help us reassure client order number will be unique
            $this->getRefHelper()->addToSequence();
            // Rerun the function
            $sveaOrder = $this->initValidOrder($quote);
        }
        return $sveaOrder;
    }

    /**
     * @param $sveaOrder GetOrderResponse
     * @throws OrderValidationException
     */
    private function validateCheckoutSveaOrder($sveaOrder)
    {
        if ($sveaOrder->getStatus() === 'Final') {
            if ($this->getRefHelper()->clientIdIsMatching($sveaOrder->getClientOrderNumber())) {
                try {
                    $this->context->getSveaOrderHandler()->tryToCancelSveaOrder($sveaOrder->getOrderId());
                } catch (\Exception $e) {
                    // do nothing!
                }
            }

            throw new OrderValidationException(__("This order is already placed in Svea. Creating a new."));
        }

        if ($sveaOrder->getStatus() === "Cancelled") {
            throw new OrderValidationException(__("This order is already placed in Svea and has been cancelled."));
        }
    }

    /**
     * @param $sveaOrderId
     * @throws ClientException
     * @throws LocalizedException
     */
    public function updateSveaPayment($sveaOrderId)
    {
        $quote       = $this->getQuote();
        $sveaHandler = $this->getSveaPaymentHandler()->assignQuote($quote); // this will also validate the quote!

        // a signature is a md5 hashed value of the customer quote. Using this we can store the hash in session and compare the values
        $newSignature = $this->getHelper()->generateHashSignatureByQuote($quote);

        $sveaHandler->updateCheckoutPaymentByQuoteAndOrderId($quote, $sveaOrderId);

        // Update new svea quote signature!
        $this->getRefHelper()->setQuoteSignature($newSignature);
    }

    /**
     * Set shipping method to quote, if needed
     *
     * @param string $methodCode
     * @param $postcode
     *
     * @return void
     */
    public function updateShippingMethod($methodCode, $postcode = null)
    {
        $quote = $this->getQuote();
        if ($quote->isVirtual()) {
            return;
        }
        $shippingAddress = $quote->getShippingAddress();
        if ($methodCode != $shippingAddress->getShippingMethod() || ($postcode != $shippingAddress->getPostcode())) {
            $this->ignoreAddressValidation();
            $shippingAddress->setShippingMethod($methodCode)->setCollectShippingRates(true);

            if (null !== $postcode) {
                $shippingAddress->setPostcode($postcode);
            }

            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        }
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @return void
     */
    private function ignoreAddressValidation()
    {
        $quote = $this->getQuote();
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        if (!$quote->getIsVirtual()) {
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }

    /**
     * @param GetOrderResponse $sveaOrder
     * @param Quote $quote
     * @return mixed
     * @throws BaseException
     */
    public function placeOrder(GetOrderResponse $sveaOrder, Quote $quote)
    {
        //prevent observer to mark quote dirty, we will check here if quote was changed and, if yes, will redirect to checkout
        $this->setDoNotMarkCartDirty(true);

        //this will be saved in order
        $quote->setSveaOrderId($sveaOrder->getOrderId());

        // we convert the addresses
        $shipping = $this->getSveaPaymentHandler()->convertSveaAddressToMagentoAddress($sveaOrder, $sveaOrder->getShippingAddress());

        // We should fetch postcode from quote address, not from svea shipping data
        // Handled here, since we cannot generate plugin NWT_Unifaun plugin of SVEA module is not used
        if ($quote->getShippingAddress() &&
            $quote->getShippingAddress()->getShippingMethod() == 'nwtunifaun_udc' &&
            isset($shipping['postcode'])
        ) {
            unset($shipping['postcode']);
        }

        $billing = $this->getSveaPaymentHandler()->convertSveaAddressToMagentoAddress($sveaOrder, $sveaOrder->getBillingAddress());

        // we set the addresses
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->addData($billing)
            ->setCustomerAddressId(0)
            ->setSaveInAddressBook(0)
            ->setShouldIgnoreValidation(true);

        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->addData($shipping)
            ->setSameAsBilling(1)
            ->setCustomerAddressId(0)
            ->setSaveInAddressBook(0)
            ->setShouldIgnoreValidation(true);

        $quote->setCustomerEmail($billingAddress->getEmail());

        $customer      = $quote->getCustomer(); //this (customer_id) is set into self::init
        $createCustomer = false;

        if ($customer && $customer->getId()) {
            $quote->setCheckoutMethod(self::METHOD_CUSTOMER)
                ->setCustomerId($customer->getId())
                ->setCustomerEmail($customer->getEmail())
                ->setCustomerFirstname($customer->getFirstname())
                ->setCustomerLastname($customer->getLastname())
                ->setCustomerIsGuest(false);
        } else {
            //checkout method
            $quote->setCheckoutMethod(self::METHOD_GUEST)
                ->setCustomerId(null)
                ->setCustomerEmail($billingAddress->getEmail())
                ->setCustomerFirstname($billingAddress->getFirstname())
                ->setCustomerLastname($billingAddress->getLastname())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID);

            // register the customer, if its required, the customer will then be registered after order is placed
            if ($billingAddress->getEmail() && $this->getHelper()->registerCustomerOnCheckout()) {
                if (!$this->_customerEmailExists($billingAddress->getEmail(), $quote->getStore()->getWebsiteId())) {
                    $createCustomer = true;
                }
            }
        }

        //set payment
        $payment = $quote->getPayment();
        $customerReference = $sveaOrder->getCustomerReference();
        if ($customerReference) {
            $payment->setAdditionalInformation('svea_customer_reference', $customerReference);
        }

        //force payment method
        if (!$payment->getMethod() || $payment->getMethod() != $this->_paymentMethod) {
            $payment->unsMethodInstance()->setMethod($this->_paymentMethod);
        }

        $paymentData = new DataObject([
            'svea_order_id' => $sveaOrder->getOrderId(),
            'country_id' => $shippingAddress->getCountryId(),
            'is_company' => $sveaOrder->getCustomer()->getIsCompany()
        ]);

        /** @var \Svea\Checkout\Model\Payment\Method\Checkout $method */
        $method = $payment->getMethodInstance();
        $method->assignData($paymentData);
        $quote->setSveaOrderId($sveaOrder->getOrderId()); //this is used by pushAction

        // we need to add invoice fee here to order if its enabled
        if ($sveaInvoiceFeeRow = $this->getInvoiceFeeRow($sveaOrder->getCartItems())) {
            $fee  = $sveaInvoiceFeeRow->getUnitPrice() / 100;
            $quote->setSveaInvoiceFee($fee);
            $quote->getBillingAddress()->setSveaInvoiceFee($fee);
            $quote->getShippingAddress()->setSveaInvoiceFee($fee);

            $quote->collectTotals();
        }

        //- do not recollect totals
        $quote->setTotalsCollectedFlag(true);

        // Schedule first recurring order, if recurring payment has been chosen by customer
        $recurringEnabled = $sveaOrder->getRecurring();
        if ($recurringEnabled) {
            $recurringToken = $sveaOrder->getRecurringToken();
            $this->context->getRecurringInfoService()->scheduleNextRecurringOrder($quote, $recurringToken);
        }

        //!
        // Now we create the order from the quote
        $order = $this->quoteManagement->submit($quote);

        $this->_eventManager->dispatch(
            'checkout_type_onepage_save_order_after',
            ['order' => $order, 'quote' => $this->getQuote()]
        );

        if ($this->getHelper()->isSendOrderEmail() && $order->getCanSendNewEmailFlag()) {
            try {
                $this->orderSender->send($order);
            } catch (\Exception $e) {
                $this->_logger->critical($e);
            }
        }

        $this->_eventManager->dispatch(
            'checkout_submit_all_after',
            [
                'order' => $order,
                'quote' => $this->getQuote()
            ]
        );

        if ($recurringEnabled) {
            $this->context->getRecurringInfoService()->saveNewOrderRecurringInfo($order);
        }

        if ($createCustomer) {
            //@see Magento\Checkout\Controller\Account\Create
            try {
                $this->createCustomer($order->getId(), $shipping, $billing);
            } catch (\Exception $e) {
                $this->_logger->error(__("Order %1, cannot create customer [%2]: %3", $order->getIncrementId(), $order->getCustomerEmail(), $e->getMessage()));
                $this->_logger->critical($e);
            }
        }

        if ($order->getCustomerEmail() && $this->getHelper()->subscribeNewsletter($payment)) {
            try {
                //subscribe to newsletter
                $this->orderSubscribeToNewsLetter($order);
            } catch (\Exception $e) {
                $this->_logger->error("Cannot subscribe customer ({$order->getCustomerEmail()}) to the Newsletter: " . $e->getMessage());
            }
        }

        return $order;
    }

    /**
     * @param $orderItems array
     * @return OrderRow|null
     */
    public function getInvoiceFeeRow($orderItems)
    {
        foreach ($orderItems as $item) {
            /** @var OrderRow $item  */
            if ($item->getArticleNumber() == SveaHelper::INVOICE_FEE_ARTICLE_NUMBER) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     * @throws BaseException
     */
    protected function orderSubscribeToNewsLetter(\Magento\Sales\Model\Order $order)
    {
        $email = $order->getCustomerEmail();
        if (!$email) {
            return false;
        }

        $subscriber = $this->context->getSubscriber();
        $subscriber->loadByEmail($email);

        if ($subscriber->getId()) {
            return false;
        }

        return $subscriber->subscribe($email);
    }

    /**
     * @param string $message
     * @param \Exception $exception
     * @throws CheckoutException
     */
    protected function throwRedirectToCartException($message, $exception = null)
    {
        if (($exception instanceof \Exception) && $this->getHelper()->isTestMode()) {
            $message .= sprintf(" Error: %s", $exception->getMessage());
        }

        throw new CheckoutException(__($message), 'checkout/cart');
    }

    /**
     * @param string $message
     * @throws CheckoutException
     */
    protected function throwReloadException($message)
    {
        throw new CheckoutException(__($message), '*/*');
    }

    /**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     * @codeCoverageIgnore
     */
    public function getCheckoutSession()
    {
        return $this->_checkoutSession; //@see Onepage::__construct
    }

    /**
     * @param $orderId
     * @param $shippingAddress
     * @param $billingAddress
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function createCustomer($orderId, $shipping = [], $billing = []): \Magento\Customer\Api\Data\CustomerInterface
    {
        $addressFactory = $this->context->getAddressInterfaceFactory();
        $addressRepository = $this->context->getAddressRepository();
        $customer = $this->context->getOrderCustomerManagement()->create($orderId);

        $shippingAddress = $addressFactory->create();
        $shippingAddress->setFirstname($shipping['firstname'] ?? null);
        $shippingAddress->setLastname($shipping['lastname'] ?? null);
        $shippingAddress->setTelephone($shipping['telephone'] ?? null);
        $shippingAddress->setStreet($shipping['street'] ?? null);
        $shippingAddress->setCity($shipping['city'] ?? null);
        $shippingAddress->setPostcode($shipping['postcode'] ?? null);
        $shippingAddress->setCountryId($shipping['country_id'] ?? null);
        $shippingAddress->setCustomerId($customer->getId());
        $shippingAddress->setIsDefaultShipping(true);
        $addressRepository->save($shippingAddress);

        $billingAddress = $addressFactory->create();
        $billingAddress->setFirstname($billing['firstname'] ?? null);
        $billingAddress->setLastname($billing['lastname'] ?? null);
        $billingAddress->setTelephone($billing['telephone'] ?? null);
        $billingAddress->setStreet($billing['street'] ?? null);
        $billingAddress->setCity($billing['city'] ?? null);
        $billingAddress->setPostcode($billing['postcode'] ?? null);
        $billingAddress->setCountryId($billing['country_id'] ?? null);
        $billingAddress->setCustomerId($customer->getId());
        $billingAddress->setIsDefaultBilling(true);
        $addressRepository->save($billingAddress);
        $customer->setAddresses([$shippingAddress, $billingAddress]);

        return $customer;
    }

    /** @return \Svea\Checkout\Model\Svea\Order */
    public function getSveaPaymentHandler()
    {
        return $this->context->getSveaOrderHandler();
    }

    /**
     * @param $markDirty
     */
    public function setDoNotMarkCartDirty($markDirty)
    {
        $this->_doNotMarkCartDirty = (bool) $markDirty;
    }

    /**
     * @return bool
     */
    public function getDoNotMarkCartDirty()
    {
        return $this->_doNotMarkCartDirty;
    }

    /**
     * Sets placeholder Svea Shipping data in quote if Svea Shipping is active
     *
     * @return void
     */
    private function setSveaShippingDefault(): void
    {
        $quote = $this->getQuote();
        if (!$this->context->getHelper()->getSveaShippingActive() || $quote->isVirtual()) {
            return;
        }

        $shippingAddress = $quote->getShippingAddress();
        $shippingMethod = (string)$shippingAddress->getShippingMethod();

        if (strpos($shippingMethod, Carrier::CODE) !== false) {
            return;
        }

        $shippingAddress->setShippingMethod(Carrier::CODE . '_' . Carrier::PLACEHOLDER_CARRIER);
        $shippingAddress->setShippingAmount(0);
        $shippingAddress->setBaseShippingAmount(0);
        if (!$shippingAddress->getCountryId()) {
            $defaultCountry = $this->context->getHelper()->getDefaultCountry();
            $shippingAddress->setCountryId($defaultCountry);
        }
        $extAttributes = $quote->getExtensionAttributes();
        if (null === $extAttributes) {
            $extAttributes = $this->context->getCartExtensionFactory()->create();
        }

        $extAttributes->setShippingAssignments([$this->context->getShippingAssignmentProcessor()->create($quote)]);
        $sveaShippingInfoService = $this->context->getSveaShippingInfoService();
        $placeholderData = [
            'carrier' => Carrier::PLACEHOLDER_CARRIER,
            'name' => Carrier::PLACEHOLDER_NAME,
            'price' => 0
        ];
        $sveaShippingInfoService->setInQuote($quote, $placeholderData);
        $this->quoteRepository->save($quote);
    }

    /**
     * Will save the payment id, quote signature, and creation time in checkout session and quote
     *
     * @param Quote $quote
     * @param int $sveaOrderId
     * @param string $signature
     * @return void
     */
    private function handleNewOrderData(Quote $quote, GetOrderResponse $sveaOrder, $signature): void
    {
        $sveaOrderId = $sveaOrder->getOrderId();
        $this->getRefHelper()->setSveaOrderId($sveaOrderId);
        $this->getRefHelper()->setQuoteSignature($signature);
        $this->getRefHelper()->setSveaCreatedAt(time());

        if ($this->getHelper()->getRecurringPaymentsActive()) {
            $recurringService = $this->context->getRecurringInfoService();
            $recurringInfo = $recurringService->quoteGetter($quote);

            $recurringEnabled = $recurringInfo['enabled'] ?? false;
            if ($recurringEnabled) {
                $recurringInfo['recurring_token'] = $sveaOrder->getRecurringToken();
            }

            $recurringService->quoteSetter($quote, $recurringInfo);
        }
        $this->updateSveaSession();
        $this->quoteRepository->save($quote);
    }

    /**
     * Initializes session IDs for this combination of: Quote ID, Country ID, and Recurring setting,
     *  Or leaves them empty if IDs don't exist yet
     *
     * @return void
     */
    private function initSveaSession(): void
    {
        $quote = $this->getQuote();
        $session = $this->context->fetchSveaSession($quote);
        if (!$session->getId()) {
            // Add to sequence if a client order number was previously set
            if ($this->getRefHelper()->getClientOrderNumber()) {
                $this->getRefHelper()->addToSequence();
            }
            $this->getRefHelper()->unsetSessions();
            return;
        }

        $this->getRefHelper()->setSveaOrderId($session->getSveaOrderId());
        $this->getRefHelper()->setClientOrderNumber($session->getSveaClientOrderId());
    }

    /**
     * Sets the Svea Order Id and Client Order Number on Svea Session for this combination of:
     *  Quote ID, Country ID, and Recurring setting
     */
    private function updateSveaSession(): void
    {
        $quote = $this->getQuote();
        $session = $this->context->fetchSveaSession($quote);
        $session->setSveaOrderId($this->getRefHelper()->getSveaOrderId());
        $session->setSveaClientOrderId($this->getRefHelper()->getClientOrderNumber());
        $session->setStoreId($quote->getStoreId());
        $this->context->saveSveaSession($session);
    }
}

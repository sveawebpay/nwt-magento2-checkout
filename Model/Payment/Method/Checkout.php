<?php

namespace Svea\Checkout\Model\Payment\Method;

use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\CancelPayment;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Exception\LocalizedException;

/**
 * Svea Checkout Payment method
 */
class Checkout extends AbstractMethod
{

    protected $_code  = 'sveacheckout';

   /**
    * @var string
    */
    protected $_formBlockType = 'Svea\Checkout\Block\Payment\Checkout\Form';

   /**
    * @var string
    */
   protected $_infoBlockType = 'Svea\Checkout\Block\Payment\Checkout\Info';

   /** @var \Magento\Directory\Model\Currency */
   protected $_currency;



    protected $_isGateway = false;
    protected $_isOffline = false;
    protected $_canOrder = false;
    protected $_canAuthorize = true; //authorize is it called by initialize
    protected $_canCapture = true;   //capture payment when invoice is placed
    protected $_canCapturePartial = true; 
    protected $_canCaptureOnce = false; // capture can be performed once and no further capture possible (didn't see when/how it's used)
    protected $_canRefund = true; //refund on credit memo
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;   //the payment will be canceled when order is canceled
    protected $_canUseInternal = false; //cannot be used internal (backend)
    protected $_canUseCheckout = true; //used in checkout (will redirect the user to the /svea/checkout)
    protected $_isInitializeNeeded = true;  //will use initialize to authorize and set state/status to new/pending (with authorize the state is set to processing)
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCancelInvoice = false; //is not yet implemented?!? (Magento 2.0.4)


    //"Keep" quote, we will need into canUseCurrency

    protected $_quote;

    /**
     * Check whether payment method can be used
     * @param \Magento\Quote\Api\Data\CartInterface|Quote|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $this->_quote = $quote;
        return $this->_helper->isEnabled() && parent::isAvailable($quote);
    }

    
    
    /**
     * Assign data to info model instance
     *
     * @param array|\Magento\Framework\DataObject $data
     * @return \Magento\Payment\Model\Info
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {

        $assocData = $data->getData();
        foreach ($assocData as $key => $val) {
            $this->getInfoInstance()->setAdditionalInformation($key, $val);
        }

        return $this;
    }


    public function canCapture()
    {
        if(!$this->_canCapture) {
            return false;
        }

        $payment = $this->getInfoInstance();
        $order   = $payment->getOrder();
        return $this->_helper->canCapture($order?$order->getStore():null);
    }

    public function canRefund()
    {
        if (!$this->_canRefund) {
            return false;
        }

        $payment = $this->getInfoInstance();
        $order   = $payment->getOrder();

        // same settings for canCapture adn canRefund!
        return $this->_helper->canCapture($order?$order->getStore():null);
    }

    public function canVoid()
    {
        return $this->_canVoid;
    }

    public function canCapturePartial()
    {
        if (!$this->_canCapturePartial) {
            return false;
        }

        $payment = $this->getInfoInstance();
        $order   = $payment->getOrder();
        return $this->_helper->canCapturePartial($order?$order->getStore():null);

    }

    /**
     * To check billing country is allowed for the payment method
     *
     * @param string $country
     * @return bool
     */
    public function canUseForCountry($country)
    {
        $storeId = $this->getStore();
        $country = trim(strtoupper($country));
        $allowedCountries = $this->_helper->getCountries($storeId);
        if ($this->_helper->getInternationalFlowActive($storeId)) {
            $allowedCountries = $this->_helper->getGeneralAllowedCountries($storeId);
        }
        $result =  $country && in_array($country, $allowedCountries) && parent::canUseForCountry($country);
        return $result;
    }

    /**
     * Get currency model instance. 
     *
     * @return \Magento\Directory\Model\Currency
     */
    public function getCurrency($currency)
    {
        if ($this->_currency === null) {
            $this->_currency = $this->_currencyFactory->create();
        }

        return $this->_currency;
    }




   /**
     * Check method for processing with base currency
     *
     * @param string $currencyCode
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canUseForCurrency($currencyCode)    
    {
        return in_array(strtoupper($currencyCode), $this->sveaHandler->getLocale()->getAllowedCurrencies());
    }



    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->_helper->getCheckoutUrl();
    }


    /**
     * Get config payment action url
     * Used to universalize payment actions when processing payment place
     *
     * @return string
     * @api
     */
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE;
    }


    public function initialize($paymentAction, $stateObject)
    {
        //$paymentAction not used, we will "authorize" by default
        $payment = $this->getInfoInstance();
        $order   = $payment->getOrder();

        //import quote data
        $order->setSveaOrderId($payment->getAdditionalInformation('svea_order_id'));


        $orderState = \Magento\Sales\Model\Order::STATE_NEW;
        $orderStatus = $this->getConfigData('order_status');

        // Determine if the custom status is valid for the NEW state
        $statuses = $order->getConfig()->getStateStatuses($orderState);

        if (!$orderStatus) {
            $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
        } elseif (!isset($statuses[$orderStatus])) {
            // Fallback to PROCESSING state if the status isn't valid for NEW
            $orderState = \Magento\Sales\Model\Order::STATE_PROCESSING;
            $statuses = $order->getConfig()->getStateStatuses($orderState);

            // Only assign PROCESSING state if the status is valid there
            if (!isset($statuses[$orderStatus])) {
                // If still not valid, fallback to default status for NEW
                $orderState = \Magento\Sales\Model\Order::STATE_NEW;
                $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }
        }

        $stateObject->setState($orderState);
        $stateObject->setStatus($orderStatus);
        $stateObject->setIsNotified(false);
        
        //We need to keep this, to restore after magento "destroy" it
        //@see Observer\FixOrderStatus
        $payment
            ->setSveaCheckoutState($stateObject->getState())
            ->setSveaCheckoutStatus($stateObject->getStatus());


        //due to some bugs, we don't want to use magento authorize, we will "replicate" almost all operations
        //$payment->authorize(true,$order->getBaseTotalDue());

        $this->authorize($payment,$order->getBaseTotalDue());
        $payment->setAmountAuthorized($order->getTotalDue());
        

        return $this;

    }

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        //NOTE: amount is "baseAmount"

        if (!$this->canAuthorize()) {
            throw new LocalizedException(__('Authorize action is not available.'));
        }

        $order = $payment->getOrder();
        $this->setStore($order->getStoreId());

        $payment->setShouldCloseParentTransaction(false);
        // update totals
        $amount = $payment->formatAmount($amount, true);
        $payment->setBaseAmountAuthorized($amount);

        $formattedAmount = $order->getBaseCurrency()->formatTxt($amount);


        $info = $this->getInfoInstance();
        $payment->setTransactionId($info->getAdditionalInformation('svea_order_id'));
        //    $payment->setIsFraudDetected(false); //bug into magento <=2.1.4 (don't know when/if was fixed) which mark all orders as FraudDetected on multicurrency stores

        //restore OUR state/status (set into initialize), not state set by authorize
        //@see Magento\Sales\Model\Order\Payment\Operations\AuthorizeOperation
        //@see Magento\Sales\Model\Order\Payment\State\OrderCommand

        $order
            ->setState($payment->getSveacheckoutState())
            ->setStatus($payment->getSveacheckoutStatus())
        ;



        $canCapture = $this->canCapture();
        if($canCapture) {
            $payment->setIsTransactionClosed(0); //let transaction OPEN (need to cancel/void this reservation)
            $message = __('Authorized amount of %1.',$formattedAmount);

        } else {
            $message = __('Authorized amount of %1.', $formattedAmount);
        }

        // update transactions, order state and add comments
        $transaction = $payment->addTransaction(Transaction::TYPE_AUTH);
        $message = $payment->prependMessage($message);
        $payment->addTransactionCommentsToOrder($transaction, $message);

        return $this;
    }


    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new LocalizedException(__('Capture action is not available.'));
        }

        try {
            $this->sveaHandler->captureSveaPayment($payment, $amount);
        } catch (ClientException $e) {
            throw new LocalizedException(__("Could not Capture order. %1", $e->getMessage()), $e);
        }
    }


    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {

        if (!$this->canRefund()) {
            throw new LocalizedException(__('Refund action is not available.'));
        }

        try {
            $this->sveaHandler->refundSveaPayment($payment, $amount);
        } catch (ClientException $e) {
            throw new LocalizedException(__("Could not Refund Invoice. %1", $e->getMessage()), $e);
        }
    }

    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this->void($payment);
    }


    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        if (!$this->_canVoid) {
            throw new LocalizedException(__('Void/Cancel action is not available.'));
        }

        try {
            $this->sveaHandler->cancelSveaPayment($payment);
        } catch (ClientException $e) {
            throw new LocalizedException(__("Could not cancel order. %1", $e->getMessage()), $e);
        }
    }


    public function detach(\Magento\Payment\Model\InfoInterface $payment)
    {
        throw new LocalizedException(__('Detach is not available.'));
    }
}

<?php

namespace Svea\Checkout\Model;

/**
 * We use this to generate a unique client_order_number for Svea.
 * The quote and checkout session should be valid for the same amount of time
 *
 * If a checkout order fails somehow at svea, we want to create an new one using the same quote id
 * Therefore we add an extra sequence to the client_order_id, to make it unique!
 *
 * Class CheckoutOrderNumberReference
 * @package Svea\Checkout\Model
 */
class CheckoutOrderNumberReference
{
    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote = null;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    private $sessionLifetimeSeconds;

    /**
     * @param \Magento\Checkout\Model\Session $_checkoutSession
     */
    public function __construct(
        \Magento\Checkout\Model\Session $_checkoutSession,
        int $sessionLifetimeSeconds = 172800
    ) {
        $this->_checkoutSession = $_checkoutSession;
        $this->sessionLifetimeSeconds = $sessionLifetimeSeconds;
    }

    /**
     * @return int|null
     */
    public function getSveaOrderId()
    {
        return $this->getCheckoutSession()->getSveaOrderId();
    }

    /**
     * @param $sveaOrderId
     * @return void
     */
    public function setSveaOrderId($sveaOrderId)
    {
        $this->getCheckoutSession()->setSveaOrderId($sveaOrderId);
        $this->getQuote()->setSveaOrderId($sveaOrderId);
    }

    /**
     * @param $signature
     * @return void
     */
    public function setQuoteSignature($signature)
    {
        $this->getCheckoutSession()->setSveaQuoteSignature($signature);
    }

    /**
     * @return string
     */
    public function getQuoteSignature()
    {
        return $this->getCheckoutSession()->getSveaQuoteSignature();
    }

    public function getQuoteId()
    {
        return $this->getQuote()->getId();
    }

    /**
     * Checks if client order number is already set
     *
     * @return bool
     */
    public function hasClientOrderNumber(): bool
    {
        return !!$this->getQuote()->getData('svea_client_order_id');
    }

    /**
     * Gets client order number / id. Generates it if not yet set.
     *
     * @return mixed
     */
    public function getClientOrderNumber()
    {
        if (!$this->hasClientOrderNumber()) {
            $this->generateClientOrderNumberToQuote();
        }

        return $this->getQuote()->getData('svea_client_order_id');
    }

    /**
     * @param string $clientOrderNumber
     * @return void
     */
    public function setClientOrderNumber($clientOrderNumber)
    {
        $this->getQuote()->setData('svea_client_order_id', $clientOrderNumber);
    }

    /**
     * @return void
     */
    public function generateClientOrderNumberToQuote()
    {
        $this->getQuote()->setData(
            'svea_client_order_id',
            $this->generateClientOrderNumber()
        );
    }

    /**
     *
     */
    public function unsetClientOrderNumber()
    {
        $quote = $this->getQuote();
        $quote->unsSveaClientOrderId();
    }

    /**
     * @return false|string
     */
    private function generateClientOrderNumber()
    {
        $sequence = $this->getSequence();
        $quote = $this->getQuote();

        if (! $quote->getReservedOrderId()) {
            $quote->reserveOrderId();
        }

        $cn = $quote->getReservedOrderId();
        if ($sequence > 1) {
            $cn = $cn . '-' . $sequence;
        }

        return substr($cn, 0, 31);
    }

    /**
     * @return string
     */
    public function getSveaHash()
    {
        if (!$this->getQuote()->getSveaHash()) {
            $hash = hash("sha1", $this->getClientOrderNumber());
            $this->getQuote()->setSveaHash($hash);
        }

        return $this->getQuote()->getSveaHash();
    }

    /**
     * @param $clientId
     * @return bool
     */
    public function clientIdIsMatching($clientId)
    {
        return $clientId === $this->getQuote()->getSveaClientOrderNumber();
    }

    /**
     * @return int
     */
    protected function getSequence()
    {
        $sequence = $this->getCheckoutSession()->getSveaCheckoutSequence();
        if ($sequence) {
            return (int) $sequence;
        }

        $this->getCheckoutSession()->setSveaCheckoutSequence(1);
        return 1;
    }

    /**
     * @return void
     */
    public function addToSequence()
    {
        $this->getCheckoutSession()->setSveaCheckoutSequence($this->getSequence() + 1);
    }

    /**
     * Quote object getter
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if ($this->_quote === null) {
            $this->_quote = $this->_checkoutSession->getQuote();
        }

        return $this->_quote;
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @return $this
     */
    public function setQuote(\Magento\Quote\Model\Quote $quote)
    {
        $this->_quote = $quote;
        return $this;
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }

    public function unsetSessions($unsetSequence = false, $keepClientNumberInQuote = false)
    {

        // remove sessions
        $this->getCheckoutSession()->unsSveaOrderId(); //remove svea order id from session
        $this->unsetSveaQuoteSignature(); //remove signature from session

        if (!$keepClientNumberInQuote) {
            $this->unsetClientOrderNumber();
        }

        if ($unsetSequence) {
            $this->unsetSequence();
        }
    }

    public function unsetSequence()
    {
        $this->getCheckoutSession()->unsSveaCheckoutSequence();
    }

    public function unsetSveaQuoteSignature()
    {
        $this->getCheckoutSession()->unsetSveaQuoteSignature();
    }

    /**
     * Set timestamp of when payment session was created
     *
     * @param int $timestamp
     * @return void
     */
    public function setSveaCreatedAt(int $timestamp): void
    {
        $this->getQuote()->getPayment()->setAdditionalInformation('svea_created_at', $timestamp);
    }

    /**
     * Get timestamp of when payment session was created
     *
     * @return int
     */
    public function getSveaCreatedAt(): int
    {
        return (int)$this->getQuote()->getPayment()->getAdditionalInformation('svea_created_at');
    }

    /**
     * Check if current payment has expired
     *
     * @return boolean
     */
    public function paymentIsExpired(): bool
    {
        $sveaCreatedAt = $this->getSveaCreatedAt();
        return ($sveaCreatedAt <= strtotime(sprintf('-%s seconds', $this->getSessionLifetimeSeconds())));
    }

    /**
     * Return session lifetime seconds value
     *
     * @return integer
     */
    public function getSessionLifetimeSeconds(): int
    {
        return $this->sessionLifetimeSeconds;
    }
}

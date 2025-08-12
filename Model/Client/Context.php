<?php

namespace Svea\Checkout\Model\Client;

use Svea\Checkout\Model\Client\DTO\GenericRequestFactory;

class Context
{
    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var\Svea\Checkout\Logger
     */
    protected $logger;

    /**
     * @var GenericRequestFactory
     */
    private GenericRequestFactory $genericRequestFactory;

   /**
    * Constructor
    *
    * @param \Svea\Checkout\Helper\Data $helper
    * @param \Svea\Checkout\Logger\Logger $logger
    * @param GenericRequestFactory $genericRequestFactory
    */
    public function __construct(
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Logger\Logger $logger,
        GenericRequestFactory $genericRequestFactory
    ) {
        $this->helper        = $helper;
        $this->logger = $logger;
        $this->genericRequestFactory = $genericRequestFactory;
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

    /**
     * @return GenericRequestFactory
     */
    public function getGenericRequestFactory(): GenericRequestFactory
    {
        return $this->genericRequestFactory;
    }
}

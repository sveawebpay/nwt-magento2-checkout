<?php


namespace Svea\Checkout\Plugin;


class Url
{

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    public function __construct(\Svea\Checkout\Helper\Data $helper)
    {
        $this->helper = $helper;
    }

    public function afterGetCheckoutUrl($subject,$result)
    {

        if (!$this->helper->isEnabled()) {
            return $result;
        }

        if ($this->helper->replaceCheckout()) {
            return $this->helper->getCheckoutUrl();
        }

        return $result;

    }
}

<?php declare(strict_types=1);

namespace Svea\Checkout\Plugin\Checkout\Controller\Sidebar;

use Magento\Checkout\Model\Session;
use Svea\Checkout\Service\SveaShippingInfo;
use Svea\Checkout\Helper\Data;

class SveaShippingIncluder
{
    private SveaShippingInfo $shipInfoService;

    private Data $helper;

    private Session $checkoutSession;

    public function __construct(
        SveaShippingInfo $shipInfoService,
        Data $helper,
        Session $checkoutSession
    ) {
        $this->shipInfoService = $shipInfoService;
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Sets flag in sevice class so that the Svea Shipping carrier is included in totals collection
     *
     * @return void
     */
    public function execute(): void
    {
        $payment = $this->checkoutSession->getQuote()->getPayment();
        if ('sveacheckout' !== $payment->getMethod()
            || !$this->helper->isEnabled()
            || !$this->helper->getSveaShippingActive()) {
            return;
        }

        $this->shipInfoService->setExcludeSveaShipping(false);
    }
}

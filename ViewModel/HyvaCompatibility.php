<?php

namespace Svea\Checkout\ViewModel;

use Svea\Checkout\Helper\Data as SveaHelper;

class HyvaCompatibility implements \Magento\Framework\View\Element\Block\ArgumentInterface
{
    /**
     * @var SveaHelper
     */
    protected $sveaHelper;

    public function __construct(
        SveaHelper $sveaHelper
    ) {
        $this->sveaHelper = $sveaHelper;
    }

    public function getCheckoutUrl(): string
    {
        return $this->sveaHelper->getCheckoutUrl();
    }
}

<?php declare(strict_types=1);

namespace Svea\Checkout\Plugin\Checkout\Controller\Sidebar;

use Magento\Checkout\Controller\Sidebar\RemoveItem as Subject;
use Svea\Checkout\Plugin\Checkout\Controller\Sidebar\SveaShippingIncluder;

/**
 * Plugin for Magento\Checkout\Controller\Sidebar\RemoveItem
 */
class RemoveItem
{
    private SveaShippingIncluder $sveaShippingIncluder;

    public function __construct(
        SveaShippingIncluder $sveaShippingIncluder
    ) {
        $this->sveaShippingIncluder = $sveaShippingIncluder;
    }

    /**
     * @param Subject $subject
     * @return void
     */
    public function beforeExecute(Subject $subject): void
    {
        $this->sveaShippingIncluder->execute();
    }
}

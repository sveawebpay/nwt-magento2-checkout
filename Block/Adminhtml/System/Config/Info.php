<?php

namespace Svea\Checkout\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;

class Info extends Template implements RendererInterface
{
    /**
     * @var string
     */
    protected
        $_template = 'Svea_Checkout::svea.phtml';

    /**
     * Render element html
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public
    function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $elementOriginalData = $element->getOriginalData();
        return $this->toHtml();
    }

    /**
     * @return string
     */
    public function getComposerVersion(): string
    {
        return $this->getLayoutHelper()->getComposerVersion();
    }

    /**
     * @return \Svea\Checkout\Helper\Adminhtml\Layout
     */
    private function getLayoutHelper(): \Svea\Checkout\Helper\Adminhtml\Layout
    {
        // Set in di.xml
        return $this->getData('layout_helper');
    }
}

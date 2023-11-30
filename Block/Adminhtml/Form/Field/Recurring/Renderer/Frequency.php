<?php declare(strict_types=1);

namespace Svea\Checkout\Block\Adminhtml\Form\Field\Recurring\Renderer;

class Frequency extends \Magento\Framework\View\Element\Html\Select
{
    const OPTION_EVERY = 1;
    const OPTION_EVERY_OTHER = 2;
    const OPTION_EVERY_THIRD = 3;

    /**
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    protected function _toHtml()
    {
        $this->addOption(self::OPTION_EVERY, __('Every'));
        $this->addOption(self::OPTION_EVERY_OTHER, __('Every Other'));
        $this->addOption(self::OPTION_EVERY_THIRD, __('Every Third'));
        return parent::_toHtml();
    }
}

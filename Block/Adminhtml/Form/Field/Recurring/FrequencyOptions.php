<?php declare(strict_types=1);

namespace Svea\Checkout\Block\Adminhtml\Form\Field\Recurring;

use Svea\Checkout\Block\Adminhtml\Form\Field\Recurring\Renderer\Frequency;
use Svea\Checkout\Block\Adminhtml\Form\Field\Recurring\Renderer\TimeUnit;
use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;

class FrequencyOptions extends AbstractFieldArray
{
    private ?Frequency $frequencyRenderer = null;

    private ?TimeUnit $timeUnitRenderer = null;

    /**
     * @inheritDoc
     */
    protected function _prepareToRender()
    {
        $this->addColumn(
            'label',
            ['label' => __('Option Label')]
        );
        $this->addColumn(
            'frequency',
            ['label' => __('Frequency'), 'renderer' => $this->getFrequencyRenderer()]
        );
        $this->addColumn(
            'time_unit',
            ['label' => __('Time Unit'), 'renderer' => $this->getTimeUnitRenderer()]
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Frequency Option');
    }

    /**
     * @return TimeUnit
     */
    private function getTimeUnitRenderer(): TimeUnit
    {
        if (!$this->timeUnitRenderer) {
            $this->timeUnitRenderer = $this->getLayout()->createBlock(
                TimeUnit::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->timeUnitRenderer->setClass('admin__control-select');
        }
        return $this->timeUnitRenderer;
    }

    /**
     * @return Frequency
     */
    private function getFrequencyRenderer(): Frequency
    {
        if (!$this->frequencyRenderer) {
            $this->frequencyRenderer = $this->getLayout()->createBlock(
                Frequency::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            $this->frequencyRenderer->setClass('customer_group_select admin__control-select');
        }
        return $this->frequencyRenderer;
    }

    /**
     * @inheritDoc
     */
    protected function _prepareArrayRow(DataObject $row)
    {
        $frequencyKey = 'option_' . $this->getFrequencyRenderer()->calcOptionHash($row->getData('frequency'));
        $optionExtraAttr[$frequencyKey] = 'selected="selected"';

        $timeUnitKey = 'option_' . $this->getTimeUnitRenderer()->calcOptionHash($row->getData('time_unit'));
        $optionExtraAttr[$timeUnitKey] = 'selected="selected"';

        $row->setData(
            'option_extra_attrs',
            $optionExtraAttr
        );
    }
}

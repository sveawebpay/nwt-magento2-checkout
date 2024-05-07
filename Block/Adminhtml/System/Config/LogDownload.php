<?php
namespace Svea\Checkout\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class LogDownload extends Field
{
    protected $_template = 'Svea_Checkout::system/config/log_download.phtml';

    /**
     * Retrieve element HTML markup.
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button HTML.
     *
     * @return string
     */
     public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            'Magento\Backend\Block\Widget\Button'
        )->setData(
            [
                'id' => 'download_logs_button',
                'label' => __('Download All Logs'),
                'onclick' => 'setLocation(\'' . $this->getLogDownloadUrl() . '\')',
            ]
        );
        return $button->toHtml();
    }

    /**
     * Get download URL.
     *
     * @return string
     */
    public function getLogDownloadUrl()
    {
        return $this->getUrl('svea_checkout/log/download', ['_current' => true, '_secure' => true]);
    }
}

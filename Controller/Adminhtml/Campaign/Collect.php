<?php declare(strict_types=1);

namespace Svea\Checkout\Controller\Adminhtml\Campaign;

use Magento\Framework\Exception\LocalizedException;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Service\CollectCampaigns;

/**
 * Collects campaigns from Svea API
 */
class Collect extends Action
{
    protected JsonFactory $resultJsonFactory;

    private Data $sveaConfig;

    private CollectCampaigns $collectCampaigns;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Data $sveaConfig,
        CollectCampaigns $collectCampaigns
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->sveaConfig = $sveaConfig;
        $this->collectCampaigns = $collectCampaigns;
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if (!$this->sveaConfig->isCampaignWidgetActive()) {
            throw new LocalizedException(__('Campaigns is not active'));
        }

        $messages = $this->collectCampaigns->collect();
        $statusMessage = '';
        foreach ($messages as $message) {
            $statusMessage .= $message . '<br>';
        }

        $result = $this->resultJsonFactory->create();
        return $result->setData(['status' => $statusMessage]);
    }
}

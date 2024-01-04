<?php declare(strict_types=1);

namespace Svea\Checkout\Cron;

use Svea\Checkout\Helper\Data;
use Svea\Checkout\Service\CollectCampaigns;

/**
 * Fetch campaigns from Svea API
 */
class FetchCampaigns
{
    private Data $sveaConfig;

    private CollectCampaigns $collectCampaigns;

    private array $messages = [];

    public function __construct(
        Data $sveaConfig,
        CollectCampaigns $collectCampaigns
    ) {
        $this->sveaConfig = $sveaConfig;
        $this->collectCampaigns = $collectCampaigns;
    }

    /**
     * Collect campaigns from Svea API, stores result messages in class property
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->sveaConfig->isCampaignWidgetActive()) {
            return;
        }

        $this->messages = $this->collectCampaigns->collect();
    }

    /**
     * Gets messages after the campaign import
     *
     * @return array
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}

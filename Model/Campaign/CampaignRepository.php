<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Campaign;

use Svea\Checkout\Api\CampaignInfoRepositoryInterface;
use Svea\Checkout\Api\Data;

class CampaignRepository implements CampaignInfoRepositoryInterface
{
    /**
     * @var \Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory
     */
    private $campaignCollectionFactory;

    /**
     * CampaignRepository constructor.
     */
    public function __construct(
        \Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory $campaignCollectionFactory
    ) {
        $this->campaignCollectionFactory = $campaignCollectionFactory;
    }

    public function save(Data\CampaignInfoInterface $push)
    {
        // TODO: Implement save() method.
    }

    public function get($campaignId): Data\CampaignInfoInterface
    {
        $collection = $this->campaignCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', $campaignId);
        $campaign = $collection->getFirstItem();
        if (!$campaign->getEntityId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException();
        }
        return $campaign;
    }

    /**
     * @param $campaignCode
     *
     * @return Data\CampaignInfoInterface
     */
    public function getByCode($campaignCode): Data\CampaignInfoInterface
    {
        $collection = $this->campaignCollectionFactory->create();
        $collection->addFieldToFilter('campaign_code', $campaignCode);

        $campaign = $collection->getFirstItem();
        if (!$campaign->getEntityId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException;
        }

        return $campaign;
    }

    /**
     * @return array
     */
    public function getCodes(): array
    {
        $collection = $this->campaignCollectionFactory->create();

        return $collection->getColumnValues('campaign_code');
    }
}
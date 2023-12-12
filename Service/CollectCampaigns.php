<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Svea\Checkout\Model\Client\Api\CampaignManagement;
use Svea\Checkout\Model\CampaignInfoFactory;
use Svea\Checkout\Model\Resource\CampaignInfo;
use Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory;
use Svea\Checkout\Logger\Logger;
use Svea\Checkout\Model\Client\ClientException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\AlreadyExistsException;

/**
 * Collect campaigns from Svea API
 */
class CollectCampaigns
{
    private CampaignManagement $campaignManagement;

    private CampaignInfoFactory $campaignInfoFactory;

    private CampaignInfo $campaignResource;

    private CollectionFactory $campaignCollectionFactory;

    private Logger $logger;

    private StoreManagerInterface $storeManager;

    public function __construct(
        CampaignManagement $campaignManagement,
        CampaignInfoFactory $campaignInfoFactory,
        CampaignInfo $campaignResource,
        CollectionFactory $campaignCollectionFactory,
        Logger $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->campaignManagement = $campaignManagement;
        $this->campaignInfoFactory = $campaignInfoFactory;
        $this->campaignResource = $campaignResource;
        $this->campaignCollectionFactory = $campaignCollectionFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Runs campaign import for each store
     *
     * @return array Array of status messages about the import
     */
    public function collect(): array
    {
        $stores = $this->storeManager->getStores();
        $messages = [];
        foreach ($stores as $store) {
            try {
                $storeId = (int)$store->getId();
                $importedResult = $this->collectCampaignsForStore($storeId);
                if (($importedResult['imported'] ?? 0) > 0) {
                    $messages[] = __('Imported %1 campaigns for store %2', $importedResult['imported'], $storeId);
                }

                if (($importedResult['deleted'] ?? 0) > 0) {
                    $messages[] = __('Deleted %1 campaigns for store %2', $importedResult['deleted'], $storeId);
                }
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
                $messages[] = __('Error importing campaigns for store %1, check logs!', $store->getId());
            }
        }

        return $messages;
    }

    /**
     * Fetches campaigns from Svea, saves them in the DB
     *
     * @param integer $storeId
     * @return array
     * @throws ClientException
     * @throws AlreadyExistsException
     */
    private function collectCampaignsForStore(int $storeId): array
    {
        $fetchedCampaigns = $this->campaignManagement->getAvailablePartPaymentCampaigns($storeId);
        foreach ($fetchedCampaigns as $fetchedCampaign) {
            $campaignObject = $this->campaignInfoFactory->create();

            // Load object by campaign code and store id, so it can be updated if needed
            // If none is loaded, $campaignObject will be saved as new
            $this->campaignResource->loadByCampaignCodeAndStoreId(
                $campaignObject,
                (int)$fetchedCampaign['campaign_code'],
                (int)$storeId
            );
            $campaignEntityId = $campaignObject->getId() ?? null;
            $campaignObject->setData($fetchedCampaign);
            // Set store ID and conditional Entity ID again, because setData() will overwrite
            $campaignObject->setId($campaignEntityId);
            $campaignObject->setStoreId($storeId);

            $this->campaignResource->save($campaignObject);
        }

        $fetchedCampaignCodes = array_column($fetchedCampaigns, 'campaign_code');
        // Delete campaigns that are not in the fetched list
        $collection = $this->campaignCollectionFactory->create();
        $collection
            ->addFieldToFilter('store_id', ['eq' => $storeId])
            ->addFieldToFilter('campaign_code', ['nin' => $fetchedCampaignCodes]);

        $deletedCampaignsCount = $collection->getSize();
        foreach ($collection as $campaign) {
            $this->campaignResource->delete($campaign);
        }
        return [
            'imported' => count($fetchedCampaigns),
            'deleted' => $deletedCampaignsCount
        ];
    }
}

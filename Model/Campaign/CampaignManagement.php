<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Campaign;

use Magento\Store\Api\StoreManagementInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;
use Svea\Checkout\Api\GetAvailablePartPaymentCampaigns;

/**
 * Class CampaignManagement
 *
 * @package Svea\Checkout\Model\Campaign
 */
class CampaignManagement implements GetAvailablePartPaymentCampaigns
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory
     */
    private $campaignCollectionFactory;

    /**
     * @var \Svea\Checkout\Model\Resource\CampaignInfo []
     */
    private $loadedCampaigns;

    /**
     * @var ProductCampaignPriceProvider
     */
    private $productPriceProvider;

    /**
     * CampaignManagement constructor.
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        \Svea\Checkout\Model\Resource\CampaignInfo\CollectionFactory $campaignCollectionFactory,
        \Svea\Checkout\Model\Campaign\ProductCampaignPriceProvider $productPriceProvider
    ) {
        $this->storeManager = $storeManager;
        $this->campaignCollectionFactory = $campaignCollectionFactory;
        $this->productPriceProvider = $productPriceProvider;
    }

    /**
     * @param ProductInterface $product
     *
     * @return \Svea\Checkout\Api\Data\CampaignInfoInterface []
     */
    public function getAvailablePartPaymentCampaigns(ProductInterface $product)
    {
        $availableCampaigns = $this->getLoadedCampaigns();
        $sortedPrices = $this->productPriceProvider->getPrices($product);

        $productCampaigns = [];
        try {
            foreach ($availableCampaigns as $campaignCandidate) {
                foreach ($sortedPrices as $priceCandidate) {
                    if ($priceCandidate > $campaignCandidate->getFromAmount() && $priceCandidate < $campaignCandidate->getToAmount()) {
                        $campaignCandidate->setProductPrice($priceCandidate);
                        $productCampaigns[] = $campaignCandidate;
                        break(1);
                    }
                }
            }
        } catch (\Exception $e) {}

        return $productCampaigns;
    }

    /**
     * @return \Svea\Checkout\Api\Data\CampaignInfoInterface[]
     */
    private function getLoadedCampaigns()
    {
        if (!isset($this->loadedCampaigns)) {
            $campaignCollection = $this->campaignCollectionFactory->create();
            $campaignCollection->addFieldToFilter('store_id', $this->storeManager->getStore()->getId());
            $campaignCollection->setOrder('from_amount', \Magento\Framework\Data\Collection::SORT_ORDER_ASC);
            $this->loadedCampaigns = $campaignCollection->getItems();

        }

        return $this->loadedCampaigns;
    }
}

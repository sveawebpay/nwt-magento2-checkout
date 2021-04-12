<?php declare(strict_types=1);

namespace Svea\Checkout\ViewModel\Widget\ProductCampaign;

class ViewModel
{
    /**
     * @var \Svea\Checkout\Api\GetAvailablePartPaymentCampaigns
     */
    private $campaignService;

    /**
     * @var \Svea\Checkout\Block\Widget\ProductProvider
     */
    private $productProvider;

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    private $checkoutHelper;

    /**
     * ProductCampaign constructor.
     *
     * @param \Svea\Checkout\Block\Widget\ProductProvider $productProvider
     * @param \Svea\Checkout\Api\GetAvailablePartPaymentCampaigns $campaignService
     */
    public function __construct(
        \Svea\Checkout\Block\Widget\ProductProvider $productProvider,
        \Svea\Checkout\Api\GetAvailablePartPaymentCampaigns $campaignService,
        \Svea\Checkout\Helper\Data $checkoutHelper
    ) {
        $this->campaignService = $campaignService;
        $this->productProvider = $productProvider;
        $this->checkoutHelper = $checkoutHelper;
    }

    /**
     * @return \Svea\Checkout\Api\Data\CampaignInfoInterface []
     */
    public function getProductCampaigns()
    {
        $product = $this->getProduct();

        return $product
            ? $this->campaignService->getAvailablePartPaymentCampaigns($product)
            : [];
    }

    /**
     * @return \Magento\Catalog\Api\Data\ProductInterface|null
     */
    public function getProduct()
    {
        return $this->productProvider->get();
    }

    /**
     * @return string
     */
    public function getCheckoutUrl()
    {
        return $this->checkoutHelper->getCheckoutUrl('', ['_secure' => true]);
    }

    /**
     * @param \Svea\Checkout\Api\Data\CampaignInfoInterface $campaignA
     * @param \Svea\Checkout\Api\Data\CampaignInfoInterface $campaignB
     * @return bool
     */
    public function sortCampaignsByPriceAsc($campaignA, $campaignB)
    {
        return $campaignA->getUnformattedCampaignPrice() > $campaignB->getUnformattedCampaignPrice();
    }
}

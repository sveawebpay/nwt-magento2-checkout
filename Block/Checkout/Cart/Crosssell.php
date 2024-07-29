<?php

namespace Svea\Checkout\Block\Checkout\Cart;

use Svea\Checkout\Helper\Cart as SveaCartHelper;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Url\Helper\Data;
use Magento\Catalog\Model\Product;

class Crosssell extends \Magento\Checkout\Block\Cart\Crosssell
{
    protected $_maxItemCount;

    /**
     * @var SveaCartHelper
     */
    protected $sveaCartHelper;

    /**
     * @var Data
     */
    protected $urlHelper;

    /**
     * Crosssell constructor.
     * @param \Magento\Catalog\Block\Product\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Catalog\Model\Product\LinkFactory $productLinkFactory
     * @param \Magento\Quote\Model\Quote\Item\RelatedProducts $itemRelationsList
     * @param StockHelper $stockHelper
     * @param SveaCartHelper $_cartHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Catalog\Block\Product\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Model\Product\Visibility $productVisibility,
        \Magento\Catalog\Model\Product\LinkFactory $productLinkFactory,
        \Magento\Quote\Model\Quote\Item\RelatedProducts $itemRelationsList,
        StockHelper $stockHelper,
        SveaCartHelper $sveaCartHelper,
        Data $urlHelper,
        array $data = []
    )
    {
        parent::__construct(
            $context,
            $checkoutSession,
            $productVisibility,
            $productLinkFactory,
            $itemRelationsList,
            $stockHelper,
            $data
        );
        $this->sveaCartHelper = $sveaCartHelper;
        $this->_maxItemCount = $sveaCartHelper->getNumberOfCrosssellProducts();
        $this->urlHelper = $urlHelper;
    }

    /**
     * @return SveaCartHelper
     */
    public function getSveaCartHelper()
    {
        return $this->sveaCartHelper;
    }

    /**
     * @return mixed
     */
    public function isEnable()
    {
        return $this->getSveaCartHelper()->isDisplayCrosssell();
    }

    /**
     * Get post parameters
     *
     * @param Product $product
     * @return array
     */
    public function getAddToCartPostParams(Product $product)
    {
        $url = $this->getAddToCartUrl($product);
        return [
            'action' => $url,
            'data' => [
                'product' => $product->getEntityId(),
                ActionInterface::PARAM_NAME_URL_ENCODED => $this->urlHelper->getEncodedUrl($url),
            ]
        ];
    }

    /**
     * Get crosssell headline text
     *
     * @return string
     */
    public function getCrosssellHeadline()
    {
        return $this->sveaCartHelper->getCrosssellHeadline();
    }
}
<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Campaign;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Class ProductCampaignPriceProvider
 *
 * @package Svea\Checkout\Model\Campaign
 */
class ProductCampaignPriceProvider
{
    /**
     * Return sorted product prices
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getPrices(ProductInterface $product) : array
    {
        if (! $product instanceof Product) {
            return [];
        }

        switch ($product->getTypeId()) {
            case 'configurable':
                return $this->getCompositePrice($product);
                break;
            default:
                return [(float) $product->getFinalPrice()];
                break;
        }
    }

    /**
     */
    private function getCompositePrice(\Magento\Catalog\Model\Product $product)
    {
        /** @var Configurable $configurableType */
        $configurableType = $product->getTypeInstance();
        $childrenProducts = $configurableType->getUsedProducts($product);

        $prices = array_map(function ($childProduct) {
            return (float) $childProduct->getFinalPrice();
        }, $childrenProducts);
        asort($prices);

        return array_values($prices);
    }
}
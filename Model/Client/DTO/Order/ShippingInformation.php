<?php

namespace Svea\Checkout\Model\Client\DTO\Order;

use Magento\Quote\Model\Quote;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Svea\Checkout\Model\Client\DTO\AbstractRequest;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\FallbackOptionFactory;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\FallbackOption;
use Svea\Checkout\Model\Shipping\Carrier;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\Tags;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation\TagsFactory;

class ShippingInformation extends AbstractRequest
{
    /**
     * @var ShipmentEstimationInterface
     */
    private $shipmentEstimation;

    /**
     * @var FallbackOptionFactory
     */
    private $fallbackOptFactory;

    /**
     * @var TagsFactory
     */
    private TagsFactory $tagsFactory;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var bool
     */
    private $enableShipping;

    /**
     * @var bool
     */
    private $enforceFallback = false;

    /**
     * @var int
     */
    private $weight;

    /**
     * @var Tags
     */
    private $tags;

    /**
     * @var FallbackOption[]
     */
    private $fallbackOptions = [];

    public function __construct(
        ShipmentEstimationInterface $shipmentEstimation,
        FallbackOptionFactory $fallbackOptFactory,
        TagsFactory $tagsFactory,
        CollectionFactory $productCollectionFactory,
        Data $helper
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
        $this->fallbackOptFactory = $fallbackOptFactory;
        $this->tagsFactory = $tagsFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $partialData = [
            'EnableShipping' => $this->getEnableShipping()
        ];

        if (!$this->getEnableShipping()) {
            return $partialData;
        }

        $completeData = array_merge(
            $partialData,
            [
                'EnforceFallback' => $this->getEnforceFallback(),
                'Weight' => $this->getWeight(),
                'FallbackOptions' => [],
            ]
        );

        if ($this->getTags() instanceof Tags) {
            $completeData['Tags'] = $this->getTags()->toArray();
        }

        foreach ($this->getFallbackOptions() as $fallbackOption) {
            $completeData['FallbackOptions'][] = $fallbackOption->toArray();
        }

        return $completeData;
    }

    /**
     * Get the value of enableShipping
     */
    public function getEnableShipping()
    {
        return $this->enableShipping;
    }

    /**
     * Set the value of enableShipping
     *
     * @return  self
     */
    public function setEnableShipping($enableShipping)
    {
        $this->enableShipping = $enableShipping;

        return $this;
    }

    /**
     * Get the value of enforceFallback
     */
    public function getEnforceFallback()
    {
        return $this->enforceFallback;
    }

    /**
     * Set the value of enforceFallback
     *
     * @return  self
     */
    public function setEnforceFallback($enforceFallback)
    {
        $this->enforceFallback = $enforceFallback;

        return $this;
    }

    /**
     * Get the value of weight
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Set the value of weight
     *
     * @return  self
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * Get the value of tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Set the value of tags
     *
     * @return self
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get the value of fallbackOptions
     *
     * @return FallbackOption[]
     */
    public function getFallbackOptions()
    {
        return $this->fallbackOptions;
    }

    /**
     * Set the value of fallbackOptions
     *
     * @param FallbackOption[] $fallbackOptions
     *
     * @return  self
     */
    public function setFallbackOptions($fallbackOptions)
    {
        $this->fallbackOptions = $fallbackOptions;

        return $this;
    }

    public function generateFromQuote(Quote $quote): ShippingInformation
    {
        $enableShipping = !$quote->getIsVirtual();
        $this->setEnableShipping($enableShipping);
        $this->setEnforceFallback($this->helper->getSveaShippingEnforceFallback());
        $this->setWeight($this->kilogramsToGrams($quote->getShippingAddress()->getWeight()));
        $this->generateFallbackOptions($quote);
        $this->generateDimensions($quote);
        return $this;
    }

    /**
     * @param Quote $quote
     * @return self
     */
    private function generateFallbackOptions(Quote $quote): self
    {
        try {
            $fallbackOptions = [];
            $methods = $this->shipmentEstimation->estimateByExtendedAddress(
                (int)$quote->getId(),
                $quote->getShippingAddress()
            );
    
            foreach ($methods as $method) {
                if (Carrier::CODE === $method->getCarrierCode()) {
                    continue;
                }

                $fallbackOptions[] = $this->fallbackOptFactory->create()
                    ->setId($method->getMethodCode())
                    ->setCarrier($method->getCarrierCode())
                    ->setName(__($method->getCarrierTitle())->getText())
                    ->setShippingFee($this->addZeroes($method->getAmount()))
                ;
            }
            $this->setFallbackOptions($fallbackOptions);
        } finally {
            return $this;
        }
    }

    private function generateDimensions(Quote $quote): void
    {
        if (!$this->helper->getSveaShippingDimensionsActive()) {
            return;
        }

        // 1. Load product collection and add dimension attributes
        $productIds = $quote->getItemsCollection()->getColumnValues('product_id');
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addFieldToFilter('entity_id', ['in' => $productIds]);
        $productCollection
            ->addAttributeToSelect('height_cm')
            ->addAttributeToSelect('length_cm')
            ->addAttributeToSelect('width_cm')
        ;

        // 1.a If cart has only one item, we can use its product dimensions as is
        if (count($quote->getItems()) === 1 && (int)$quote->getItemsQty() === 1) {
            $product = $productCollection->getFirstItem();
            $tags = $this->tagsFactory->create();
            $tags->addTag('height_cm', (int)ceil($product->getHeightCm() ?? 0));
            $tags->addTag('length_cm', (int)ceil($product->getLengthCm() ?? 0));
            $tags->addTag('width_cm', (int)ceil($product->getWidthCm() ?? 0));
            $this->setTags($tags);
            return;
        }

        // 2. Find the highest value of each dimension among the products. This will be packageDimensionX
        // 3. Find the second highest value of each dimension among the products. This will be packageDimensionY
        $maxDimensions = [
            'height' => max($productCollection->getColumnValues('height_cm')),
            'length' => max($productCollection->getColumnValues('length_cm')),
            'width' => max($productCollection->getColumnValues('width_cm'))
        ];
        rsort($maxDimensions);
        array_pop($maxDimensions);
        $packageDimensionX = $maxDimensions[0];
        $packageDimensionY = $maxDimensions[1];

        if ((int)$packageDimensionX === 0) {
            return;
        }

        // 4. Add the lowest dimension of each product together. This will be packageDimensionZ
        $packageDimensionZ = 0;
        foreach ($productCollection as $product) {
            $item = $quote->getItemByProduct($product);
            if (!$item) {
                continue;
            }
            $itemDimensions = [];
            $itemDimensions['height'] = $product->getHeightCm();
            $itemDimensions['length'] = $product->getLengthCm();
            $itemDimensions['width'] = $product->getWidthCm();
            sort($itemDimensions);
            $packageDimensionZ += $itemDimensions[0] * $item->getQty();
        }

        /** @var Tags $tags */
        $tags = $this->tagsFactory->create();
        $tags->addTag('height_cm', (int)ceil($packageDimensionY));
        $tags->addTag('length_cm', (int)ceil($packageDimensionX));
        $tags->addTag('width_cm', (int)ceil($packageDimensionZ));
        $this->setTags($tags);
    }

    /**
     * @param float $amount
     * @return int
     */
    private function kilogramsToGrams($weight): int
    {
        return (int)round($weight * 1000);
    }
}

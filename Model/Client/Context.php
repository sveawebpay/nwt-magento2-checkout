<?php

namespace Svea\Checkout\Model\Client;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\PackageInfo;
use Svea\Checkout\Model\Client\DTO\GenericRequestFactory;

class Context
{
    private const MODULE_NAME = 'Svea_Checkout';

    private const EDITION_ABBREVIATIONS = [
        'Community' => 'CE',
        'Enterprise' => 'EE',
        'B2B' => 'EEB2B',
    ];

    /**
     * @var \Svea\Checkout\Helper\Data
     */
    protected $helper;

    /**
     * @var\Svea\Checkout\Logger
     */
    protected $logger;

    /**
     * @var GenericRequestFactory
     */
    private GenericRequestFactory $genericRequestFactory;

    /**
     * @var PackageInfo
     */
    private PackageInfo $packageInfo;

    /**
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

   /**
    * Constructor
    *
    * @param \Svea\Checkout\Helper\Data $helper
    * @param \Svea\Checkout\Logger\Logger $logger
    * @param GenericRequestFactory $genericRequestFactory
    * @param PackageInfo $packageInfo
    * @param ProductMetadataInterface $productMetadata
    */
    public function __construct(
        \Svea\Checkout\Helper\Data $helper,
        \Svea\Checkout\Logger\Logger $logger,
        GenericRequestFactory $genericRequestFactory,
        PackageInfo $packageInfo,
        ProductMetadataInterface $productMetadata
    ) {
        $this->helper        = $helper;
        $this->logger = $logger;
        $this->genericRequestFactory = $genericRequestFactory;
        $this->packageInfo = $packageInfo;
        $this->productMetadata = $productMetadata;
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->helper;
    }

    /**
     * @return \Svea\Checkout\Logger\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return GenericRequestFactory
     */
    public function getGenericRequestFactory(): GenericRequestFactory
    {
        return $this->genericRequestFactory;
    }

    /**
     * Get Svea_Checkout version from its composer.json
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return (string)$this->packageInfo->getVersion(self::MODULE_NAME);
    }

    /**
     * Get Magento version with edition abbreviation, e.g. "2.4.9 CE"
     *
     * @return string
     */
    public function getMagentoVersion(): string
    {
        $edition = $this->productMetadata->getEdition();
        $editionAbbreviation = self::EDITION_ABBREVIATIONS[$edition] ?? $edition;

        return $this->productMetadata->getVersion() . ' ' . $editionAbbreviation;
    }
}

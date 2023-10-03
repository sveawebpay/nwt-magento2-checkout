<?php declare(strict_types=1);

namespace Svea\Checkout\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use \Magento\Catalog\Model\Product\Type;
use \Magento\Bundle\Model\Product\Type as BundleType;
use \Magento\Downloadable\Model\Product\Type as DownloadableType;

/**
 * Adds the minum age product attribute
 */
class AddMinimumAgeProductAttr implements DataPatchInterface
{
    const ATTRIBUTE_CODE = 'svea_minimum_age';

    const ATTRIBUE_LABEL = 'Minimum Age for buying with Svea Checkout';

    /**
     * @var AttributeRepositoryInterface
     */
    private AttributeRepositoryInterface $attributeRepo;

    /**
     * @var EavSetupFactory
     */
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        AttributeRepositoryInterface $attributeRepo,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->attributeRepo = $attributeRepo;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        // Create the attribute if doesn't already exist
        try {
            $this->attributeRepo->get(Product::ENTITY, self::ATTRIBUTE_CODE);
        } catch (NoSuchEntityException $e) {
            $eavSetup = $this->eavSetupFactory->create();
            $applyToTypes = [
                Type::TYPE_SIMPLE,
                Type::TYPE_VIRTUAL,
                BundleType::TYPE_CODE,
                DownloadableType::TYPE_DOWNLOADABLE
            ];
            $eavSetup->addAttribute(
                Product::ENTITY,
                self::ATTRIBUTE_CODE,
                [
                    'group' => 'General',
                    'label' => self::ATTRIBUE_LABEL,
                    'type' => 'int',
                    'input' => 'text',
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'required' => false,
                    'user_defined' => false,
                    'apply_to' => implode(',', $applyToTypes),
                    'visible_on_front' => false,
                    'used_in_product_listing' => false,
                    'is_used_in_grid' => false,
                    'is_visible_in_grid' => false,
                    'is_filterable_in_grid' => false,
                ]
            );
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}

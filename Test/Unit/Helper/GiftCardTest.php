<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Helper\GiftCard;

/**
 * SC-310: Adobe Commerce gift card services come from the injected object manager, and only when
 * Magento_GiftCardAccount is enabled and installed.
 */
class GiftCardTest extends TestCase
{
    private const MANAGEMENT_INTERFACE = 'Magento\GiftCardAccount\Api\GiftCardAccountManagementInterface';

    public function testDoesNotLoadGiftCardServicesWhenModuleIsDisabled(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $helper = $this->makeHelper(false, $objectManager);

        // Without the module the helper does nothing instead of failing.
        $helper->deleteByQuoteId(1, 'CODE');
        $helper->saveByQuoteId(1, 'CODE');
        $this->addToAssertionCount(1);
    }

    public function testDoesNotLoadGiftCardServicesWhenModuleIsNotInstalled(): void
    {
        if (interface_exists(self::MANAGEMENT_INTERFACE)) {
            $this->markTestSkipped('Magento_GiftCardAccount is installed');
        }
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        // Enabled in config but the code is missing, e.g. a config.php copied from Adobe Commerce.
        $this->makeHelper(true, $objectManager)->deleteByQuoteId(1, 'CODE');
        $this->addToAssertionCount(1);
    }

    public function testKeepsWorkingWithEarlierConstructorArguments(): void
    {
        // A subclass calling parent::__construct() with the arguments from before the object manager
        $helper = new GiftCard(
            $this->makeModuleManager(false),
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(Json::class)
        );

        $helper->deleteByQuoteId(1, 'CODE');
        $this->addToAssertionCount(1);
    }

    public function testUsesPassedInServicesWhenModuleIsInstalled(): void
    {
        if (!interface_exists(self::MANAGEMENT_INTERFACE)) {
            $this->markTestSkipped('Magento_GiftCardAccount is only available on Adobe Commerce');
        }
        $management = $this->createMock(self::MANAGEMENT_INTERFACE);
        $management->expects($this->once())->method('deleteByQuoteId')->with(1, 'CODE');
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(fn ($class) => $this->createStub($class));

        $helper = new GiftCard(
            $this->makeModuleManager(true),
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(Json::class),
            null,
            null,
            null,
            $management,
            $objectManager
        );
        $helper->deleteByQuoteId(1, 'CODE');
    }

    private function makeHelper(bool $moduleEnabled, ObjectManagerInterface $objectManager): GiftCard
    {
        return new GiftCard(
            $this->makeModuleManager($moduleEnabled),
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(Json::class),
            null,
            null,
            null,
            null,
            $objectManager
        );
    }

    private function makeModuleManager(bool $moduleEnabled): Manager
    {
        $moduleManager = $this->createStub(Manager::class);
        $moduleManager->method('isEnabled')->willReturnMap([['Magento_GiftCardAccount', $moduleEnabled]]);

        return $moduleManager;
    }
}

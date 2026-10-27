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
    public function testDoesNotLoadGiftCardServicesWhenModuleIsDisabled(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $helper = $this->makeHelper($objectManager, false);

        // Without the module the helper does nothing instead of failing.
        $helper->deleteByQuoteId(1, 'CODE');
        $helper->saveByQuoteId(1, 'CODE');
        $this->addToAssertionCount(1);
    }

    public function testDoesNotLoadGiftCardServicesWhenModuleIsNotInstalled(): void
    {
        if (interface_exists('Magento\GiftCardAccount\Api\GiftCardAccountManagementInterface')) {
            $this->markTestSkipped('Magento_GiftCardAccount is installed');
        }
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        // Enabled in config but the code is missing, e.g. a config.php copied from Adobe Commerce.
        $this->makeHelper($objectManager, true)->deleteByQuoteId(1, 'CODE');
        $this->addToAssertionCount(1);
    }

    private function makeHelper(ObjectManagerInterface $objectManager, bool $moduleEnabled): GiftCard
    {
        $moduleManager = $this->createStub(Manager::class);
        $moduleManager->method('isEnabled')->willReturnMap([['Magento_GiftCardAccount', $moduleEnabled]]);

        return new GiftCard(
            $moduleManager,
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(Json::class),
            $objectManager
        );
    }
}

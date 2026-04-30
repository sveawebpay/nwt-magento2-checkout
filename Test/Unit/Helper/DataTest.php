<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Svea\Locale as SveaLocale;

class DataTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfigMock;

    /** @var Data */
    private $helper;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfigMock);

        $objectManager = new ObjectManagerHelper($this);
        $this->helper = $objectManager->getObject(
            Data::class,
            [
                'context'      => $context,
                'storeManager' => $this->createMock(StoreManagerInterface::class),
                'locale'       => $this->createMock(SveaLocale::class),
            ]
        );
    }

    public function testIsLegacySuccessPageEnabledReturnsTrueWhenFlagSet(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                'svea_checkout/settings/use_legacy_success_page',
                ScopeInterface::SCOPE_STORE,
                null
            )
            ->willReturn(true);

        $this->assertTrue($this->helper->isLegacySuccessPageEnabled());
    }

    public function testIsLegacySuccessPageEnabledReturnsFalseByDefault(): void
    {
        $this->scopeConfigMock->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->helper->isLegacySuccessPageEnabled());
    }

    public function testIsLegacySuccessPageEnabledForwardsStoreScope(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                'svea_checkout/settings/use_legacy_success_page',
                ScopeInterface::SCOPE_STORE,
                42
            )
            ->willReturn(true);

        $this->assertTrue($this->helper->isLegacySuccessPageEnabled(42));
    }
}

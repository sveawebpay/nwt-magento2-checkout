<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\HyvaThemeDetector;

/**
 * SC-310: Hyvä is detected through the injected object manager, only when the module is enabled
 * and its class exists.
 */
class HyvaThemeDetectorTest extends TestCase
{
    public function testIsFalseWithoutCallingHyvaWhenModuleIsDisabled(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $this->assertFalse($this->makeDetector($objectManager, false)->isHyvaTheme());
    }

    public function testAsksHyvaWhetherCurrentThemeIsHyva(): void
    {
        if (!class_exists('Hyva\Theme\Service\CurrentTheme')) {
            $this->markTestSkipped('Hyvä is not installed');
        }
        $currentTheme = $this->createStub('Hyva\Theme\Service\CurrentTheme');
        $currentTheme->method('isHyva')->willReturn(true);
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('get')
            ->with('Hyva\Theme\Service\CurrentTheme')
            ->willReturn($currentTheme);

        $this->assertTrue($this->makeDetector($objectManager, true)->isHyvaTheme());
    }

    private function makeDetector(ObjectManagerInterface $objectManager, bool $moduleEnabled): HyvaThemeDetector
    {
        $moduleManager = $this->createStub(Manager::class);
        $moduleManager->method('isEnabled')->willReturnMap([['Hyva_Theme', $moduleEnabled]]);

        return new HyvaThemeDetector($objectManager, $moduleManager);
    }
}

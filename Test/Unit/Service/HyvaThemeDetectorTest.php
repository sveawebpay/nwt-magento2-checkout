<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\HyvaThemeDetector;

/**
 * SC-310: Hyvä is detected through the injected object manager, only when the module is enabled
 * and its class exists. The class name is passed in, so every case runs with or without Hyvä.
 */
class HyvaThemeDetectorTest extends TestCase
{
    private const MISSING_CLASS = 'Svea\Checkout\Test\Unit\Service\MissingCurrentTheme';

    public function testIsFalseWithoutCallingHyvaWhenModuleIsDisabled(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $this->assertFalse($this->makeDetector($objectManager, false, self::MISSING_CLASS)->isHyvaTheme());
    }

    public function testIsFalseWithoutCallingHyvaWhenModuleIsEnabledButClassIsMissing(): void
    {
        // e.g. Hyva_Theme left enabled in config.php after the package was removed
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->never())->method('get');

        $this->assertFalse($this->makeDetector($objectManager, true, self::MISSING_CLASS)->isHyvaTheme());
    }

    public static function isHyvaProvider(): array
    {
        return ['Hyvä theme' => [true], 'other theme' => [false]];
    }

    #[DataProvider('isHyvaProvider')]
    public function testAsksHyvaWhetherCurrentThemeIsHyva(bool $isHyva): void
    {
        $currentTheme = new class($isHyva) {
            public function __construct(private bool $isHyva)
            {
            }

            public function isHyva(): bool
            {
                return $this->isHyva;
            }
        };
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects($this->once())
            ->method('get')
            ->with(get_class($currentTheme))
            ->willReturn($currentTheme);

        $this->assertSame(
            $isHyva,
            $this->makeDetector($objectManager, true, get_class($currentTheme))->isHyvaTheme()
        );
    }

    private function makeDetector(
        ObjectManagerInterface $objectManager,
        bool $moduleEnabled,
        string $currentThemeClass
    ): HyvaThemeDetector {
        $moduleManager = $this->createStub(Manager::class);
        $moduleManager->method('isEnabled')->willReturnMap([['Hyva_Theme', $moduleEnabled]]);

        return new HyvaThemeDetector($objectManager, $moduleManager, $currentThemeClass);
    }
}

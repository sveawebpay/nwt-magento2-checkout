<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;

/**
 * Tells whether the current theme is a Hyvä theme, without a hard dependency on Hyvä.
 *
 * Hyvä is optional, so its service cannot be a constructor or di.xml dependency: compilation would
 * fail on shops without it. It is created through the injected object manager, guarded by the module
 * status and class_exists().
 */
class HyvaThemeDetector
{
    private const CURRENT_THEME_SERVICE = 'Hyva\Theme\Service\CurrentTheme';

    private ObjectManagerInterface $objectManager;

    private Manager $moduleManager;

    private string $currentThemeClass;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param Manager $moduleManager
     * @param string $currentThemeClass Hyvä's CurrentTheme service; only replaced in tests
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Manager $moduleManager,
        string $currentThemeClass = self::CURRENT_THEME_SERVICE
    ) {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
        $this->currentThemeClass = $currentThemeClass;
    }

    /**
     * @return bool
     */
    public function isHyvaTheme(): bool
    {
        if (!$this->moduleManager->isEnabled('Hyva_Theme') || !class_exists($this->currentThemeClass)) {
            return false;
        }

        return (bool)$this->objectManager->get($this->currentThemeClass)->isHyva();
    }
}

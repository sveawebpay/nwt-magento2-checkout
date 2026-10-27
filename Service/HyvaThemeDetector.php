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

    public function __construct(ObjectManagerInterface $objectManager, Manager $moduleManager)
    {
        $this->objectManager = $objectManager;
        $this->moduleManager = $moduleManager;
    }

    /**
     * @return bool
     */
    public function isHyvaTheme(): bool
    {
        if (!$this->moduleManager->isEnabled('Hyva_Theme') || !class_exists(self::CURRENT_THEME_SERVICE)) {
            return false;
        }

        return (bool)$this->objectManager->get(self::CURRENT_THEME_SERVICE)->isHyva();
    }
}

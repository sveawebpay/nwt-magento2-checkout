<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\AbstractAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SC-308: every Svea admin controller declares its own ACL resource. Without one it falls back to
 * Magento_Backend::admin, which every admin role has.
 */
class AdminResourceTest extends TestCase
{
    public static function adminControllerProvider(): array
    {
        $baseDir = dirname(__DIR__, 4) . '/Controller/Adminhtml';
        $controllers = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($baseDir) + 1, -4);
            $class = 'Svea\\Checkout\\Controller\\Adminhtml\\' . str_replace('/', '\\', $relative);
            $controllers[$relative] = [$class];
        }
        ksort($controllers);

        return $controllers;
    }

    #[DataProvider('adminControllerProvider')]
    public function testDeclaresSveaAdminResource(string $class): void
    {
        $resource = (new \ReflectionClass($class))->getConstant('ADMIN_RESOURCE');

        $this->assertNotSame(AbstractAction::ADMIN_RESOURCE, $resource);
        $this->assertStringStartsWith('Svea_Checkout::', (string)$resource);
    }

    public function testCampaignCollectNeedsSveaConfigPermission(): void
    {
        $this->assertSame(
            'Svea_Checkout::system_config',
            \Svea\Checkout\Controller\Adminhtml\Campaign\Collect::ADMIN_RESOURCE
        );
    }
}

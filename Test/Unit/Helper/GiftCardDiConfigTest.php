<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Helper;

use PHPUnit\Framework\TestCase;

/**
 * SC-310: Magento passes the default (null) for an optional constructor argument unless di.xml configures
 * it, so the GiftCard helper's object manager must be configured, or it would fall back to
 * ObjectManager::getInstance() on every request.
 */
class GiftCardDiConfigTest extends TestCase
{
    public function testObjectManagerIsConfiguredForGiftCardHelper(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/etc/di.xml');
        $node = $xml->xpath(
            "/config/type[@name='Svea\\Checkout\\Helper\\GiftCard']/arguments/argument[@name='objectManager']"
        );

        $this->assertCount(1, $node);
        $this->assertSame('object', (string)$node[0]->attributes('xsi', true)->type);
        $this->assertSame('Magento\Framework\ObjectManagerInterface', (string)$node[0]);
    }
}

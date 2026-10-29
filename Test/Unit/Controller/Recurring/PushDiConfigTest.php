<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Controller\Recurring;

use PHPUnit\Framework\TestCase;

/**
 * SC-304: Magento passes the default (null) for an optional constructor argument unless di.xml configures
 * it, so the recurring push's repository and service must be configured, or they would come from
 * ObjectManager::getInstance() on every request.
 */
class PushDiConfigTest extends TestCase
{
    public function testOptionalArgumentsAreConfigured(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 4) . '/etc/di.xml');
        $arguments = [];
        foreach ($xml->xpath("/config/type[@name='Svea\\Checkout\\Controller\\Recurring\\Push']/arguments/argument") as $node) {
            $arguments[(string)$node['name']] = (string)$node;
        }

        $this->assertSame([
            'recurringInfoRepo' => 'Svea\Checkout\Api\RecurringInfoRepositoryInterface',
            'recurringInfoService' => 'Svea\Checkout\Service\SveaRecurringInfo\Proxy',
        ], $arguments);
    }
}

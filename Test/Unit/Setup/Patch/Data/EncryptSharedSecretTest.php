<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Setup\Patch\Data\EncryptSharedSecret;

/**
 * SC-311: existing plaintext shared secrets are encrypted at every scope, and applying the patch
 * again changes nothing.
 */
class EncryptSharedSecretTest extends TestCase
{
    public function testEncryptsPlaintextRowsOnly(): void
    {
        $updates = $this->apply([
            1 => 'default-secret',
            2 => '0:3:QWxyZWFkeUVuY3J5cHRlZA==',
            3 => '',
            4 => 'store-secret',
        ]);

        $this->assertSame([
            [['value' => 'encrypted(default-secret)'], ['config_id = ?' => 1]],
            [['value' => 'encrypted(store-secret)'], ['config_id = ?' => 4]],
        ], $updates);
    }

    public function testSecondRunChangesNothing(): void
    {
        $firstRun = $this->apply([1 => 'default-secret']);
        $encryptedValue = $firstRun[0][0]['value'];

        $this->assertSame([], $this->apply([1 => '0:3:' . base64_encode($encryptedValue)]));
    }

    /**
     * @param array<int, string> $rows config_id => value
     * @return array updates as [bind, where]
     */
    private function apply(array $rows): array
    {
        $updates = [];
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchPairs')->willReturn($rows);
        $connection->method('update')->willReturnCallback(function ($table, $bind, $where) use (&$updates) {
            $updates[] = [$bind, $where];
            return 1;
        });

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn('core_config_data');

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('encrypt')->willReturnCallback(fn ($value) => "encrypted($value)");

        (new EncryptSharedSecret($resource, $encryptor))->apply();

        return $updates;
    }
}

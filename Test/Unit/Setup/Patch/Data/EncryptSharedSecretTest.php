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
            5 => '1:3:Um90YXRlZEtleQ+/=',
            6 => 'secret:with:colons',
        ]);

        $this->assertSame([
            [['value' => 'encrypted(default-secret)'], ['config_id = ?' => 1]],
            [['value' => 'encrypted(store-secret)'], ['config_id = ?' => 4]],
            [['value' => 'encrypted(secret:with:colons)'], ['config_id = ?' => 6]],
        ], $updates);
    }

    public function testSecondRunLeavesItsOwnOutputAlone(): void
    {
        $firstRun = $this->apply([1 => 'default-secret'], true);
        $encryptedValue = $firstRun[0][0]['value'];

        $this->assertSame([], $this->apply([1 => $encryptedValue], true));
    }

    /**
     * @param array<int, string> $rows config_id => value
     * @param bool $realFormat encrypt into Magento's "<key version>:<cipher version>:<base64>" format
     * @return array updates as [bind, where]
     */
    private function apply(array $rows, bool $realFormat = false): array
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
        $encryptor->method('encrypt')->willReturnCallback(
            fn ($value) => $realFormat ? '0:3:' . base64_encode($value) : "encrypted($value)"
        );

        (new EncryptSharedSecret($resource, $encryptor))->apply();

        return $updates;
    }
}

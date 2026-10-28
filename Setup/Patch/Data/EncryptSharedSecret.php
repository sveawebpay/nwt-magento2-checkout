<?php declare(strict_types=1);

namespace Svea\Checkout\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Encrypts the Svea shared secret stored in plaintext before the field had the encrypted backend model.
 *
 * Covers every scope in core_config_data. Already encrypted rows are left alone, so applying it again
 * is safe. Values set in app/etc/env.php or config.php are not in the database and have to be set again
 * encrypted, with bin/magento config:set --lock-env.
 */
class EncryptSharedSecret implements DataPatchInterface
{
    public const CONFIG_PATH = 'svea_checkout/connection/shared_secret';

    /**
     * Magento's encrypted format: "<key version>:<cipher version>:<base64 data>"
     */
    private const ENCRYPTED_PATTERN = '/^\d+:\d+:[A-Za-z0-9+\/=]+$/';

    private ResourceConnection $resourceConnection;

    private EncryptorInterface $encryptor;

    public function __construct(ResourceConnection $resourceConnection, EncryptorInterface $encryptor)
    {
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('core_config_data');
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path = ?', self::CONFIG_PATH)
        );

        foreach ($rows as $configId => $value) {
            $value = (string)$value;
            if ($value === '' || preg_match(self::ENCRYPTED_PATTERN, $value)) {
                continue;
            }
            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int)$configId]
            );
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}

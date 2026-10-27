<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Config;

/**
 * Tells config values encrypted by Magento's encryptor apart from plaintext values.
 *
 * Encrypted values look like "<key version>:<cipher version>:<base64 data>". Values set in
 * app/etc/env.php or config.php may still be plaintext, and decrypting those returns garbage.
 */
class EncryptedValue
{
    private const ENCRYPTED_PATTERN = '/^\d+:\d+:[A-Za-z0-9+\/=]+$/';

    /**
     * @param string $value
     * @return bool
     */
    public static function isEncrypted(string $value): bool
    {
        return (bool)preg_match(self::ENCRYPTED_PATTERN, $value);
    }
}

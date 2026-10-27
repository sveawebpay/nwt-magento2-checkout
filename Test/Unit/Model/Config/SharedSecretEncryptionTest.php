<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Client\BaseClient;
use Svea\Checkout\Model\Client\Context as ClientContext;
use Svea\Checkout\Model\Config\EncryptedValue;
use Svea\Checkout\Model\Svea\Locale;

/**
 * SC-311: the shared secret is stored encrypted and decrypted when read. Plaintext values, e.g. from
 * app/etc/env.php, are still used as they are.
 */
class SharedSecretEncryptionTest extends TestCase
{
    private const PLAIN_SECRET = 'a1B2c3D4e5F6g7H8';
    private const ENCRYPTED_SECRET = '1:3:QmFzZTY0RW5jcnlwdGVkU2VjcmV0+/=';

    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
    }

    public static function valueProvider(): array
    {
        return [
            'encrypted, key version 0' => ['0:3:AbCdEf0123+/=', true],
            'encrypted, rotated key' => [self::ENCRYPTED_SECRET, true],
            'plaintext' => [self::PLAIN_SECRET, false],
            'plaintext with colon' => ['abc:def', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testTellsEncryptedValuesFromPlaintext(string $value, bool $isEncrypted): void
    {
        $this->assertSame($isEncrypted, EncryptedValue::isEncrypted($value));
    }

    public function testGetSharedSecretDecryptsEncryptedValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(self::ENCRYPTED_SECRET);
        $this->encryptor->expects($this->once())
            ->method('decrypt')
            ->with(self::ENCRYPTED_SECRET)
            ->willReturn(self::PLAIN_SECRET);

        $this->assertSame(self::PLAIN_SECRET, $this->makeHelper()->getSharedSecret());
    }

    public function testGetSharedSecretUsesPlaintextValueAsIs(): void
    {
        // e.g. set in app/etc/env.php before the field was encrypted
        $this->scopeConfig->method('getValue')->willReturn(self::PLAIN_SECRET);
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertSame(self::PLAIN_SECRET, $this->makeHelper()->getSharedSecret());
    }

    public function testRequestsAreSignedWithDecryptedSecret(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            fn ($path) => str_ends_with($path, 'shared_secret') ? self::ENCRYPTED_SECRET : 'merchant-1'
        );
        $this->encryptor->method('decrypt')->willReturn(self::PLAIN_SECRET);

        $clientContext = $this->createStub(ClientContext::class);
        $clientContext->method('getHelper')->willReturn($this->makeHelper());
        $client = new class($clientContext) extends BaseClient {
            protected function setGuzzleHttpClient(Data $helper, $storeView = null)
            {
            }
        };

        $sign = new \ReflectionMethod(BaseClient::class, 'createAuthorizationToken');
        $this->assertSame(
            'Svea ' . base64_encode('merchant-1:' . hash('sha512', '{}' . self::PLAIN_SECRET . '2026-10-27 10:00:00')),
            $sign->invoke($client, '2026-10-27 10:00:00', '{}')
        );
    }

    private function makeHelper(): Data
    {
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        return new Data(
            $context,
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(Locale::class),
            $this->encryptor
        );
    }
}

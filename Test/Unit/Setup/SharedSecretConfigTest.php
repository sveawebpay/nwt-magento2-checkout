<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Setup;

use PHPUnit\Framework\TestCase;

/**
 * SC-311: the shared secret is declared encrypted and sensitive, so core encrypts it on save, decrypts
 * it on config load and keeps it out of app/etc/config.php.
 */
class SharedSecretConfigTest extends TestCase
{
    private const PATH = 'svea_checkout/connection/shared_secret';
    private const ENCRYPTED_BACKEND = 'Magento\Config\Model\Config\Backend\Encrypted';

    public function testConfigXmlDeclaresEncryptedBackendModel(): void
    {
        $xml = $this->load('etc/config.xml');
        $node = $xml->xpath('/config/default/' . self::PATH);

        $this->assertCount(1, $node);
        $this->assertSame(self::ENCRYPTED_BACKEND, (string)$node[0]['backend_model']);
    }

    public function testAdminFieldIsObscureAndEncrypted(): void
    {
        $xml = $this->load('etc/adminhtml/system.xml');
        [$section, $group, $field] = explode('/', self::PATH);
        $node = $xml->xpath(
            "//section[@id='$section']/group[@id='$group']/field[@id='$field']"
        );

        $this->assertCount(1, $node);
        $this->assertSame('obscure', (string)$node[0]['type']);
        $this->assertSame(self::ENCRYPTED_BACKEND, (string)$node[0]->backend_model);
    }

    public function testPathIsMarkedSensitive(): void
    {
        $xml = $this->load('etc/di.xml');
        $node = $xml->xpath(
            "/config/type[@name='Magento\\Config\\Model\\Config\\TypePool']"
            . "/arguments/argument[@name='sensitive']/item[@name='" . self::PATH . "']"
        );

        $this->assertCount(1, $node);
        $this->assertSame('1', (string)$node[0]);
    }

    private function load(string $file): \SimpleXMLElement
    {
        $xml = simplexml_load_file(dirname(__DIR__, 3) . '/' . $file);
        $this->assertNotFalse($xml, $file . ' is not valid XML');

        return $xml;
    }
}

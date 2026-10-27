<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Client;

use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Client\LogRedactor;

/**
 * SC-306: Svea request and response data is logged without personal data or secrets.
 */
class LogRedactorTest extends TestCase
{
    private const HASH = '3f7a0c9e2b1d4e6f8a0b2c4d6e8f0a1b3c5d7e9f';
    private const TOKEN = '1c3b4b8f-6a2d-4e5f-9a7b-0c1d2e3f4a5b';

    private LogRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = new LogRedactor();
    }

    public function testRedactsPersonalDataInSveaOrderResponse(): void
    {
        // json_encode escapes slashes by default, as Svea responses and toJSON() do.
        $json = json_encode([
            'OrderId' => 9001,
            'ClientOrderNumber' => '100000123',
            'EmailAddress' => 'jane@example.com',
            'PhoneNumber' => '0701234567',
            'Customer' => ['NationalId' => '19800101-1234', 'IsCompany' => false],
            'BillingAddress' => ['FullName' => 'Jane Doe', 'StreetAddress' => 'Storgatan 1', 'PostalCode' => '11122'],
            'ShippingAddress' => ['FirstName' => 'Jane', 'LastName' => 'Doe', 'City' => 'Stockholm'],
            'Cart' => ['Items' => [['Name' => 'T-shirt', 'Quantity' => 100, 'UnitPrice' => 19900]]],
            'RecurringToken' => self::TOKEN,
        ]);

        $logged = $this->redactor->redact($json);
        $decoded = json_decode($logged, true);

        foreach (['jane@example.com', '0701234567', '19800101-1234', 'Jane', 'Storgatan', '11122', self::TOKEN] as $value) {
            $this->assertStringNotContainsString($value, $logged);
        }
        $this->assertSame(9001, $decoded['OrderId']);
        $this->assertSame('100000123', $decoded['ClientOrderNumber']);
        $this->assertSame('T-shirt', $decoded['Cart']['Items'][0]['Name']);
        $this->assertSame(LogRedactor::REDACTED, $decoded['Customer']);
        $this->assertSame(LogRedactor::REDACTED, $decoded['BillingAddress']);
    }

    public function testRedactsPresetValuesAndCallbackHashesInCreateOrderRequest(): void
    {
        $json = json_encode([
            'ClientOrderNumber' => '100000123',
            'PresetValues' => [['TypeName' => 'EmailAddress', 'Value' => 'jane@example.com', 'IsReadonly' => false]],
            'MerchantSettings' => [
                'PushUri' => 'https://shop.test/sveacheckout/order/push/sid/{checkout.order.uri}/hash/' . self::HASH . '/',
                'ConfirmationUri' => 'https://shop.test/sveacheckout/order/confirmation/hash/' . self::HASH . '/quote_id/42/',
            ],
        ]);

        $logged = $this->redactor->redact($json);

        $this->assertStringNotContainsString('jane@example.com', $logged);
        $this->assertStringNotContainsString(self::HASH, $logged);
        $this->assertStringContainsString('/hash/' . LogRedactor::REDACTED . '/quote_id/42/', $logged);
        $this->assertStringContainsString('100000123', $logged);
    }

    public function testRedactsRequestOptionsArray(): void
    {
        $options = [
            'headers' => ['Content-Type' => 'application/json', 'Timestamp' => '2026-10-27 10:00:00'],
            'json' => [
                'EmailAddress' => 'jane@example.com',
                'MerchantSettings' => [
                    'PushUri' => 'https://shop.test/sveacheckout/recurring/push/sid/1/token/' . self::TOKEN . '/storeid/1/',
                ],
            ],
        ];

        $logged = $this->redactor->redact($options);

        $this->assertStringNotContainsString('jane@example.com', $logged);
        $this->assertStringNotContainsString(self::TOKEN, $logged);
        $this->assertStringContainsString('application/json', $logged);
    }

    public function testRedactsTokenInEndpointAndPlainText(): void
    {
        $this->assertSame(
            '/api/tokens/' . LogRedactor::REDACTED . '/orders/9001',
            $this->redactor->redactUrls('/api/tokens/' . self::TOKEN . '/orders/9001')
        );
        $this->assertSame(
            'Client error: `GET https://api.test/api/tokens/' . LogRedactor::REDACTED . '/orders/9001` resulted in 404',
            $this->redactor->redact('Client error: `GET https://api.test/api/tokens/' . self::TOKEN . '/orders/9001` resulted in 404')
        );
    }

    public function testKeepsTextWithoutSecrets(): void
    {
        $this->assertSame('Internal Server Error', $this->redactor->redact('Internal Server Error'));
        $this->assertSame('', $this->redactor->redact(''));
    }
}

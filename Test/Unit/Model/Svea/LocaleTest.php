<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Model\Svea;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Model\Svea\Locale;

class LocaleTest extends TestCase
{
    private Locale $locale;

    protected function setUp(): void
    {
        $this->locale = new Locale();
    }

    public function testGetAllowedCurrenciesReturnsExpectedCurrencies(): void
    {
        $currencies = $this->locale->getAllowedCurrencies();

        $this->assertContains('SEK', $currencies);
        $this->assertContains('NOK', $currencies);
        $this->assertContains('DKK', $currencies);
        $this->assertContains('EUR', $currencies);
        $this->assertCount(4, $currencies);
    }

    public function testGetAllowedCountriesContainsNordicCountries(): void
    {
        $countries = $this->locale->getAllowedCountries();

        foreach (['SE', 'NO', 'DK', 'FI', 'DE', 'NL'] as $country) {
            $this->assertContains($country, $countries);
        }
    }

    #[DataProvider('localeByCountryCodeProvider')]
    public function testGetLocaleByCountryCodeReturnsCorrectLocale(string $country, string $expected): void
    {
        $this->assertSame($expected, $this->locale->getLocaleByCountryCode($country));
    }

    public static function localeByCountryCodeProvider(): array
    {
        return [
            'sweden'       => ['SE', 'sv-SE'],
            'norway'       => ['NO', 'nn-NO'],
            'denmark'      => ['DK', 'da-DK'],
            'finland'      => ['FI', 'fi-FI'],
            'germany'      => ['DE', 'de-DE'],
            'netherlands'  => ['NL', 'en-US'],
        ];
    }

    public function testGetLocaleByCountryCodeFallsBackToEnUs(): void
    {
        $this->assertSame('en-US', $this->locale->getLocaleByCountryCode('XX'));
    }

    public function testGetTestPresetValuesByCountryCodeIncludesEmailAddress(): void
    {
        $values = $this->locale->getTestPresetValuesByCountryCode('SE');

        $this->assertArrayHasKey('EmailAddress', $values);
        $this->assertSame(Locale::DEFAULT_TEST_EMAIL, $values['EmailAddress']);
        $this->assertArrayHasKey('PostalCode', $values);
    }

    public function testGetTestPresetValuesByCountryCodeReturnsEmptyForUnknownCountry(): void
    {
        $this->assertSame([], $this->locale->getTestPresetValuesByCountryCode('XX'));
    }

    #[DataProvider('defaultPostalCodeProvider')]
    public function testGetDefaultDataByCountryCodeReturnsPostalCode(string $country, string $expectedPostal): void
    {
        $data = $this->locale->getDefaultDataByCountryCode($country);

        $this->assertArrayHasKey('PostalCode', $data);
        $this->assertSame($expectedPostal, $data['PostalCode']);
    }

    public static function defaultPostalCodeProvider(): array
    {
        return [
            'sweden'  => ['SE', '111 22'],
            'norway'  => ['NO', '0010'],
            'denmark' => ['DK', '1000'],
            'finland' => ['FI', '00100'],
        ];
    }

    public function testGetDefaultDataByCountryCodeReturnsEmptyForUnknownCountry(): void
    {
        $this->assertSame([], $this->locale->getDefaultDataByCountryCode('XX'));
    }

    public function testIsValidCurrencyReturnsTrueForCorrectPairing(): void
    {
        $this->assertTrue($this->locale->isValidCurrency('SE', 'SEK'));
        $this->assertTrue($this->locale->isValidCurrency('NO', 'NOK'));
        $this->assertTrue($this->locale->isValidCurrency('FI', 'EUR'));
    }

    public function testIsValidCurrencyIsCaseInsensitive(): void
    {
        $this->assertTrue($this->locale->isValidCurrency('SE', 'sek'));
    }

    public function testIsValidCurrencyReturnsFalseForWrongCurrency(): void
    {
        $this->assertFalse($this->locale->isValidCurrency('SE', 'EUR'));
        $this->assertFalse($this->locale->isValidCurrency('NO', 'SEK'));
    }

    public function testIsValidCurrencyReturnsFalseForUnknownCountry(): void
    {
        $this->assertFalse($this->locale->isValidCurrency('XX', 'SEK'));
    }

    public function testGetCurrencyByCountryCodeReturnsCorrectCurrency(): void
    {
        $this->assertSame('SEK', $this->locale->getCurrencyByCountryCode('SE'));
        $this->assertSame('NOK', $this->locale->getCurrencyByCountryCode('NO'));
        $this->assertSame('EUR', $this->locale->getCurrencyByCountryCode('FI'));
    }

    public function testGetCurrencyByCountryCodeReturnsNullForUnknownCountry(): void
    {
        $this->assertNull($this->locale->getCurrencyByCountryCode('XX'));
    }
}

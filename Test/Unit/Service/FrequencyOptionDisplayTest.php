<?php declare(strict_types=1);

namespace Svea\Checkout\Test\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Svea\Checkout\Service\FrequencyOptionDisplay;

class FrequencyOptionDisplayTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        // Anonymous class so we can test the trait without a real consumer class
        $this->subject = new class {
            use FrequencyOptionDisplay;
        };
    }

    #[DataProvider('singularFrequencyProvider')]
    public function testSingularFrequencyRendersAsEveryUnit(string $option, string $expectedUnit): void
    {
        $result = (string)$this->subject->readableFrequencyOption($option);

        $this->assertStringContainsStringIgnoringCase($expectedUnit, $result);
        $this->assertStringContainsStringIgnoringCase('every', $result);
    }

    public static function singularFrequencyProvider(): array
    {
        return [
            'every month' => ['1|month', 'month'],
            'every week'  => ['1|week',  'week'],
            'every day'   => ['1|day',   'day'],
        ];
    }

    #[DataProvider('pluralFrequencyProvider')]
    public function testPluralFrequencyRendersNumberAndPluralUnit(
        string $option,
        string $expectedNumber,
        string $expectedUnit
    ): void
    {
        $result = (string)$this->subject->readableFrequencyOption($option);

        $this->assertStringContainsString($expectedNumber, $result);
        $this->assertStringContainsStringIgnoringCase($expectedUnit, $result);
    }

    public static function pluralFrequencyProvider(): array
    {
        return [
            '2 weeks'  => ['2|week',  '2', 'weeks'],
            '3 months' => ['3|month', '3', 'months'],
            '4 days'   => ['4|day',   '4', 'days'],
        ];
    }

    public function testSingularFrequencyDoesNotAppendPlural(): void
    {
        $result = (string)$this->subject->readableFrequencyOption('1|month');

        $this->assertStringNotContainsString('months', $result);
    }
}

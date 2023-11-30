<?php declare(strict_types=1);

namespace Svea\Checkout\Service;

use Magento\Framework\Phrase;

trait FrequencyOptionDisplay
{
    /**
     * Makes chosen frequency option for recurring payments more reaadble
     * Example: "1|month" => "Every month", "2|week" => "2 weeks"
     *
     * @param string $frequencyOption
     * @return Phrase
     */
    public function readableFrequencyOption(string $frequencyOption): Phrase
    {
        $parts = explode('|', $frequencyOption);
        $frequency = $parts[0];
        $unit = $parts[1];

        if ($frequency > 1) {
            $unit .= 's';
            return __('%1 %2', _($frequency), __($unit));
        }

        return __('Every %1', __($unit));
    }
}
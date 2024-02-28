<?php


namespace Svea\Checkout\Model\Svea;

class Locale
{
    const DEFAULT_TEST_EMAIL = 'test@example.com';

    /**
     * Swedish, Norway, Danish Kronor
     * @var array $allowedCurrencies
     */
    protected $allowedCurrencies = [
      "SEK","NOK","DKK","EUR"
    ];

    protected $allowedCountries = [
        "SE","NO","DK","FI","DE","NL","SJ","EE","LV","LT"
    ];

    protected $locales = [
        "SE" => [
            "locale" => "sv-SE",
            "currency" => "SEK",
            "test" => [
                "PhoneNumber" => "0811111111",
                "PostalCode" => "99999",
            ],
            "default" => [
                "PostalCode" => "111 22"
            ]
        ],
        "NO" => [
            "locale" => "nn-NO",
            "currency" => "NOK",
            "test" => [
                "PhoneNumber" => "21222222",
                "PostalCode" => "0359",
            ],
            "default" => [
                "PostalCode" => "0010"
            ]
        ],
        "DK" => [
            "locale" => "da-DK",
            "currency" => "DKK",
            "test" => [
                "PhoneNumber" => "22222222",
                "PostalCode" => "2100",
            ],
            "default" => [
                "PostalCode" => "1000"
            ]
        ],
        "FI" => [
            "locale" => "fi-FI",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "370",
            ],
            "default" => [
                "PostalCode" => "00100"
            ]
        ],
        "DE" => [
            "locale" => "de-DE",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "13591",
            ],
            "default" => [
                "PostalCode" => "10117"
            ]
        ],
        "NL" => [
            "locale" => "en-US",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "1111 CD",
            ],
            "default" => [
                "PostalCode" => "1011 AA"
            ]
        ],
        "SJ" => [
            "locale" => "nn-NO",
            "currency" => "NOK",
            "test" => [
                "PostalCode" => "9170",
            ],
            "default" => [
                "PostalCode" => "9170"
            ]
        ],
        "EE" => [
            "locale" => "en-US",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "10415"
            ],
            "default" => [
                "PostalCode" => "10415"
            ]
        ],
        "LV" => [
            "locale" => "en-US",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "LV-1050"
            ],
            "default" => [
                "PostalCode" => "LV-1050"
            ]
        ],
        "LT" => [
            "locale" => "en-US",
            "currency" => "EUR",
            "test" => [
                "PostalCode" => "01302"
            ],
            "default" => [
                "PostalCode" => "01302"
            ]
        ]
    ];

    /**
     * @return array
     */
    public function getAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    /**
     * @return array
     */
    public function getAllowedCountries()
    {
        return $this->allowedCountries;
    }

    /**
     * @param $countryCode string
     * @return string
     */
    public function getLocaleByCountryCode($countryCode)
    {
        if (array_key_exists($countryCode, $this->locales)) {
            return $this->locales[$countryCode]['locale'];
        }

        return "en-US";
    }

    /**
     * @param $countryCode string
     * @return array
     */
    public function getTestPresetValuesByCountryCode($countryCode)
    {
        if (isset($this->locales[$countryCode]['test'])) {
            $testData = $this->locales[$countryCode]['test'];
            $testData['EmailAddress'] = self::DEFAULT_TEST_EMAIL;
            return $testData;
        }

        return [];
    }

    public function getDefaultDataByCountryCode($countryCode)
    {
        if (isset($this->locales[$countryCode]['default'])) {
            return $this->locales[$countryCode]['default'];
        }

        return [];
    }

    public function isValidCurrency($countryCode, $currency)
    {
        if (!array_key_exists($countryCode, $this->locales)) {
            return false;
        }

        return $this->locales[$countryCode]['currency'] === strtoupper($currency);
    }

    public function getCurrencyByCountryCode($countryCode)
    {
        if (!array_key_exists($countryCode, $this->locales)) {
            return null;
        }

        return $this->locales[$countryCode]['currency'];
    }
}

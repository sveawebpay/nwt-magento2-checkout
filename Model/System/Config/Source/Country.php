<?php
namespace Svea\Checkout\Model\System\Config\Source;

class Country implements \Magento\Framework\Option\ArrayInterface
{


    /** @var \Magento\Directory\Model\Config\Source\Country $_country */
    protected $_country;

    /** @var \Svea\Checkout\Model\Svea\Locale $_locale */
    protected $_locale;

    /** @var array $_countryMap */
    private $_countryMap = [];

    public function __construct(
        \Magento\Directory\Model\Config\Source\Country $country,
        \Svea\Checkout\Model\Svea\Locale $locale
    )
    {
        $this->_locale = $locale;
        $this->_country = $country;
    }


    public function toOptionArray($isMultiselect=false)
    {
        $this->initCountryMap();

        $locales = $this->_locale->getAllowedCountries();
        $return = array();

        if(!$isMultiselect) {
            $return[] = array('value'=>'', 'label'=> '');
        }

        $mappedCountries = [];
        foreach($locales as $countryCode) {
            $label = $this->getCountryLabelByCode($countryCode);
            if ($label === null) {
                $label = $countryCode;
            }

            $mappedCountries[$label] = $countryCode;
        }

        // sort
        $sortedCountries = array_keys($mappedCountries);
        asort($sortedCountries);

        foreach ($sortedCountries as $country) {
            $return[] = array(
                'value'=>$mappedCountries[$country],
                'label'=>$country
            );
        }

        return $return;
    }

    private function initCountryMap()
    {
        $this->_countryMap = [];
        $countries = $this->_country->toOptionArray(false);
        foreach($countries as $country) {
            $this->_countryMap[$country['value']] = $country['label'];
        }

        return $this;
    }

    private function getCountryLabelByCode($countryCode)
    {
        if (array_key_exists($countryCode, $this->_countryMap)) {
            return $this->_countryMap[$countryCode];
        }

        return null;
    }
}
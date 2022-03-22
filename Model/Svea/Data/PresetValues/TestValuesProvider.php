<?php

namespace Svea\Checkout\Model\Svea\Data\PresetValues;

use Svea\Checkout\Model\Client\DTO\Order\PresetValue;
use Svea\Checkout\Helper\Data as Helper;

class TestValuesProvider implements PresetValuesProviderInterface
{

    /**
     * @var Helper
     */
    private $helper;

    /**
     * TestValuesProvider constructor.
     */
    public function __construct(Helper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @return string|null
     */
    public function getEmailAddress() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setEmailAddress('test@example.com');

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getPhoneNumber() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setPhoneNumber('0811111111');

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getIsCompany() : PresetValue
    {
        $customerType = $this->helper->getDefaultConsumerType();
        $customerTypes = $this->helper->getConsumerTypes();

        $isB2B = $customerType == 'B2B';

        $presetValue = new PresetValue();
        $presetValue->setIsCompany($isB2B);
        $presetValue->setValue($isB2B);

        $isReadOnly = true;
        if (is_array($customerTypes) && count($customerTypes) > 1) {
            $isReadOnly = false;
        }

        $presetValue->setIsReadOnly($isReadOnly);

        return $presetValue;
    }

    /**
     * @return mixed|string|null
     */
    public function getPostalCode() : PresetValue
    {
        $presetValue = new PresetValue();
        $presetValue->setPostalCode('99999');

        return $presetValue;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return [
            'EmailAddress'  => $this->getEmailAddress(),
            'PhoneNumber'   => $this->getPhoneNumber(),
            'PostalCode'    => $this->getPostalCode(),
            'IsCompany'     => $this->getIsCompany(),
        ];
    }
}

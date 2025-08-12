<?php
namespace Svea\Checkout\Model\Client\DTO;

use Svea\Checkout\Model\Client\DTO\Order\Address;
use Svea\Checkout\Model\Client\DTO\Order\Customer;
use Svea\Checkout\Model\Client\DTO\Order\GetOrder;
use Svea\Checkout\Model\Client\DTO\Order\Gui;
use Svea\Checkout\Model\Client\DTO\Order\IdentityFlags;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\PresetValue;

class GetOrderResponse extends GetOrder
{

    private $_data;

    /**
     * CreatePaymentResponse constructor.
     * @param $response string
     */
    public function __construct($response = "")
    {
        if ($response === "") {
            return;
        }

        $data = json_decode($response, true);
        $this->_data = $data;

        // We set the data here!

        $guiData = $this->get('Gui');
        if ($guiData) {
            $gui = new Gui();

            if (isset($guiData['Layout'])) {
                $gui->setLayout($guiData['Layout']);
            }

            if (isset($guiData['Snippet'])) {
                $gui->setSnippet($guiData['Snippet']);
            }

            $this->setGui($gui);
        }


        $this->setOrderId($this->get('OrderId'));
        $this->setStatus($this->get('Status'));

        $this->setLocale($this->get('Locale'));
        $this->setCurrency($this->get('Currency'));
        $this->setCountryCode($this->get('CountryCode'));
        $this->setClientOrderNumber($this->get('ClientOrderNumber'));

        $this->setEmailAddress($this->get('EmailAddress'));
        $this->setPhoneNumber($this->get('PhoneNumber'));
        $this->setPaymentType($this->get('PaymentType'));
        $payment = $this->get('Payment');
        if ($payment && isset($payment['PaymentMethodType'])) {
            $this->setPaymentMethodType($payment['PaymentMethodType']);
        }
        $this->setCustomerReference($this->get('CustomerReference'));
        $this->setSveaWillBuyOrder($this->get('SveaWillBuyOrder'));


        $merchantDataString = $this->get('MerchantData');
        $merchantDataResponse = new MerchantDataResponse();
        $merchantDataResponse->setDataFromResponse($merchantDataString);

        $this->setMerchantData($merchantDataResponse);

        $flags = $this->get('IdentityFlags');
        if ($flags) {
            $flagObj = new IdentityFlags();

            if (isset($flags['HideNotYou'])) {
                $flagObj->setHideNotYou($flags['HideNotYou']);
            }

            if (isset($flags['HideChangeAddress'])) {
                $flagObj->setHideChangeAddress($flags['HideChangeAddress']);
            }

            if (isset($flags['HideAnonymous'])) {
                $flagObj->setHideAnonymous($flags['HideAnonymous']);
            }

            $this->setIdentityFlags($flagObj);
        }

        $presetValuesResponse = $this->get('PresetValues');
        if ($presetValuesResponse && is_array($presetValuesResponse)) {
            $presetValues = [];
            foreach ($presetValuesResponse as $presetValueResponse) {
                $presetValue = new PresetValue();
                $presetValue->setTypeName($presetValueResponse['TypeName']);
                $presetValue->setValue($presetValueResponse['Value']);
                $presetValue->setIsReadOnly($presetValueResponse['IsReadonly']);

                $presetValues[] = $presetValue;
            }

            $this->setPresetValues($presetValues);
        }


        $merchantSettingsResponse = $this->get('MerchantSettings');
        if ($merchantSettingsResponse) {
            $merchantSettings = new MerchantSettings();

            if (isset($merchantSettingsResponse['CheckoutValidationCallBackUri'])) {
                $merchantSettings->setCheckoutValidationCallBackUri($merchantSettingsResponse['CheckoutValidationCallBackUri']);
            }

            if (isset($merchantSettingsResponse['PushUri'])) {
                $merchantSettings->setPushUri($merchantSettingsResponse['PushUri']);
            }

            if (isset($merchantSettingsResponse['TermsUri'])) {
                $merchantSettings->setTermsUri($merchantSettingsResponse['TermsUri']);
            }

            if (isset($merchantSettingsResponse['CheckoutUri'])) {
                $merchantSettings->setCheckoutUri($merchantSettingsResponse['CheckoutUri']);
            }

            if (isset($merchantSettingsResponse['ConfirmationUri'])) {
                $merchantSettings->setConfirmationUri($merchantSettingsResponse['ConfirmationUri']);
            }

            if (isset($merchantSettingsResponse['ActivePartPaymentCampaigns'])) {
                $merchantSettings->setActivePartPaymentCampaigns($merchantSettingsResponse['ActivePartPaymentCampaigns']);
            }

            $this->setMerchantSettings($merchantSettings);
        }

        $customerResponse = $this->get('Customer');
        if ($customerResponse) {
            $customer = new Customer();
            if (isset($customerResponse['Id'])) {
                $customer->setId($customerResponse['Id']);
            }

            if (isset($customerResponse['NationalId'])) {
                $customer->setNationalId($customerResponse['NationalId']);
            }

            if (isset($customerResponse['IsCompany'])) {
                $customer->setIsCompany($customerResponse['IsCompany']);
            }

            if (isset($customerResponse['CountryCode'])) {
                $customer->setCountryCode($customerResponse['CountryCode']);
            }

            $this->setCustomer($customer);
        }

        $shippingAddressResponse = $this->get('ShippingAddress');
        if ($shippingAddressResponse) {
            $this->setShippingAddress($this->generateAddressFromResponse($shippingAddressResponse));
        }

        $billingAddressResponse = $this->get('BillingAddress');
        if ($billingAddressResponse) {
            $this->setBillingAddress($this->generateAddressFromResponse($billingAddressResponse));
        }

        if (isset($data['Cart']['Items'])) {
            $items = $data['Cart']['Items'];
            $orderRows = [];
            foreach ($items as $item) {
                $orderRow = new OrderRow();

                // fill
                $orderRow
                    ->setArticleNumber($item['ArticleNumber'])
                    ->setName($item['Name'])
                    ->setQuantity($item['Quantity'])
                    ->setUnitPrice($item['UnitPrice'])
                    ->setUnit($item['Unit'])
                    ->setDiscountPercent($item['DiscountPercent'])
                    ->setVatPercent($item['VatPercent'])
                    ->setTemporaryReference($item['TemporaryReference'])
                    ->setRowNumber($item['RowNumber'])
                    ->setMerchantData($item['MerchantData']);

                // add to array
                $orderRows[] = $orderRow;
            }

            $this->setCartItems($orderRows);
        }

        if ($this->get('Recurring')) {
            $this->setRecurring($this->get('Recurring'));
            $this->setRecurringToken($this->get('RecurringToken'));
        }
    }

    private function generateAddressFromResponse($response)
    {
        $address = new Address();

        if (isset($response['FullName'])) {
            $address->setFullName($response['FullName']);
        }

        if (isset($response['FirstName'])) {
            $address->setFirstName($response['FirstName']);
        }

        if (isset($response['LastName'])) {
            $address->setLastName($response['LastName']);
        }

        if (isset($response['StreetAddress'])) {
            $address->setStreetAddress($response['StreetAddress']);
        }

        if (isset($response['CoAddress'])) {
            $address->setCoAddress($response['CoAddress']);
        }

        if (isset($response['PostalCode'])) {
            $address->setPostalCode($response['PostalCode']);
        }

        if (isset($response['City'])) {
            $address->setCity($response['City']);
        }

        if (isset($response['CountryCode'])) {
            $address->setCountryCode($response['CountryCode']);
        }

        if (isset($response['IsGeneric']) && $response['IsGeneric'] !== null) {
            $address->setIsGeneric($response['IsGeneric']);
        }

        if (isset($response['AddressLines']) && $response['AddressLines'] !== null) {
            $address->setAddressLines($response['AddressLines']);
        }

        return $address;
    }

    private function get($key)
    {
        if (array_key_exists($key, $this->_data)) {
            return $this->_data[$key];
        }

        return null;
    }

    /** @return mixed */
    public function getHttpResponse()
    {
        return $this->_data;
    }

}
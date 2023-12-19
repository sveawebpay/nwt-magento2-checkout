<?php

namespace Svea\Checkout\Model\Client\Api;

use Svea\Checkout\Model\Client\ApiClient;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\Context;

class CampaignManagement extends ApiClient
{
    /**
     * @var \Svea\Checkout\Model\CampaignInfoFactory
     */
    private $campaignInfoFactory;

    /**
     * CampaignManagement constructor.
     */
    public function __construct(
        \Svea\Checkout\Model\CampaignInfoFactory $campaignInfoFactory,
        Context $apiContext
    ) {
        parent::__construct($apiContext);
        $this->campaignInfoFactory = $campaignInfoFactory;
    }

    /**
     * @param null|int|string $store
     * @return array
     * @throws ClientException
     */
    public function getAvailablePartPaymentCampaigns($store = null)
    {
        if (null !== $store) {
            $this->resetCredentials($store);
        }
        $response = $this->get('api/util/GetAvailablePartPaymentCampaigns?isCompany=false');

        return $this->parseCampaigns($response);
    }

    /**
     * @param string $response
     *
     * @return array
     */
    private function parseCampaigns(string $response)
    {
        $campaignItems = [];
        foreach (\json_decode($response, true) ?: [] as $item) {
            $campaignItems[] = $this->fromCammelCase($item);
        }

        return $campaignItems;
    }

    /**
     * @param array $inputArray
     *
     * @return array
     */
    private function fromCammelCase(array $inputArray)
    {
        $pattern = '!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!';

        $keys = [];
        foreach ($inputArray as $arrayKey => $item) {
            preg_match_all($pattern, $arrayKey, $matches);
            $ret = $matches[0];
            foreach ($ret as &$match) {
                $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
            }
            $keys[implode('_', $ret)] = $item;
        }


        return $keys;
    }
}

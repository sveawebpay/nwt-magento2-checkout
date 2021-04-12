<?php

namespace Svea\Checkout\Api\Data;

use Magento\Catalog\Api\Data\ProductInterface;

interface CampaignInfoInterface
{
    /**
     * @return string
     */
    public function getCampaignCode() : int;

    /**
     * @return string
     */
    public function getDescription() : string;

    /**
     * @return int
     */
    public function getPaymentPlanType() : int;

    /**
     * @return string
     */
    public function getContractLengthInMonths() : int;

    /**
     * @return string
     */
    public function getMonthlyAnnuityFactor() : string;

    /**
     * @return string
     */
    public function getInitialFee() : string;

    /**
     * @return string
     */
    public function getNotificationFee() : string;

    /**
     * @return string
     */
    public function getInterestRatePercent() : string;

    /**
     * @return string
     */
    public function getNumberOfInterestFreeMonths() : int;

    /**
     * @return string
     */
    public function getNumberOfPaymentFreeMonths() : int;

    /**
     * @return string
     */
    public function getFromAmount() : string;

    /**
     * @return string
     */
    public function getToAmount() : string;

    /**
     * @return string
     */
    public function getCampaignPrice();

    /**
     * @return float
     */
    public function getUnformattedCampaignPrice();

    /**
     * @param float $price
     *
     * @return mixed
     */
    public function setProductPrice(float $price);
}
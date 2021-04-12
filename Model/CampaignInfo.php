<?php declare(strict_types=1);

namespace Svea\Checkout\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Svea\Checkout\Api\Data\CampaignInfoInterface;
use Svea\Checkout\Model\Resource\CampaignInfo as CampaignInfoResource;

class CampaignInfo extends AbstractModel implements CampaignInfoInterface
{
    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var float
     */
    private $productPrice;

    /**
     * CampaignInfo constructor.
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        PriceCurrencyInterface $priceCurrency,
        array $data = []

    ) {
        $this->priceCurrency = $priceCurrency;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @param float $price
     */
    public function setProductPrice(float $price)
    {
        $this->productPrice = $price;
    }

    /**
     *  Model Construct
     */
    protected function _construct()
    {
        $this->_init(CampaignInfoResource::class);
    }

    /**
     * @return int
     */
    public function getCampaignCode(): int
    {
        return $this->getData('campaign_code');
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->getData('description');
    }

    /**
     * @return int
     */
    public function getPaymentPlanType(): int
    {
        return $this->getData('payment_plan_type');
    }

    /**
     * @return int
     */
    public function getContractLengthInMonths(): int
    {
        return $this->getData('contract_length_in_months');
    }

    /**
     * @return string
     */
    public function getMonthlyAnnuityFactor(): string
    {
        return $this->getData('monthly_annuity_factor');
    }

    /**
     * @return string
     */
    public function getInitialFee(): string
    {
        return $this->getData('initial_fee');
    }

    /**
     * @return string
     */
    public function getNotificationFee(): string
    {
        return $this->getData('notification_fee');
    }

    /**
     * @return string
     */
    public function getInterestRatePercent(): string
    {
        return $this->getData('interest_rate_percent');
    }

    /**
     * @return int
     */
    public function getNumberOfInterestFreeMonths(): int
    {
        return $this->getData('number_of_interest_free_months');
    }

    /**
     * @return int
     */
    public function getNumberOfPaymentFreeMonths(): int
    {
        return $this->getData('number_of_payment_free_months');
    }

    /**
     * @return string
     */
    public function getFromAmount(): string
    {
        return $this->getData('from_amount');
    }

    /**
     * @return string
     */
    public function getToAmount(): string
    {
        return $this->getData('to_amount');
    }

    /**
     * @return string
     */
    public function getFormatedPrice()
    {
        return $this->priceCurrency->format($this->productPrice);
    }

    /**
     *
     * @return string
     */
    public function getCampaignPrice()
    {
        $notificationFee =  $this->getNotificationFee();
        $monthlyAnnuallyFactor = $this->getMonthlyAnnuityFactor();
        $finalPrice = round(($this->productPrice * $monthlyAnnuallyFactor) + $notificationFee);

        return $this->priceCurrency->format($finalPrice);
    }

    /**
     *
     * @return float
     */
    public function getUnformattedCampaignPrice()
    {
        $notificationFee =  $this->getNotificationFee();
        $monthlyAnnuallyFactor = $this->getMonthlyAnnuityFactor();
        $finalPrice = round(($this->productPrice * $monthlyAnnuallyFactor) + $notificationFee);

        return $finalPrice;
    }
}
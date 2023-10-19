<?php
namespace Svea\Checkout\Model\Client\DTO\Order;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;

class MerchantSettings extends AbstractRequest
{

    /**
     * An optional URI to a location that is expecting callbacks from the Checkout to validate order’s
     * stock status and also the possibility to update checkout with an updated ClientOrderNumber.
     * May contain a {checkout.order.uri} placeholder which will be replaced with the checkoutorderid.
     *
     * Requests for this endpoint are made with HTTP Method GET.
     * Your response's HTTP Status Code is interpreted as: 200-299 is interpreted as validation passed.
     * Everything else is interpreted as validation failure.
     * See https://checkoutapi.svea.com/docs/html/reference/web-api/data-types/checkoutvalidationcallbackresponse.htm for a description of the expected response content.
     * @var $CheckoutValidationCallBackUri string
     */
    protected $CheckoutValidationCallBackUri;

    /**
     * URI to a location that is expecting callbacks from the Checkout whenever an order’s state is changed (confirmed, final, etc.).
     * May contain a {checkout.order.uri} placeholder which will be replaced with the checkoutorderid.
     *
     * Requests for this endpoint are made with HTTP Method POST.
     * Your response's HTTP Status Code is interpreted as:
     *      100-199 are ignored.
     *      200-299 is interpreted as OK.
     *      300-399 are ignored.
     *      404 - the order hasn't been created on your side yet. We will try pushing again.
     *      All other 400 status codes are ignored.
     *      500 and above is interpreted as error on your side. We will try pushing again.
     *
     * @var $PushUri string
     */
    protected $PushUri;

    /**
     * URI to a page with webshop specific terms. ,
     * @var $TermsUri string
     */
    protected $TermsUri;

    /**
     * URI to the page in the webshop displaying the Checkout. May not contain order specific information.
     * @var $CheckoutUri string
     */
    protected $CheckoutUri;

    /**
     * URI to the page in the webshop displaying specific information to a customer after the order has been confirmed.
     * May not contain order specific information.
     * @var $ConfirmationUri string
     */
    protected $ConfirmationUri;


    /**
     * Optional
     * List of valid CampaignIDs. If used then list of available part payment campaign options will be filtered through the chosen list. ,
     * @var $ActivePartPaymentCampaigns int[]
     */
    protected $ActivePartPaymentCampaigns;

    /**
     * Optional
     * Valid CampaignID. If used then the chosen campaign will be shown as the first payment method in all payment method lists.
     * @var $PromotedPartPaymentCampaign int
     */
    protected $PromotedPartPaymentCampaign;

    /**
     * @var int|null
     */
    protected ?int $storeId = null;

    /**
     * @return string
     */
    public function getCheckoutValidationCallBackUri()
    {
        return $this->CheckoutValidationCallBackUri;
    }

    /**
     * @param string $checkoutValidationCallBackUri
     * @return MerchantSettings
     */
    public function setCheckoutValidationCallBackUri($checkoutValidationCallBackUri)
    {
        if (null !== $this->storeId) {
            $checkoutValidationCallBackUri = rtrim($checkoutValidationCallBackUri, '/') . '/';
            $checkoutValidationCallBackUri .= "store_id/{$this->storeId}";
        }
        $this->CheckoutValidationCallBackUri = $checkoutValidationCallBackUri;
        return $this;
    }

    /**
     * @return string
     */
    public function getTermsUri()
    {
        return $this->TermsUri;
    }

    /**
     * @param string $TermsUri
     * @return MerchantSettings
     */
    public function setTermsUri($TermsUri)
    {
        $this->TermsUri = $TermsUri;
        return $this;
    }

    /**
     * @return string
     */
    public function getCheckoutUri()
    {
        return $this->CheckoutUri;
    }

    /**
     * @param string $CheckoutUri
     * @return MerchantSettings
     */
    public function setCheckoutUri($CheckoutUri)
    {
        $this->CheckoutUri = $CheckoutUri;
        return $this;
    }

    /**
     * @return string
     */
    public function getConfirmationUri()
    {
        return $this->ConfirmationUri;
    }

    /**
     * @param string $ConfirmationUri
     * @return MerchantSettings
     */
    public function setConfirmationUri($ConfirmationUri)
    {
        $this->ConfirmationUri = $ConfirmationUri;
        return $this;
    }

    /**
     * @return int[]
     */
    public function getActivePartPaymentCampaigns()
    {
        return $this->ActivePartPaymentCampaigns;
    }

    /**
     * @param int[] $ActivePartPaymentCampaigns
     * @return MerchantSettings
     */
    public function setActivePartPaymentCampaigns($ActivePartPaymentCampaigns)
    {
        $this->ActivePartPaymentCampaigns = $ActivePartPaymentCampaigns;
        return $this;
    }

    /**
     * @return int
     */
    public function getPromotedPartPaymentCampaign()
    {
        return $this->PromotedPartPaymentCampaign;
    }

    /**
     * @param int $PromotedPartPaymentCampaign
     * @return MerchantSettings
     */
    public function setPromotedPartPaymentCampaign($PromotedPartPaymentCampaign)
    {
        $this->PromotedPartPaymentCampaign = $PromotedPartPaymentCampaign;
        return $this;
    }

    /**
     * @return string
     */
    public function getPushUri()
    {
        return $this->PushUri;
    }

    /**
     * @param string $pushUri
     * @return MerchantSettings
     */
    public function setPushUri($pushUri)
    {
        if (null !== $this->storeId) {
            $pushUri = rtrim($pushUri, '/') . '/';
            $pushUri .= "store_id/{$this->storeId}";
        }
        $this->PushUri = $pushUri;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    /**
     * @param int $storeId
     * @return MerchantSettings
     */
    public function setStoreId(int $storeId): MerchantSettings
    {
        $this->storeId = $storeId;
        return $this;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [];

        if ($this->getActivePartPaymentCampaigns()) {
            $data['ActivePartPaymentCampaigns'] = $this->getActivePartPaymentCampaigns();
        }

        if ($this->getPromotedPartPaymentCampaign()) {
            $data['PromotedPartPaymentCampaign'] = $this->getPromotedPartPaymentCampaign();
        }

        if ($this->getCheckoutUri()) {
            $data['CheckoutUri'] = $this->getCheckoutUri();
        }

        if ($this->getConfirmationUri()) {
            $data['ConfirmationUri'] = $this->getConfirmationUri();
        }

        if ($this->getTermsUri()) {
            $data['TermsUri'] = $this->getTermsUri();
        }

        if ($this->getCheckoutValidationCallBackUri()) {
            $data['CheckoutValidationCallBackUri'] = $this->getCheckoutValidationCallBackUri();
        }

        if ($this->getPushUri()) {
            $data['PushUri'] = $this->getPushUri();
        }

        return $data;
    }

}
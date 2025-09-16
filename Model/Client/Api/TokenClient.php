<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\Api;

use Magento\Quote\Model\Quote;
use Svea\Checkout\Model\Client\DTO\Token\PatchTokenFactory;
use Svea\Checkout\Model\Client\ApiClient;
use Svea\Checkout\Model\Client\Context;
use Svea\Checkout\Model\Client\DTO\Token\CreateRecurringOrderFactory;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettingsFactory;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Svea\Items;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\Token\GetTokenOrderResponseFactory;
use Svea\Checkout\Model\Client\DTO\Token\GetTokenOrderResponse;
use Svea\Checkout\Model\Client\DTO\Token\PaymentMethodsResponseFactory;
use Svea\Checkout\Model\Client\DTO\Token\PaymentMethodsResponse;
use Svea\Checkout\Model\Client\DTO\GenericRequestFactory;
use Magento\Framework\Math\Random;

class TokenClient extends ApiClient
{
    private PatchTokenFactory $patchTokenFactory;

    private CreateRecurringOrderFactory $createRecurringOrderFactory;

    private MerchantSettingsFactory $merchantSettingsFactory;

    private GetTokenOrderResponseFactory $getTokenOrderResponseFactory;

    private GenericRequestFactory $genericRequestFactory;

    private PaymentMethodsResponseFactory $paymentMethodsResponseFactory;

    private Data $helper;

    private Items $itemsHelper;

    private Random $random;

    public function __construct(
        Context $apiContext,
        PatchTokenFactory $patchTokenFactory,
        CreateRecurringOrderFactory $createRecurringOrderFactory,
        MerchantSettingsFactory $merchantSettingsFactory,
        GetTokenOrderResponseFactory $getTokenOrderResponseFactory,
        GenericRequestFactory $genericRequestFactory,
        PaymentMethodsResponseFactory $paymentMethodsResponseFactory,
        Data $helper,
        Items $itemsHelper,
        Random $random
    ) {
        parent::__construct($apiContext);
        $this->patchTokenFactory = $patchTokenFactory;
        $this->createRecurringOrderFactory = $createRecurringOrderFactory;
        $this->merchantSettingsFactory = $merchantSettingsFactory;
        $this->getTokenOrderResponseFactory = $getTokenOrderResponseFactory;
        $this->genericRequestFactory = $genericRequestFactory;
        $this->paymentMethodsResponseFactory = $paymentMethodsResponseFactory;
        $this->helper = $helper;
        $this->itemsHelper = $itemsHelper;
        $this->random = $random;
    }

    /**
     * Create a recurring order using token and Quote
     * @link https://checkoutapi.svea.com/docs/recurring/#/token-api?id=create-recurring-order
     *
     * @param string $token
     * @param Quote $quote
     * @return void
     * @throws ClientException
     */
    public function createRecurringOrder(string $token, Quote $quote): void
    {
        $merchantSettings = $this->merchantSettingsFactory->create();
        $pushUri = $this->helper->getRecurringPushUrl(
            $token,
            (int)$quote->getStoreId()
        );
        $merchantSettings->setPushUri($pushUri);

        $cartItems = $this->itemsHelper->generateOrderItemsFromQuote($quote);
        $cartItems = $this->itemsHelper->fixCartItems($cartItems);

        $createRecurringOrder = $this->createRecurringOrderFactory->create();
        $createRecurringOrder->setCurrency($quote->getQuoteCurrencyCode());
        $createRecurringOrder->setCartItems($cartItems);

        $prefix = $quote->getReservedOrderId() . '-';
        $clientOrderNumber = substr($this->random->getUniqueHash($prefix), 0, 32);
        $quote->setSveaClientOrderId($clientOrderNumber);
        $quote->getPayment()->setAdditionalInformation('svea_client_order_number', $clientOrderNumber);
        $createRecurringOrder->setClientOrderNumber($clientOrderNumber);

        $createRecurringOrder->setMerchantSettings($merchantSettings);
        $createRecurringOrder->setPartnerKey($this->helper->getPartnerKey());

        $endpoint = sprintf('/api/tokens/%s/orders', $token);
        $this->resetCredentials($quote->getStoreId());
        $response = $this->post($endpoint, $createRecurringOrder);

        $createdOrder = $this->getTokenOrderResponseFactory->create();
        $createdOrder->populateWithJson($response);
        $quote->getPayment()->setAdditionalInformation('svea_order_id', $createdOrder->getOrderId());
        $quote->setSveaOrderId($createdOrder->getOrderId());
    }

    /**
     * Gets token order
     * @link https://checkoutapi.svea.com/docs/recurring/#/token-api?id=get-token-order
     *
     * @param integer $orderId
     * @param string $token
     * @return GetTokenOrderResponse
     * @throws ClientException
     */
    public function getTokenOrder(int $orderId, string $token): GetTokenOrderResponse
    {
        $endpoint = sprintf('/api/tokens/%s/orders/%s', $token, $orderId);
        $response = $this->get($endpoint);
        $getOrderResponse = $this->getTokenOrderResponseFactory->create();
        $getOrderResponse->populateWithJson($response);
        return $getOrderResponse;
    }

    /**
     * Cancels a recurring token
     * @link https://checkoutapi.svea.com/docs/recurring/#/token-api?id=patch-token
     *
     * @param $token
     * @return void
     * @throws ClientException
     */
    public function cancelToken(string $token): void
    {
        $patchToken = $this->patchTokenFactory->create();
        $patchToken->setStatus('Cancelled');
        $this->patch('/api/tokens/' . $token, $patchToken);
    }

    /**
     * Get snippet for customer to change payment method for their recurring order
     * @see https://docs.payments.svea.com/docs/checkout/recurring-orders/change-payment-method
     *
     * @param string $token Active Recurring Token
     * @param string $termsUrl
     * @return string
     * @throws ClientException
     */
    public function changePaymentMethod(string $token, string $termsUrl): string
    {
        $request = $this->genericRequestFactory->create();
        $request->setData(['TermsUrl' => $termsUrl]);
        $response = $this->post(sprintf('/api/tokens/%s/payment-methods', $token), $request);
        $responseObj = $this->paymentMethodsResponseFactory->create();
        /** @var PaymentMethodsResponse $responseObj */
        $responseObj->populateWithJson($response);
        return $responseObj->getSnippet();
    }
}

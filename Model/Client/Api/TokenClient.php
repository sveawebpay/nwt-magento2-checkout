<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\Api;

use Magento\Quote\Model\Quote;
use Svea\Checkout\Model\Client\DTO\Token\PatchTokenFactory;
use Svea\Checkout\Model\Client\ApiClient;
use Svea\Checkout\Model\Client\Context;
use Svea\Checkout\Model\Client\DTO\Token\CreateRecurringOrderFactory;
use Svea\Checkout\Model\Client\DTO\Token\CreateRecurringOrder;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettingsFactory;
use Svea\Checkout\Helper\Data;
use Svea\Checkout\Model\Svea\Items;
use Svea\Checkout\Model\Client\ClientException;
use Svea\Checkout\Model\Client\DTO\Token\GetTokenOrderResponseFactory;
use Svea\Checkout\Model\Client\DTO\Token\GetTokenOrderResponse;

class TokenClient extends ApiClient
{
    private PatchTokenFactory $patchTokenFactory;

    private CreateRecurringOrderFactory $createRecurringOrderFactory;

    private MerchantSettingsFactory $merchantSettingsFactory;

    private GetTokenOrderResponseFactory $getTokenOrderResponseFactory;

    private Data $helper;

    private Items $itemsHelper;

    private int $clientOrderNumberSequence = 0;

    public function __construct(
        Context $apiContext,
        PatchTokenFactory $patchTokenFactory,
        CreateRecurringOrderFactory $createRecurringOrderFactory,
        MerchantSettingsFactory $merchantSettingsFactory,
        GetTokenOrderResponseFactory $getTokenOrderResponseFactory,
        Data $helper,
        Items $itemsHelper
    ) {
        parent::__construct($apiContext);
        $this->patchTokenFactory = $patchTokenFactory;
        $this->createRecurringOrderFactory = $createRecurringOrderFactory;
        $this->merchantSettingsFactory = $merchantSettingsFactory;
        $this->getTokenOrderResponseFactory = $getTokenOrderResponseFactory;
        $this->helper = $helper;
        $this->itemsHelper = $itemsHelper;
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
        $createRecurringOrder->setClientOrderNumber($quote->getReservedOrderId());
        $createRecurringOrder->setMerchantSettings($merchantSettings);
        $createRecurringOrder->setPartnerKey($this->helper->getPartnerKey());

        $this->createValidRecurringOrder($createRecurringOrder, $token);
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
     * Runs recursively until order with valid and unique ClientOrderNumber is created
     *
     * @param CreateRecurringOrder $createRecurringOrder
     * @param string $token
     * @return void
     * @throws ClientException
     */
    private function createValidRecurringOrder(CreateRecurringOrder $createRecurringOrder, string $token): void
    {
        try {
            $this->sendCreateRecurringOrderRequest($createRecurringOrder, $token);
        } catch (ClientException $e) {
            $message = strtolower($e->getMessage());

            // String comparison on the error message is the only way we can check for this specific error
            $isClientOrderNumberError = strpos($message, 'clientordernumber already exists') !== false;
            if (!$isClientOrderNumberError) {
                throw $e;
            }

            // If error is that ClientOrderNumber already exists, we try again with added sequence suffix
            $newClientOrderNumber = sprintf(
                '%s-%s',
                $createRecurringOrder->getClientOrderNumber(),
                ++$this->clientOrderNumberSequence
            );
            $createRecurringOrder->setClientOrderNumber($newClientOrderNumber);
            $this->createValidRecurringOrder($createRecurringOrder, $token);
        }
    }

    /**
     * @param CreateRecurringOrder $createRecurringOrder
     * @param string $token
     * @return void
     * @throws ClientException
     */
    private function sendCreateRecurringOrderRequest(CreateRecurringOrder $createRecurringOrder, string $token): void
    {
        $endpoint = sprintf('/api/tokens/%s/orders', $token);
        $this->post($endpoint, $createRecurringOrder);
    }
}

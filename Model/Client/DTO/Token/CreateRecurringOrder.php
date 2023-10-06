<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\Token;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;
use Svea\Checkout\Model\Client\DTO\Order\MerchantSettings;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;

class CreateRecurringOrder extends AbstractRequest
{
    /**
     * @var string
     */
    protected string $currency;

    /**
     * @var string
     */
    protected string $clientOrderNumber;

    /**
     * @var MerchantSettings
     */
    protected MerchantSettings $merchantSettings;

    /**
     * @var OrderRow[]
     */
    protected array $cartItems = [];

    /**
     * @var string|null
     */
    protected ?string $partnerKey = null;

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        $result = [
            'currency' => $this->getCurrency(),
            'clientOrderNumber' => $this->getClientOrderNumber(),
            'merchantSettings' => $this->getMerchantSettings()->toArray(),
        ];

        $items = $this->getCartItems();
        if (is_array($items)) {
            $cartItems = [];
            foreach ($items as $item) {
                /** @var OrderRow $item */
                $cartItems[] = $item->toArray();
            }

            $result['cart'] = ['items' => $cartItems];
        }

        if ($this->getPartnerKey()) {
            $result['partnerKey'] = $this->getPartnerKey();
        }

        return $result;
    }

    /**
     * Get the value of currency
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Set currency
     *
     * @param string $currency
     * @return void
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * Get the value of clientOrderNumber
     */
    public function getClientOrderNumber(): string
    {
        return $this->clientOrderNumber;
    }

    /**
     * Set the value of clientOrderNumber
     *
     * @param string $clientOrderNumber
     * @return void
     */
    public function setClientOrderNumber(string $clientOrderNumber): void
    {
        $this->clientOrderNumber = $clientOrderNumber;
    }

    /**
     * Get the value of merchantSettings
     */
    public function getMerchantSettings(): MerchantSettings
    {
        return $this->merchantSettings;
    }

    /**
     * Set the value of merchantSettings
     *
     * @param MerchantSettings $merchantSettings
     * @return void
     */
    public function setMerchantSettings(MerchantSettings $merchantSettings): void
    {
        $this->merchantSettings = $merchantSettings;
    }

    /**
     * Get the value of cartItems
     * @return OrderRow[]
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /**
     * Set the value of cartItems
     *
     * @param OrderRow[] $cartItems
     * @return void
     */
    public function setCartItems(array $cartItems): void
    {
        $this->cartItems = $cartItems;
    }

    /**
     * Get the value of partnerKey
     * @return string
     */
    public function getPartnerKey(): string
    {
        return $this->partnerKey;
    }

    /**
     * Set the value of partnerKey
     *
     * @param string $partnerKey
     * @return void
     */
    public function setPartnerKey(string $partnerKey): void
    {
        $this->partnerKey = $partnerKey;
    }
}

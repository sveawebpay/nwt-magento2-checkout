<?php
namespace Svea\Checkout\Model\Client\DTO;

use Svea\Checkout\Api\Data\HasOrderValidationInterface;
use Svea\Checkout\Model\Client\DTO\Order\OrderRow;
use Svea\Checkout\Model\Client\DTO\Order\OrderValidation;
use Svea\Checkout\Model\Client\DTO\Order\ShippingInformation;

class UpdateOrderCart extends AbstractRequest implements HasOrderValidationInterface
{

    /**
     * Required
     * @var $items OrderRow[]
     */
    protected $items;

    /** @var $merchantData string */
    protected $merchantData;

    /**
     * @var ShippingInformation
     */
    private $shippingInformation;

    private ?OrderValidation $validation = null;
    
    /**
     * @return string
     */
    public function getMerchantData()
    {
        return $this->merchantData;
    }

    /**
     * @param string $merchantData
     * @return UpdateOrderCart
     */
    public function setMerchantData($merchantData)
    {
        $this->merchantData = $merchantData;
        return $this;
    }
    

    /**
     * @return OrderRow[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param OrderRow[] $items
     * @return UpdateOrderCart
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }

    /**
     * @return OrderValidation|null
     */
    public function getValidation(): ?OrderValidation
    {
        return $this->validation;
    }

    /**
     * @param OrderValidation $validation
     * @return void
     */
    public function setValidation(OrderValidation $validation): void
    {
        $this->validation = $validation;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {
        $items = [];
        if (!empty($this->getItems())) {
            foreach ($this->getItems() as $item) {
                $items[] = $item->toArray();
            }
        }

        $array = [
            'cart' => ['Items' => $items],
            'merchantData' => $this->getMerchantData()
        ];

        if ($this->getShippingInformation()) {
            $array['ShippingInformation'] = $this->getShippingInformation()->toArray();
        }

        if (null !== $this->validation) {
            $array['validation'] = $this->validation->toArray();
        }

        return $array;
    }

    /**
     * Get the value of shippingInformation
     *
     * @return ShippingInformation
     */
    public function getShippingInformation()
    {
        return $this->shippingInformation;
    }

    /**
     * Set the value of shippingInformation
     *
     * @param ShippingInformation $shippingInformation
     *
     * @return self
     */
    public function setShippingInformation(ShippingInformation $shippingInformation)
    {
        $this->shippingInformation = $shippingInformation;

        return $this;
    }
}

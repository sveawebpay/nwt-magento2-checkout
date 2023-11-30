<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\Order;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;

/**
 * Represents order validation data type
 * @link https://checkoutapi.svea.com/docs/#/data-types?id=order-validation
 */
class OrderValidation extends AbstractRequest
{
    private int $minAge;

    /**
     * @return int
     */
    public function getMinAge(): int
    {
        return $this->minAge;
    }

    /**
     * @param int $minAge
     * @return OrderValidation
     */
    public function setMinAge(int $minAge): void
    {
        $this->minAge = $minAge;
    }

    public function toArray()
    {
        return [
            'minAge' => $this->getMinAge()
        ];
    }
}

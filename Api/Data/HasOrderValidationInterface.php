<?php declare(strict_types=1);

namespace Svea\Checkout\Api\Data;

use Svea\Checkout\Model\Client\DTO\Order\OrderValidation;

interface HasOrderValidationInterface
{
    /**
     * @param OrderValidation $validation
     * @return void
     */
    public function setValidation(OrderValidation $validation): void;
}

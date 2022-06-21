<?php

namespace Svea\Checkout\Model;

use Magento\Framework\DataObject;
use Svea\Checkout\Api\Data\QtyIncrementConfigInterface;

class QtyIncrementConfig extends DataObject implements QtyIncrementConfigInterface
{
    /**
     * @var bool
     */
    private $enableQtyIncrements;

    /**
     * @var float
     */
    private $qtyIncrements;

    /**
     * Get the value of enableQtyIncrements
     */
    public function isEnableQtyIncrements(): bool
    {
        return $this->enableQtyIncrements;
    }

    /**
     * Set the value of enableQtyIncrements
     *
     * @return void
     */
    public function setEnableQtyIncrements(bool $enableQtyIncrements): void
    {
        $this->enableQtyIncrements = $enableQtyIncrements;
    }

    /**
     * Get the value of qtyIncrements
     */
    public function getQtyIncrements(): float
    {
        return $this->qtyIncrements;
    }

    /**
     * Set the value of qtyIncrements
     *
     * @return void
     */
    public function setQtyIncrements(float $qtyIncrements): void
    {
        $this->qtyIncrements = $qtyIncrements;
    }
}

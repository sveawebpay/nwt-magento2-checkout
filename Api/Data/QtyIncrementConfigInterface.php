<?php

namespace Svea\Checkout\Api\Data;

/**
 * Data interface representing Qty Inrcement config for a single product's stock item
 */
interface QtyIncrementConfigInterface
{
    /**
     * Enabled status for Qty Increments, i.e. it's something else than exactly 1
     *
     * @return boolean
     */
    public function isEnableQtyIncrements(): bool;

    /**
     * Get Qty Increments value.
     * Note that a return value of 0 means that the qty increments is 1!
     *
     * @return boolean
     */
    public function getQtyIncrements(): float;

    /**
     * Set the value of enableQtyIncrements
     *
     * @return void
     */
    public function setEnableQtyIncrements(bool $enableQtyIncrements): void;

    /**
     * Set the value of qtyIncrements
     *
     * @param float $qtyIncrements
     * @return void
     */
    public function setQtyIncrements(float $qtyIncrements): void;
}

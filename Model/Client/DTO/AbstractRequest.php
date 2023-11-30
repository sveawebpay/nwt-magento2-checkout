<?php
namespace Svea\Checkout\Model\Client\DTO;

abstract class AbstractRequest
{
    // do stuff
    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    /**
     * Convert numeric amount to format accepted by Svea
     *
     * @param $amount
     * @return float
     */
    protected function addZeroes($amount)
    {
        return round($amount * 100, 0);
    }

    /**
     * @return array
     */
    abstract public function toArray();
}

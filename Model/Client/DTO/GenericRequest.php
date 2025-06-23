<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO;

/**
 * Generic implementation of AbstractRequest
 */
class GenericRequest extends AbstractRequest
{
    private array $data = [];

    /**
     * @param array $data
     * @return void
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}

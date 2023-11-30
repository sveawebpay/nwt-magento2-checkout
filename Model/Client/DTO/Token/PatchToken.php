<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\Token;

use Svea\Checkout\Model\Client\DTO\AbstractRequest;

class PatchToken extends AbstractRequest
{
    /**
     * @var string|null
     */
    protected ?string $status = null;

    public function setStatus(string $status)
    {
        $this->status = $status;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return [
            'status' => $this->getStatus(),
        ];
    }
}

<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\Token;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * @method self setOrderId
 * @method int getOrderId
 * @method self setStatus
 * @method string getStatus
 */
class GetTokenOrderResponse extends DataObject
{
    private Json $json;

    public function __construct(
        Json $json,
        array $data = []
    ) {
        parent::__construct($data);
        $this->json = $json;
    }

    /**
     * @param string $json
     * @return void
     */
    public function populateWithJson(string $json)
    {
        $data = $this->json->unserialize($json);
        $this->setOrderId($data['orderId']);
        $this->setStatus($data['status']);
    }
}

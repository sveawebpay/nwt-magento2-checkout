<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\Token;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Model representing response from token payment methods API call
 * @see https://docs.payments.svea.com/docs/checkout/recurring-orders/change-payment-method
 *
 * @method self setSnippet
 * @method string getSnippet
 * @method self setExpiration
 * @method string getExpiration
 */
class PaymentMethodsResponse extends DataObject
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
        $this->setSnippet($data['snippet']);
        $this->setExpiration($data['expiration']);
    }
}

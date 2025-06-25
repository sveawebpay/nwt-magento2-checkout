<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO;

use Magento\Framework\DataObject;
use Svea\Checkout\Model\Client\DTO\CreditWithFee\RowCreditingOption;

/**
 * @see https://docs.payments.svea.com/docs/manage-order/crediting_orders#credit-order-rows
 */
class CreditRows extends AbstractRequest
{
    /**
     * @var DataObject[]
     */
    private array $rowCreditingOptions = [];

    /**
     * Uses RowCreditingOptions to also set OrderRowIds
     * @inheritDoc
     */
    public function toArray()
    {
        $result = ['OrderRowIds' => []];
        foreach ($this->rowCreditingOptions as $rowCreditingOption) {
            $result['OrderRowIds'][] = $rowCreditingOption->getOrderRowId();
            $result['RowCreditingOptions'][] = [
                'OrderRowId' => $rowCreditingOption->getOrderRowId(),
                'Quantity' => $rowCreditingOption->getQuantity()
            ];
        }
        return $result;
    }

    /**
     * @param int $orderRowId
     * @param float $quantity
     * @return void
     */
    public function addRowCreditingOption(int $orderRowId, float $quantity): void
    {
        $this->rowCreditingOptions[] = (new DataObject())
            ->setData(['order_row_id' => $orderRowId, 'quantity' => $quantity])
        ;
    }
}

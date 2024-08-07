<?php
namespace Svea\Checkout\Model\Client\DTO;

class DeliverOrder extends AbstractRequest
{

    /**
     * Required
     * @var $OrderRowIds int[]
     */
    protected $OrderRowIds;

    /**
     * Optional
     * One of: 0=Default,2=Post,3=Email,4=EInvoiceB2B
     * @var $InvoiceDistributionType string
     */
    protected $InvoiceDistributionType;

    /**
     * Details of partial delivery of order rows. Optional field
     *  "RowDeliveryOptions":[
     *   {"OrderRowId":1,"Quantity":100},
     *   {"OrderRowId":2,"Quantity":100}
     * ]
     * @var array|null
     */
    protected $RowDeliveryOptions = null;

    /**
     * @return int[]
     */
    public function getOrderRowIds()
    {
        return $this->OrderRowIds;
    }

    /**
     * @param int[] $OrderRowIds
     * @return DeliverOrder
     */
    public function setOrderRowIds($OrderRowIds)
    {
        $this->OrderRowIds = $OrderRowIds;
        return $this;
    }

    /**
     * @return array|null
     */
    public function getRowDeliveryOptions(): ?array
    {
        return $this->RowDeliveryOptions;
    }

    /**
     * @param array|null $rowDeliveryOptions
     * @return void
     */
    public function setRowDeliveryOptions(?array $rowDeliveryOptions): void
    {
        $this->RowDeliveryOptions = $rowDeliveryOptions;
    }

    /**
     * @return string
     */
    public function getInvoiceDistributionType()
    {
        return $this->InvoiceDistributionType;
    }

    /**
     * @param string $InvoiceDistributionType
     * @return DeliverOrder
     */
    public function setInvoiceDistributionType($InvoiceDistributionType)
    {
        $this->InvoiceDistributionType = $InvoiceDistributionType;
        return $this;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }

    public function toArray()
    {
        $data = [];
        if ($this->getInvoiceDistributionType()) {
            $data['InvoiceDistributionType'] = $this->getInvoiceDistributionType();
        }

        $rows = $this->getOrderRowIds() ? $this->getOrderRowIds() : [];
        $data['OrderRowIds'] = $rows;

        if (null !== $this->getRowDeliveryOptions()) {
            $data['RowDeliveryOptions'] = $this->getRowDeliveryOptions();
        }

        return $data;
    }


}
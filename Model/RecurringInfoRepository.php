<?php declare(strict_types=1);

namespace Svea\Checkout\Model;

use Svea\Checkout\Api\Data\RecurringInfoInterface;
use Svea\Checkout\Api\RecurringInfoRepositoryInterface;
use Svea\Checkout\Model\RecurringInfoFactory as ModelFactory;
use Svea\Checkout\Model\RecurringInfo;
use Svea\Checkout\Model\ResourceModel\RecurringInfoFactory as ResourceFactory;
use Svea\Checkout\Model\ResourceModel\RecurringInfo\CollectionFactory;

class RecurringInfoRepository implements RecurringInfoRepositoryInterface
{
    private ModelFactory $modelFactory;

    private ResourceFactory $resourceFactory;

    private CollectionFactory $collectionFactory;

    /**
     * @var RecurringInfo[]
     */
    private array $instancesById = [];

    public function __construct(
        ModelFactory $modelFactory,
        ResourceFactory $resourceFactory,
        CollectionFactory $collectionFactory
    ) {
        $this->modelFactory = $modelFactory;
        $this->resourceFactory = $resourceFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function save(RecurringInfoInterface $recurringInfo)
    {
        $resourceModel = $this->resourceFactory->create();
        $resourceModel->save($recurringInfo);
    }

    /**
     * @inheritDoc
     */
    public function getByOriginalOrderId(int $orderId): RecurringInfoInterface
    {
        return $this->load('original_order_id', $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getByRecurringToken(string $recurringToken): RecurringInfoInterface
    {
        return $this->load('recurring_token', $recurringToken);
    }

    /**
     * Load an instance
     *
     * @param string $field
     * @param mixed $value
     * @return RecurringInfoInterface
     */
    private function load(string $field, mixed $value): RecurringInfoInterface
    {
        $model = $this->modelFactory->create();
        $resourceModel = $this->resourceFactory->create();
        $resourceModel->load($model, $value, $field);
        if ($model->getId()) {
            $this->instancesById[$model->getId()] = $model;
        }
        return $model;
    }

    /**
     * @inheritDoc
     */
    public function getByTodaysDate(): array
    {
        $todaysDate = date('Y-m-d');
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('next_order_date', ['from' => $todaysDate, 'to' => $todaysDate]);
        $collection->join(
            'sales_order',
            'main_table.original_order_id = sales_order.entity_id',
            ['store_id']
        );
        return $collection->getItems();
    }
}

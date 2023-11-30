<?php declare(strict_types=1);

namespace Svea\Checkout\Model\System\Config\Backend\Recurring;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Serialize\Serializer\Json;

class FrequencyOptions extends Value
{
    private Json $serializer;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        Json $serializer,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    public function beforeSave()
    {
        $value = $this->getValue();
        $value = $this->serializer->serialize($value);
        $this->setValue($value);
        return parent::beforeSave();
    }

    protected function _afterLoad()
    {
        $value = $this->getValue();
        if (!$value) {
            return;
        }
        $value = $this->serializer->unserialize($value);
        unset($value['__empty']);
        $this->setValue($value);
    }
}

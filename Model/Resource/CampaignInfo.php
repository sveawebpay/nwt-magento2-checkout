<?php

namespace Svea\Checkout\Model\Resource;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Svea\Checkout\Model\CampaignInfo as ModelCampaignInfo;

class CampaignInfo extends AbstractDb
{
    /**
     * Define main table
     */

    public function _construct()
    {
        $this->_init('svea_campaign_info', 'entity_id');
    }

    /**
     * @param ModelCampaignInfo $campaignObject
     * @param int $code
     * @param integer $storeId
     * @return void
     */
    public function loadByCampaignCodeAndStoreId(
        ModelCampaignInfo $campaignObject,
        int $code,
        int $storeId
    ): void {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'entity_id')
            ->where('campaign_code = ?', $code)
            ->where('store_id = ?', $storeId)
        ;

        $data = $connection->fetchRow($select);
        if (!$data) {
            return;
        }

        $this->load($campaignObject, $data['entity_id']);
    }
}

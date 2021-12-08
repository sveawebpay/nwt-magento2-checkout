<?php

namespace Svea\Checkout\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $definition = [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            '10,2',
            'default' => 0.00,
            'nullable' => true,
            'comment' =>'Svea Invoice Fee'
        ];

        $tables  = ['quote_address','quote_address','quote','sales_order','sales_invoice','sales_creditmemo'];
        foreach ($tables as $table) {
            $setup->getConnection()->addColumn($setup->getTable($table), "svea_invoice_fee", $definition);
        }

        if (version_compare($context->getVersion(), '1.1.0') < 0) {
            $this->installCampaignsInfo($setup);
        }

        if (version_compare($context->getVersion(), '1.1.1') < 0) {
            $this->alterInvoiceFeeColumns($setup);
        }

        $setup->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     *
     * @throws \Zend_Db_Exception
     */
    private function installCampaignsInfo(SchemaSetupInterface $installer)
    {
        $table = $installer->getConnection()->newTable(
            $installer->getTable('svea_campaign_info')
        )
        ->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            [
                'identity' => true,
                'nullable' => false,
                'primary'  => true,
                'unsigned' => true,
            ]
        )
        ->addColumn(
            'campaign_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'campaign_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'description',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'payment_plan_type',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'contract_length_in_months',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'monthly_annuity_factor',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            '10,5',
            ['nullable' => false, 'lenght' => '10,5']
        )
        ->addColumn(
            'initial_fee',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            '10,5',
            ['nullable' => false]
        )
        ->addColumn(
            'notification_fee',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            '10,5',
            ['nullable' => false]
        )
        ->addColumn(
            'interest_rate_percent',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            '10,5',
            ['nullable' => false, 'lenght' => '10,5']
        )
        ->addColumn(
            'number_of_interest_free_months',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'number_of_payment_free_months',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            255,
            ['nullable' => false]
        )
        ->addColumn(
            'from_amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            null,
            ['nullable' => false]
        )
        ->addColumn(
            'to_amount',
            \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
            null,
            ['nullable' => false]
        );

        $installer->getConnection()->createTable($table);
    }

    private function alterInvoiceFeeColumns(SchemaSetupInterface $setup)
    {
        $tables  = ['quote_address','quote_address','quote','sales_order','sales_invoice','sales_creditmemo'];

        foreach ($tables as $table) {
            $setup->getConnection()->modifyColumn(
                $table,
                'svea_invoice_fee',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    'length' => '20,4',
                    'default' => 0.00,
                    'nullable' => true,
                    'comment' =>'Svea Invoice Fee'
                ]
            );
        }
    }
}

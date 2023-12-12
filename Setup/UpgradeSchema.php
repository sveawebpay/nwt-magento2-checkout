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

        if (version_compare($context->getVersion(), '1.1.1') < 0) {
            $this->alterInvoiceFeeColumns($setup);
        }

        $setup->endSetup();
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

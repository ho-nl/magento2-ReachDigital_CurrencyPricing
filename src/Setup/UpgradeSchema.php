<?php

namespace ReachDigital\CurrencyPricing\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{

    /**
     * Upgrades data for a module
     *
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface   $context
     *
     * @return void
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context) :void
    {
        $setup->startSetup();
        $setup->getConnection()->addColumn('catalog_product_entity_tier_price', 'currency', [
            'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            'comment'   => 'Currency for the price',
            'length' => '3',
            'nullable' => true
        ]);
        $setup->endSetup();
    }
}

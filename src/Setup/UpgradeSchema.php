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
        if (version_compare($context->getVersion(), '0.1', '<')) {

            $setup->getConnection()->addColumn('catalog_product_entity_tier_price', 'currency', [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'comment'   => 'Currency for the price',
                'length' => '3',
                'nullable' => true
            ]);
        }
        if (version_compare($context->getVersion(), '0.2', '<')) {
            $uniqueKeyName = $setup->getIdxName(
                'catalog_product_entity_tier_price',
                ['entity_id', 'all_groups', 'customer_group_id', 'qty', 'website_id'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            );
            $setup->getConnection()->dropIndex('catalog_product_entity_tier_price', $uniqueKeyName);
            $setup->getConnection()->addIndex(
                'catalog_product_entity_tier_price',
                $setup->getIdxName(
                    'catalog_product_entity_tier_price',
                    ['entity_id', 'all_groups', 'customer_group_id', 'qty', 'website_id', 'currency'],
                    \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                ),
                ['entity_id', 'all_groups', 'customer_group_id', 'qty', 'website_id', 'currency'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
            );
        }
        if (version_compare($context->getVersion(), '0.3', '<')) {

            $setup->getConnection()->addColumn('catalog_product_index_tier_price', 'currency', [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'comment'   => 'Currency for the price',
                'length' => '3',
                'nullable' => true
            ]);
        }
        $setup->endSetup();
    }
}

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
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     * @throws \Zend_Db_Exception
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

            $setup->getConnection()->addIndex(
                'catalog_product_index_tier_price',
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY,
                ['entity_id','customer_group_id','website_id', 'currency'],
                \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_PRIMARY
            );

        }
        if (version_compare($context->getVersion(), '0.4', '<')) {

            $currencyPriceTable = $setup->getConnection()->newTable($setup->getTable('catalog_product_entity_currency_price'))
                ->addColumn('currency_price_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true])
                ->addColumn('currency',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    3,
                    ['nullable' => false])
                ->addColumn('type',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    10,
                    ['nullable' => false])
                ->addColumn('price',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    3)
                ->addColumn(
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    ['unsigned' => true, 'nullable' => false],
                    'Entity ID'
                )
                ->addForeignKey(
                    $setup->getFkName(
                        'catalog_product_entity_currency_price',
                        'entity_id',
                        'catalog_product_entity',
                        'entity_id'
                    ),
                    'entity_id',
                    $setup->getTable('catalog_product_entity'),
                    'entity_id',
                    \Magento\Framework\DB\Ddl\Table::ACTION_CASCADE
                );

            $setup->getConnection()->createTable($currencyPriceTable);
        }
        $setup->endSetup();
    }
}

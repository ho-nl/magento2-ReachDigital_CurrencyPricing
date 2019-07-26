<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price\Query;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\ColumnValueExpression;
use Magento\Framework\Indexer\Dimension;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\JoinAttributeProcessor;


/**
 * Variant of Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice but considers currency for indexing.
 * This class is used by ReachDigital\CurrencyPricing\Model\ResourceModel\Indexer\PriceIndexerWithCurrency.
 */
class BaseFinalPriceWithCurrency
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resource;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * @var JoinAttributeProcessor
     */
    private $joinAttributeProcessor;

    /**
     * @var \Magento\Framework\Module\Manager
     */
    private $moduleManager;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $eventManager;

    /**
     * Mapping between dimensions and field in database
     *
     * @var array
     */
    private $dimensionToFieldMapper = [
        WebsiteDimensionProvider::DIMENSION_NAME => 'pw.website_id',
        CustomerGroupDimensionProvider::DIMENSION_NAME => 'cg.customer_group_id',
    ];

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    private $metadataPool;

    /**
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param JoinAttributeProcessor $joinAttributeProcessor
     * @param \Magento\Framework\Module\Manager $moduleManager
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Framework\EntityManager\MetadataPool $metadataPool
     * @param string $connectionName
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        JoinAttributeProcessor $joinAttributeProcessor,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Framework\EntityManager\MetadataPool $metadataPool,
        $connectionName = 'indexer'
    ) {
        $this->resource = $resource;
        $this->connectionName = $connectionName;
        $this->joinAttributeProcessor = $joinAttributeProcessor;
        $this->moduleManager = $moduleManager;
        $this->eventManager = $eventManager;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Build query for base final price.
     *
     * @param Dimension[] $dimensions
     * @param string $productType
     * @param array $entityIds
     * @return Select
     * @throws \LogicException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getQuery(array $dimensions, string $productType, string $currency, bool $isBaseCurrency, array $entityIds = []): Select
    {
        $connection = $this->getConnection();
        $metadata = $this->metadataPool->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class);
        $linkField = $metadata->getLinkField();

        $select = $connection->select()->from(
            ['e' => $this->getTable('catalog_product_entity')],
            ['entity_id']
        )->joinInner(
            ['cg' => $this->getTable('customer_group')],
            array_key_exists(CustomerGroupDimensionProvider::DIMENSION_NAME, $dimensions)
                ? sprintf(
                '%s = %s',
                $this->dimensionToFieldMapper[CustomerGroupDimensionProvider::DIMENSION_NAME],
                $dimensions[CustomerGroupDimensionProvider::DIMENSION_NAME]->getValue()
            ) : '',
            ['customer_group_id']
        )->joinInner(
            ['pw' => $this->getTable('catalog_product_website')],
            'pw.product_id = e.entity_id',
            ['pw.website_id']
        )->joinInner(
            ['cwd' => $this->getTable('catalog_product_index_website')],
            'pw.website_id = cwd.website_id',
            []
        )->joinLeft(
        // Get the currency price.
            ['cp' => $this->getTable('catalog_product_entity_currency_price')],
            'cp.entity_id = e.entity_id AND' .
            ' cp.type = "price" AND cp.currency = "' . $currency . '"',
            []
        )->joinLeft(
            // we need this only for BCC in case someone expects table `tp` to be present in query
            ['tp' => $this->getTable('catalog_product_index_tier_price')],
            'tp.entity_id = e.entity_id AND' .
            ' tp.customer_group_id = cg.customer_group_id AND tp.website_id = pw.website_id',
            []
        )->joinLeft(
        // calculate tier price specified as Website = `All Websites` and Customer Group = `Specific Customer Group`
            ['tier_price_1' => $this->getTable('catalog_product_entity_tier_price')],
            $this->getTierPriceCondition($linkField, '1', '0', '0', $currency, $isBaseCurrency),
            []
        )->joinLeft(
        // calculate tier price specified as Website = `Specific Website`
        //and Customer Group = `Specific Customer Group`
            ['tier_price_2' => $this->getTable('catalog_product_entity_tier_price')],
            $this->getTierPriceCondition($linkField, '2', '0', 'pw.website_id', $currency, $isBaseCurrency),
            []
        )->joinLeft(
        // calculate tier price specified as Website = `All Websites` and Customer Group = `ALL GROUPS`
            ['tier_price_3' => $this->getTable('catalog_product_entity_tier_price')],
            $this->getTierPriceCondition($linkField, '3', '1', '0', $currency, $isBaseCurrency),
            []
        )->joinLeft(
        // calculate tier price specified as Website = `Specific Website` and Customer Group = `ALL GROUPS`
            ['tier_price_4' => $this->getTable('catalog_product_entity_tier_price')],
            $this->getTierPriceCondition($linkField, '4', '1', 'pw.website_id', $currency, $isBaseCurrency),
            []
        );

        foreach ($dimensions as $dimension) {
            if (!isset($this->dimensionToFieldMapper[$dimension->getName()])) {
                throw new \LogicException(
                    'Provided dimension is not valid for Price indexer: ' . $dimension->getName()
                );
            }
            $select->where($this->dimensionToFieldMapper[$dimension->getName()] . ' = ?', $dimension->getValue());
        }

        if ($this->moduleManager->isEnabled('Magento_Tax')) {
            $taxClassId = $this->joinAttributeProcessor->process($select, 'tax_class_id');
        } else {
            $taxClassId = new \Zend_Db_Expr(0);
        }
        $select->columns(['tax_class_id' => $taxClassId]);

        $this->joinAttributeProcessor->process($select, 'status', Status::STATUS_ENABLED);

        $price = $this->joinAttributeProcessor->process($select, 'price');
        $specialPrice = $this->joinAttributeProcessor->process($select, 'special_price');
        $specialFrom = $this->joinAttributeProcessor->process($select, 'special_from_date');
        $specialTo = $this->joinAttributeProcessor->process($select, 'special_to_date');
        $currentDate = 'cwd.website_date';

        $maxUnsignedBigint = '~0';
        $specialFromDate = $connection->getDatePartSql($specialFrom);
        $specialToDate = $connection->getDatePartSql($specialTo);
        $specialFromExpr = "{$specialFrom} IS NULL OR {$specialFromDate} <= {$currentDate}";
        $specialToExpr = "{$specialTo} IS NULL OR {$specialToDate} >= {$currentDate}";
        $specialPriceExpr = $connection->getCheckSql(
            "{$specialPrice} IS NOT NULL AND ({$specialFromExpr}) AND ({$specialToExpr})",
            $specialPrice,
            $maxUnsignedBigint
        );
        $tierPrice = $this->getTotalTierPriceExpression($price);
        $tierPriceExpr = $connection->getIfNullSql($tierPrice, $maxUnsignedBigint);
        $finalPrice = $connection->getLeastSql([
            $price,
            $specialPriceExpr,
            $tierPriceExpr,
        ]);

        $price = 'IF(cp.price IS NULL OR cp.price = 0, ' . $price . ', cp.price)';


        $select->columns(
            [
                //orig_price in catalog_product_index_price_final_tmp
                'price' => $connection->getIfNullSql($price, 0),
                //price in catalog_product_index_price_final_tmp
                'final_price' => $connection->getIfNullSql($finalPrice, 0),
                'min_price' => $connection->getIfNullSql($finalPrice, 0),
                'max_price' => $connection->getIfNullSql($finalPrice, 0),
                'tier_price' => $tierPrice,
                'currency' => new \Zend_Db_Expr('"' . $currency . '"'),
            ]
        );

        $select->where("e.type_id = ?", $productType);

        if ($entityIds !== null) {
            if (count($entityIds) > 1) {
                $select->where(sprintf('e.entity_id BETWEEN %s AND %s', min($entityIds), max($entityIds)));
            } else {
                $select->where('e.entity_id = ?', $entityIds);
            }
        }

        /**
         * throw event for backward compatibility
         */
        $this->eventManager->dispatch(
            'prepare_catalog_product_index_select',
            [
                'select' => $select,
                'entity_field' => new ColumnValueExpression('e.entity_id'),
                'website_field' => new ColumnValueExpression('pw.website_id'),
                'store_field' => new ColumnValueExpression('cwd.default_store_id'),
            ]
        );

        return $select;
    }

    /**
     * @param $linkField
     * @param $tierPriceExpressionNumber
     * @param $allGroups
     * @param $website
     * @param $currency
     * @param $isBaseCurrency
     *
     * @return string
     */
    private function getTierPriceCondition($linkField, $tierPriceExpressionNumber, $allGroups, $website, $currency, $isBaseCurrency): string
    {
        $tierPriceCondition = 'tier_price_' . $tierPriceExpressionNumber. '.' . $linkField . ' = e.' . $linkField . ' AND tier_price_' . $tierPriceExpressionNumber. '.all_groups = '. $allGroups .
            ' AND tier_price_' . $tierPriceExpressionNumber. '.customer_group_id = cg.customer_group_id AND tier_price_' . $tierPriceExpressionNumber. '.qty = 1' .
            ' AND tier_price_' . $tierPriceExpressionNumber. '.website_id = ' . $website;
        if ($isBaseCurrency) {
            $tierPriceCondition .= ' AND (tier_price_' . $tierPriceExpressionNumber. '.currency = "' . $currency . '" || tier_price_' . $tierPriceExpressionNumber. '.currency IS NULL)';
        } else {
            $tierPriceCondition .= ' AND tier_price_' . $tierPriceExpressionNumber. '.currency = "' . $currency . '"';
        }

        return $tierPriceCondition;
    }

    /**
     * Get total tier price expression
     *
     * @param \Zend_Db_Expr $priceExpression
     * @return \Zend_Db_Expr
     */
    private function getTotalTierPriceExpression(\Zend_Db_Expr $priceExpression)
    {
        $maxUnsignedBigint = '~0';

        return $this->getConnection()->getCheckSql(
            implode(
                ' AND ',
                [
                    'tier_price_1.value_id is NULL',
                    'tier_price_2.value_id is NULL',
                    'tier_price_3.value_id is NULL',
                    'tier_price_4.value_id is NULL'
                ]
            ),
            'NULL',
            $this->getConnection()->getLeastSql([
                $this->getConnection()->getIfNullSql(
                    $this->getTierPriceExpressionForTable('tier_price_1', $priceExpression),
                    $maxUnsignedBigint
                ),
                $this->getConnection()->getIfNullSql(
                    $this->getTierPriceExpressionForTable('tier_price_2', $priceExpression),
                    $maxUnsignedBigint
                ),
                $this->getConnection()->getIfNullSql(
                    $this->getTierPriceExpressionForTable('tier_price_3', $priceExpression),
                    $maxUnsignedBigint
                ),
                $this->getConnection()->getIfNullSql(
                    $this->getTierPriceExpressionForTable('tier_price_4', $priceExpression),
                    $maxUnsignedBigint
                ),
            ])
        );
    }

    /**
     * Get tier price expression for table
     *
     * @param string $tableAlias
     * @param \Zend_Db_Expr $priceExpression
     * @return \Zend_Db_Expr
     */
    private function getTierPriceExpressionForTable($tableAlias, \Zend_Db_Expr $priceExpression): \Zend_Db_Expr
    {
        return $this->getConnection()->getCheckSql(
            sprintf('%s.value = 0', $tableAlias),
            sprintf(
                'ROUND(%s * (1 - ROUND(%s.percentage_value * cwd.rate, 4) / 100), 4)',
                $priceExpression,
                $tableAlias
            ),
            sprintf('ROUND(%s.value * cwd.rate, 4)', $tableAlias)
        );
    }

    /**
     * Get connection
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     * @throws \DomainException
     */
    private function getConnection(): \Magento\Framework\DB\Adapter\AdapterInterface
    {
        if ($this->connection === null) {
            $this->connection = $this->resource->getConnection($this->connectionName);
        }

        return $this->connection;
    }

    /**
     * Get table
     *
     * @param string $tableName
     * @return string
     */
    private function getTable($tableName)
    {
        return $this->resource->getTableName($tableName, $this->connectionName);
    }
}

<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);
namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Indexer;

use ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPriceWithCurrency;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier;
use Magento\Directory\Model\Currency;
use Magento\Downloadable\Model\Product\Type;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice;
use Magento\Downloadable\Model\ResourceModel\Indexer\Price;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;

class PriceIndexerWithCurrency extends Price
{
    /**
     * @var IndexTableStructureFactory
     */
    private $indexTableStructureFactory;

    /**
     * @var TableMaintainer
     */
    private $tableMaintainer;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var string
     */
    private $connectionName;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connection;

    /**
     * @var BasePriceModifier
     */
    private $basePriceModifier;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var Currency
     */
    private $currencyModel;

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    /**
     * @var BaseFinalPriceWithCurrency
     */
    private $baseFinalPriceWithCurrency;

    /**
     * PriceIndexerWithCurrency constructor.
     *
     * @param BaseFinalPrice                    $baseFinalPrice
     * @param IndexTableStructureFactory        $indexTableStructureFactory
     * @param TableMaintainer                   $tableMaintainer
     * @param MetadataPool                      $metadataPool
     * @param Config                            $eavConfig
     * @param ResourceConnection                $resource
     * @param BasePriceModifier                 $basePriceModifier
     * @param string                            $connectionName
     * @param Currency $currencyModel
     */
    public function __construct(
        BaseFinalPrice $baseFinalPrice,
        IndexTableStructureFactory $indexTableStructureFactory,
        TableMaintainer $tableMaintainer,
        MetadataPool $metadataPool,
        Config $eavConfig,
        ResourceConnection $resource,
        BasePriceModifier $basePriceModifier,
        Currency $currencyModel,
        RealBaseCurrency $realBaseCurrency,
        BaseFinalPriceWithCurrency $baseFinalPriceWithCurrency,
        string $connectionName = 'indexer'
    ) {
        parent::__construct(
            $baseFinalPrice,
            $indexTableStructureFactory,
            $tableMaintainer,
            $metadataPool,
            $eavConfig,
            $resource,
            $basePriceModifier,
            $connectionName
        );
        $this->indexTableStructureFactory = $indexTableStructureFactory;
        $this->tableMaintainer = $tableMaintainer;
        $this->resource = $resource;
        $this->connectionName = $connectionName;
        $this->basePriceModifier = $basePriceModifier;
        $this->metadataPool = $metadataPool;
        $this->currencyModel = $currencyModel;
        $this->realBaseCurrency = $realBaseCurrency;
        $this->baseFinalPriceWithCurrency = $baseFinalPriceWithCurrency;
    }

    /**
     * {@inheritdoc}
     * @param array $dimensions
     * @param \Traversable $entityIds
     * @throws \Exception
     */
    public function executeByDimensions(array $dimensions, \Traversable $entityIds)
    {
        $temporaryPriceTable = $this->indexTableStructureFactory->create([
            'tableName' => $this->tableMaintainer->getMainTmpTable($dimensions),
            'entityField' => 'entity_id',
            'customerGroupField' => 'customer_group_id',
            'websiteField' => 'website_id',
            'taxClassField' => 'tax_class_id',
            'originalPriceField' => 'price',
            'finalPriceField' => 'final_price',
            'minPriceField' => 'min_price',
            'maxPriceField' => 'max_price',
            'tierPriceField' => 'tier_price',
            'currencyField' => 'currency',
            'storeviewIdField' => 'storeview_id',
        ]);
        $this->fillFinalPrice($dimensions, $entityIds, $temporaryPriceTable);
        $this->basePriceModifier->modifyPrice($temporaryPriceTable, iterator_to_array($entityIds));
        $this->applyDownloadableLink($temporaryPriceTable, $dimensions);
    }

    /**
     * Fill final price
     *
     * @param array $dimensions
     * @param \Traversable $entityIds
     * @param IndexTableStructure $temporaryPriceTable
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Zend_Db_Select_Exception
     */
    private function fillFinalPrice(
        array $dimensions,
        \Traversable $entityIds,
        IndexTableStructure $temporaryPriceTable
    ) {
        $currencies = $this->currencyModel->getConfigAllowCurrencies();
        $baseCurrency = $this->realBaseCurrency->getRealBaseCurrencyCode();

        foreach ($currencies as $currency) {
            $this->updatePriceIndexerForCurrency(
                $dimensions,
                $entityIds,
                $temporaryPriceTable,
                $currency,
                $currency === $baseCurrency
            );
        }
    }

    private function updatePriceIndexerForCurrency(
        array $dimensions,
        \Traversable $entityIds,
        IndexTableStructure $temporaryPriceTable,
        string $currency,
        bool $isBaseCurrency
    ): void {
        $select = $this->baseFinalPriceWithCurrency->getQuery(
            $dimensions,
            Type::TYPE_DOWNLOADABLE,
            $currency,
            $isBaseCurrency,
            iterator_to_array($entityIds)
        );
        $query = $select->insertFromSelect($temporaryPriceTable->getTableName(), [], false);
        $this->tableMaintainer->getConnection()->query($query);
    }

    /*
     * ---------------------------------------------
     * The following functions have been copied without changes from Magento\Downloadable\Model\ResourceModel\Indexer\Price
     * They are necesary since they are called in executeByDimensions() but they are private in our parent class.
     */

    /**
     * Calculate and apply Downloadable links price to index
     *
     * @param IndexTableStructure $temporaryPriceTable
     * @param array $dimensions
     * @return $this
     * @throws \Exception
     */
    private function applyDownloadableLink(IndexTableStructure $temporaryPriceTable, array $dimensions)
    {
        $temporaryDownloadableTableName = 'catalog_product_index_price_downlod_temp';
        $this->getConnection()->createTemporaryTableLike(
            $temporaryDownloadableTableName,
            $this->getTable('catalog_product_index_price_downlod_tmp'),
            true
        );
        $this->fillTemporaryTable($temporaryDownloadableTableName, $dimensions);
        $this->updateTemporaryDownloadableTable($temporaryPriceTable->getTableName(), $temporaryDownloadableTableName);
        $this->getConnection()->delete($temporaryDownloadableTableName);
        return $this;
    }

    /**
     * Put data into catalog product price indexer Downloadable links price  temp table
     *
     * @param string $temporaryDownloadableTableName
     * @param array $dimensions
     * @return void
     * @throws \Exception
     */
    private function fillTemporaryTable(string $temporaryDownloadableTableName, array $dimensions)
    {
        $dlType = $this->getAttribute('links_purchased_separately');
        $ifPrice = $this->getConnection()->getIfNullSql('dlpw.price_id', 'dlpd.price');
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();

        $select = $this->getConnection()
            ->select()
            ->from(
                ['i' => $this->tableMaintainer->getMainTmpTable($dimensions)],
                ['entity_id', 'customer_group_id', 'website_id']
            )
            ->join(
                ['dl' => $dlType->getBackend()->getTable()],
                "dl.{$linkField} = i.entity_id AND dl.attribute_id = {$dlType->getAttributeId()}" .
                    ' AND dl.store_id = 0',
                []
            )
            ->join(['dll' => $this->getTable('downloadable_link')], 'dll.product_id = i.entity_id', [])
            ->join(
                ['dlpd' => $this->getTable('downloadable_link_price')],
                'dll.link_id = dlpd.link_id AND dlpd.website_id = 0',
                []
            )
            ->joinLeft(
                ['dlpw' => $this->getTable('downloadable_link_price')],
                'dlpd.link_id = dlpw.link_id AND dlpw.website_id = i.website_id',
                []
            )
            ->where('dl.value = ?', 1)
            ->group(['i.entity_id', 'i.customer_group_id', 'i.website_id'])
            ->columns([
                'min_price' => new \Zend_Db_Expr('MIN(' . $ifPrice . ')'),
                'max_price' => new \Zend_Db_Expr('SUM(' . $ifPrice . ')'),
            ]);
        $query = $select->insertFromSelect($temporaryDownloadableTableName);
        $this->getConnection()->query($query);
    }

    /**
     * Update data in the catalog product price indexer temp table
     *
     * @param string $temporaryPriceTableName
     * @param string $temporaryDownloadableTableName
     * @return void
     */
    private function updateTemporaryDownloadableTable(
        string $temporaryPriceTableName,
        string $temporaryDownloadableTableName
    ) {
        $ifTierPrice = $this->getConnection()->getCheckSql(
            'i.tier_price IS NOT NULL',
            '(i.tier_price + id.min_price)',
            'NULL'
        );

        $selectForCrossUpdate = $this->getConnection()
            ->select()
            ->join(
                ['id' => $temporaryDownloadableTableName],
                'i.entity_id = id.entity_id AND i.customer_group_id = id.customer_group_id' .
                    ' AND i.website_id = id.website_id',
                []
            );
        // adds price of custom option, that was applied in DefaultPrice::_applyCustomOption
        $selectForCrossUpdate->columns([
            'min_price' => new \Zend_Db_Expr('i.min_price + id.min_price'),
            'max_price' => new \Zend_Db_Expr('i.max_price + id.max_price'),
            'tier_price' => new \Zend_Db_Expr($ifTierPrice),
        ]);
        $query = $selectForCrossUpdate->crossUpdateFromSelect(['i' => $temporaryPriceTableName]);
        $this->getConnection()->query($query);
    }

    /**
     * Get connection
     *
     * return \Magento\Framework\DB\Adapter\AdapterInterface
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

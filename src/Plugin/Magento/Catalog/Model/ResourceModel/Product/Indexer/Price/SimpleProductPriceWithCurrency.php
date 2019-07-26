<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Plugin\Magento\Catalog\Model\ResourceModel\Product\Indexer\Price;


use Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\SimpleProductPrice;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;
use ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPriceWithCurrency;
use Magento\Directory\Model\Currency;
use Magento\Downloadable\Model\Product\Type;

class SimpleProductPriceWithCurrency
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
     * @var BasePriceModifier
     */
    private $basePriceModifier;

    /**
     * @var string
     */
    private $productType;

    /**
     * @var BaseFinalPriceWithCurrency
     */
    private $baseFinalPriceWithCurrency;

    /**
     * @var Currency
     */
    private $currencyModel;

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    public function __construct(
        BaseFinalPriceWithCurrency $baseFinalPriceWithCurrency,
        IndexTableStructureFactory $indexTableStructureFactory,
        TableMaintainer $tableMaintainer,
        BasePriceModifier $basePriceModifier,
        Currency $currencyModel,
        RealBaseCurrency $realBaseCurrency,
        $productType = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    {
        $this->indexTableStructureFactory = $indexTableStructureFactory;
        $this->tableMaintainer = $tableMaintainer;
        $this->basePriceModifier = $basePriceModifier;
        $this->productType = $productType;
        $this->baseFinalPriceWithCurrency = $baseFinalPriceWithCurrency;
        $this->currencyModel = $currencyModel;
        $this->realBaseCurrency = $realBaseCurrency;
    }

    /**
     * @param SimpleProductPrice $simpleProductPrice
     * @param \Closure           $proceed
     * @param array              $dimensions
     * @param \Traversable       $entityIds
     */
    public function aroundExecuteByDimensions(SimpleProductPrice $simpleProductPrice, \Closure $proceed, array $dimensions, \Traversable $entityIds): void
    {
        $this->tableMaintainer->createMainTmpTable($dimensions);

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
        ]);
        $currencies = $this->currencyModel->getConfigAllowCurrencies();
        $baseCurrency = $this->realBaseCurrency->getRealBaseCurrencyCode();

        foreach ($currencies as $currency) {
            $this->updatePriceIndexerForCurrency($dimensions, $entityIds, $temporaryPriceTable, $currency, $currency === $baseCurrency);
        }

        $this->basePriceModifier->modifyPrice($temporaryPriceTable, iterator_to_array($entityIds));
    }

    private function updatePriceIndexerForCurrency(array $dimensions, \Traversable $entityIds, IndexTableStructure $temporaryPriceTable, string $currency, bool $isBaseCurrency): void
    {
        $select = $this->baseFinalPriceWithCurrency->getQuery($dimensions, $this->productType, $currency, $isBaseCurrency, iterator_to_array($entityIds));
        $query = $select->insertFromSelect($temporaryPriceTable->getTableName(), [], false);
        $this->tableMaintainer->getConnection()->query($query);
    }
}

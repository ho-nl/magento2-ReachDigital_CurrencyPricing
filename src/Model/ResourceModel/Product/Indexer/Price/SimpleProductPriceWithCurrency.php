<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price;

use Magento\Catalog\Model\Indexer\Product\Price\TableMaintainer;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\BasePriceModifier;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructureFactory;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPrice;
use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\SimpleProductPrice;
use Magento\Store\Model\StoreManagerInterface;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;
use ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price\Query\BaseFinalPriceWithCurrency;
use Magento\Directory\Model\Currency;

class SimpleProductPriceWithCurrency extends SimpleProductPrice
{
    private $productType;

    /**
     * @var TableMaintainer
     */
    private $tableMaintainer;

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

    /**
     * @var BasePriceModifier
     */
    private $basePriceModifier;

    /**
     * @var IndexTableStructureFactory
     */
    private $indexTableStructureFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        BaseFinalPrice $baseFinalPrice,
        IndexTableStructureFactory $indexTableStructureFactory,
        TableMaintainer $tableMaintainer,
        BasePriceModifier $basePriceModifier,
        BaseFinalPriceWithCurrency $baseFinalPriceWithCurrency,
        Currency $currencyModel,
        RealBaseCurrency $realBaseCurrency,
        StoreManagerInterface $storeManager,
        string $productType = \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
    ) {
        parent::__construct(
            $baseFinalPrice,
            $indexTableStructureFactory,
            $tableMaintainer,
            $basePriceModifier,
            $productType
        );
        $this->productType = $productType;
        $this->tableMaintainer = $tableMaintainer;
        $this->baseFinalPriceWithCurrency = $baseFinalPriceWithCurrency;
        $this->currencyModel = $currencyModel;
        $this->realBaseCurrency = $realBaseCurrency;
        $this->basePriceModifier = $basePriceModifier;
        $this->indexTableStructureFactory = $indexTableStructureFactory;
        $this->storeManager = $storeManager;
    }

    /**
     * @param array        $dimensions
     * @param \Traversable $entityIds
     */
    public function executeByDimensions(array $dimensions, \Traversable $entityIds): void
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
            'storeviewIdField' => 'storeview_id',
        ]);

        foreach ($this->storeManager->getStores() as $store) {
            $currencies = $this->currencyModel->getConfigAllowCurrencies();
            $baseCurrency = $this->realBaseCurrency->getRealBaseCurrencyCode();
            foreach ($currencies as $currency) {
                $this->updatePriceIndexerForCurrency(
                    $dimensions,
                    $entityIds,
                    $temporaryPriceTable,
                    $currency,
                    $currency === $baseCurrency,
                    $store->getWebsiteId(),
                    $store->getId()
                );
            }
            $this->basePriceModifier->modifyPrice($temporaryPriceTable, iterator_to_array($entityIds));
        }
    }

    private function updatePriceIndexerForCurrency(
        array $dimensions,
        \Traversable $entityIds,
        IndexTableStructure $temporaryPriceTable,
        string $currency,
        bool $isBaseCurrency,
        $websiteId,
        $storeviewId
    ): void {
        $select = $this->baseFinalPriceWithCurrency->getQuery(
            $dimensions,
            $this->productType,
            $currency,
            $isBaseCurrency,
            $websiteId,
            $storeviewId,
            iterator_to_array($entityIds)
        );
        $query = $select->insertFromSelect($temporaryPriceTable->getTableName(), [], false);
        $this->tableMaintainer->getConnection()->query($query);
    }
}

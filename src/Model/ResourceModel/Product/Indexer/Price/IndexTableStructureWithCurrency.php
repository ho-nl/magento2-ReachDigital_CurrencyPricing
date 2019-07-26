<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Indexer\Price;

use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\IndexTableStructure;

class IndexTableStructureWithCurrency extends IndexTableStructure
{

    /**
     * @var string
     */
    private $currencyField;

    public function __construct(
        string $tableName,
        string $entityField,
        string $customerGroupField,
        string $websiteField,
        string $taxClassField,
        string $originalPriceField,
        string $finalPriceField,
        string $minPriceField,
        string $maxPriceField,
        string $tierPriceField,
        string $currencyField
    ) {
        parent::__construct($tableName, $entityField, $customerGroupField, $websiteField, $taxClassField,
            $originalPriceField, $finalPriceField, $minPriceField, $maxPriceField, $tierPriceField);
        $this->currencyField = $currencyField;
    }

    /**
     * @return string
     */
    public function getCurrencyField(): string
    {
        return $this->currencyField;
    }
}

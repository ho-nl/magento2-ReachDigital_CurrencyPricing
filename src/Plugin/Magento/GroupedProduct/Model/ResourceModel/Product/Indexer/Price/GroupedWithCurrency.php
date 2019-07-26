<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Plugin\Magento\GroupedProduct\Model\ResourceModel\Product\Indexer\Price;

use Magento\GroupedProduct\Model\ResourceModel\Product\Indexer\Price\Grouped;

/**
 * Class GroupedWithCurrency
 * Grouped product price indexer now gets skipped.
 * TODO: Make sure currency pricing module also supports Grouped products.
 *
 * @package ReachDigital\CurrencyPricing\Plugin\Magento\GroupedProduct\Model\ResourceModel\Product\Indexer\Price
 */
class GroupedWithCurrency
{
    public function aroundExecuteByDimensions(Grouped $grouped, \Closure $proceed, array $dimensions, \Traversable $entityIds): void
    {
    }
}

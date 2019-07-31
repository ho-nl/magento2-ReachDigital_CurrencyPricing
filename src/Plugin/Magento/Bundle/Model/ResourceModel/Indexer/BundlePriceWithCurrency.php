<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Plugin\Magento\Bundle\Model\ResourceModel\Indexer;

use Magento\Bundle\Model\ResourceModel\Indexer\Price;

/**
 * Class BundlePriceWithCurrency
 * Bundle product price indexer now gets skipped.
 * TODO: Make sure currency pricing module also supports Bundle products.
 *
 * @package ReachDigital\CurrencyPricing\Plugin\Magento\Bundle\Model\ResourceModel\Indexer
 */
class BundlePriceWithCurrency
{
    public function aroundExecuteByDimensions(Price $bundlePrice, \Closure $proceed, array $dimensions, \Traversable $entityIds): void
    {
    }
}

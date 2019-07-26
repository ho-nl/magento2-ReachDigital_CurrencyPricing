<?php
namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType;

use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice as TierPriceResourceModel;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\DB\Select;

class TierPriceWithCurrency
{
    /**
     * @param TierPrice $subject
     * @param \Closure  $proceed
     * @param array     $entityIds
     */
    public function aroundReindexEntity(TierPrice $subject, \Closure $proceed, array $entityIds = []): void {
        // The tier price indexer table is no longer used, so we do not update it.
        // The tier price information is still provided in the regular price index table (catalog_product_index_price).
    }

}

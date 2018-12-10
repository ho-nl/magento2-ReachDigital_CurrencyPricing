<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;

class SaveCurrency
{

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * SaveCurrency constructor.
     *
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {

        $this->resourceConnection = $resourceConnection;
    }

    public function afterSave(Product $subject, $result) {
        foreach ($subject->getData('tier_price') as $tierPrice) {
            $tableName = $this->resourceConnection->getTableName('catalog_product_entity_tier_price');
            if (array_key_exists('currency', $tierPrice)) {
                $currency = $tierPrice['currency'];
            } else {
                $currency = null;
            }
            $this->resourceConnection->getConnection()->update($tableName,
                ['currency' => $currency],
                ['value_id = ?' => $tierPrice['price_id']]);
        }
        return $result;
    }

    /**
     * @param Product $subject
     * @param                                $result
     *
     * @return mixed
     * @throws \Zend_Db_Statement_Exception
     */
    public function afterLoad(Product $subject, Product $result) {
        $tierPriceArray = $result->getData('tier_price');
        $updatedTierPriceArray = [];
        foreach ($tierPriceArray as $tierPrice) {
            $currency = $this->resourceConnection->getConnection()->query('SELECT currency from catalog_product_entity_tier_price where value_id = ?', $tierPrice['price_id'])->fetch();

            $tierPrice['currency'] = $currency['currency'];
            $updatedTierPriceArray []= $tierPrice;
        }
        $result->setData('tier_price', $updatedTierPriceArray);
        return $result;
    }
}
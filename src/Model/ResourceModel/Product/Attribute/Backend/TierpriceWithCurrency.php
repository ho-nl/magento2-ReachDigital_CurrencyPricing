<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Product\Attribute\Backend;


use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice;

class TierpriceWithCurrency extends Tierprice
{
    /**
     * Add qty column
     *
     * @param array $columns
     * @return array
     */
    protected function _loadPriceDataColumns($columns)
    {
        $columns = parent::_loadPriceDataColumns($columns);
        $columns['currency'] = 'currency';
        return $columns;
    }
}

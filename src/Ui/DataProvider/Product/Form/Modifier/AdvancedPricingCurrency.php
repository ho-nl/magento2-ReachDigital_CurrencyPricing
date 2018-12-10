<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\CurrencyPricing\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;

class AdvancedPricingCurrency extends AbstractModifier
{
    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['tier_price']['children']['record']['children']['currency'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'dataType' => 'text',
                        'formElement'=> 'input',
                        'componentType' => 'field',
                        'label' => __('Currency'),
                        'dataScope' => 'currency',
                        'sortOrder' => 35
                    ]
                ]
            ]
        ];
        return $meta;
    }

    /**
     * @param array $data
     *
     * @return array
     * @since 100.1.0
     */
    public function modifyData(array $data)
    {
        return $data;
    }
}

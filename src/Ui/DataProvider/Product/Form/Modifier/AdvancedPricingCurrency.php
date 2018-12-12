<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\CurrencyPricing\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Form\Element\Select;

class AdvancedPricingCurrency extends AbstractModifier
{

    /**
     * @var \Magento\Directory\Model\Currency
     */
    private $currencyModel;

    /**
     * AdvancedPricingCurrency constructor.
     *
     * @param \Magento\Directory\Model\Currency $currencyModel
     */
    public function __construct(
        \Magento\Directory\Model\Currency $currencyModel
    )
    {
        $this->currencyModel = $currencyModel;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $currencyOptions = [['label' => 'Base currency', 'value' => null]];
        foreach ($this->currencyModel->getConfigAllowCurrencies() as $value) {
            $currencyOptions []= ['label' => $value, 'value' => $value];
        }
        $meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['tier_price']['children']['record']['children']['currency'] = [
            'arguments' => [
                'data' => [
                    'options' => $currencyOptions,
                    'config' => [
                        'dataType' => 'text',
                        'formElement'=> Select::NAME,
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

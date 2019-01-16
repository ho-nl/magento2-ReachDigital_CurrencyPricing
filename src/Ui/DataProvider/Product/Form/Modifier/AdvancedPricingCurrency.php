<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\CurrencyPricing\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Form\Element\Select;
use ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice;

class AdvancedPricingCurrency extends AbstractModifier
{

    /**
     * @var \Magento\Directory\Model\Currency
     */
    private $currencyModel;

    /**
     * @var LocatorInterface
     */
    private $locator;

    /**
     * @var CurrencyPrice
     */
    private $currencyPriceResourceModel;

    /**
     * AdvancedPricingCurrency constructor.
     *
     * @param LocatorInterface                                                $locator
     * @param \Magento\Directory\Model\Currency                               $currencyModel
     * @param CurrencyPrice $currencyPriceResourceModel
     */
    public function __construct(
        LocatorInterface $locator,
        \Magento\Directory\Model\Currency $currencyModel,
        CurrencyPrice $currencyPriceResourceModel
    )
    {
        $this->currencyModel = $currencyModel;
        $this->locator = $locator;
        $this->currencyPriceResourceModel = $currencyPriceResourceModel;
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

        $currencies = [];

        foreach ($this->currencyModel->getConfigAllowCurrencies() as $value) {
            $currencies [$value]= [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'componentType' => 'field',
                                'formElement' => 'input',
                                'dataType' => 'price',
                                'label' => $value,
                                'enableLable' => 'true',
                                'dataScope' => 'currency_price.' . $value
                            ]
                        ]
                    ]
                ];
        }

        $meta['product-details']['children']['container_currency_price'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        'formElement'=> 'container',
                        'breakLine' => false,
                        'label' => __('Currency price'),
                        'required' => '0',
                        'sortOrder' => 15
                    ]
                ]
            ],
            'children' => $currencies
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

        $productId = $this->locator->getProduct()->getId();

        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($productId, 'price');
        $currencyPriceData = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] = $currencyPriceObject['price'] === '0' ? '' : (string)$currencyPriceObject['price'];
        }

        if (count($currencyPriceData) == 0) {
            // set default to '' for currencies otherwise we cannot save
            foreach ($this->currencyModel->getConfigAllowCurrencies() as $value) {
                $currencyPriceData[$value] = "";
            }
        }

        $data[$productId][self::DATA_SOURCE_DEFAULT]['currency_price'] = $currencyPriceData;

        return $data;
    }
}

<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
namespace ReachDigital\CurrencyPricing\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Form\Element\Checkbox;
use Magento\Ui\Component\Form\Element\DataType\Number;
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

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
        CurrencyPrice $currencyPriceResourceModel,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->currencyModel = $currencyModel;
        $this->locator = $locator;
        $this->currencyPriceResourceModel = $currencyPriceResourceModel;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     */
    public function modifyMeta(array $meta)
    {
        $currentStore = $this->storeManager->getStore();
        $specificStoreScope = (int) $currentStore->getStoreId() !== 0;
        $storeviewId = (int) $currentStore->getId() === 0 ? null : $currentStore->getId();
        $productId = $this->locator->getProduct()->getId();
        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($productId, 'price', $storeviewId);
        $specialCurrencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData(
            $productId,
            'special',
            $storeviewId
        );
        $currencyPricesSet = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            if ($currencyPriceObject['price'] !== 0) {
                $currencyPricesSet[$currencyPriceObject['currency']] = true;
            }
        }
        $specialCurrencyPricesSet = [];
        foreach ($specialCurrencyPriceObjects as $currencyPriceObject) {
            if ($currencyPriceObject['price'] !== 0) {
                $specialCurrencyPricesSet[$currencyPriceObject['currency']] = true;
            }
        }

        if (
            $specificStoreScope &&
            isset(
                $meta['product-details']['children']['container_price']['children']['price']['arguments']['data'][
                    'config'
                ]['label']
            )
        ) {
            $meta['product-details']['children']['container_price']['children']['price']['arguments']['data']['config'][
                'data'
            ]['label'] = __('Website Price');
        }
        $currencyOptions = [['label' => 'Base currency', 'value' => null]];
        foreach ($this->currencyModel->getConfigAllowCurrencies() as $currency) {
            $currencyOptions[] = ['label' => $currency, 'value' => $currency];
        }
        if (isset($meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['tier_price'])) {
            $meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['tier_price']['children'][
                'record'
            ]['children']['currency'] = [
                'arguments' => [
                    'data' => [
                        'options' => $currencyOptions,
                        'config' => [
                            'dataType' => 'text',
                            'formElement' => Select::NAME,
                            'componentType' => 'field',
                            'label' => __('Currency'),
                            'dataScope' => 'currency',
                            'sortOrder' => 35,
                        ],
                    ],
                ],
            ];

            $meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['tier_price']['children'][
                'record'
            ]['children']['is_special'] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'dataType' => Number::NAME,
                            'formElement' => Checkbox::NAME,
                            'componentType' => 'field',
                            'label' => __('Is special price'),
                            'dataScope' => 'is_special',
                            'sortOrder' => 45,
                            'valueMap' => [
                                'true' => '1',
                                'false' => '0',
                            ],
                        ],
                    ],
                ],
            ];
        }

        $specialPriceCurrencies = [];
        foreach ($this->currencyModel->getConfigAllowCurrencies() as $currency) {
            $specialPriceCurrencies['special_price_currency_' . $currency] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => 'field',
                            'formElement' => 'input',
                            'dataType' => 'price',
                            'label' => $currency,
                            'enableLable' => 'true',
                            'dataScope' => 'special_price_currency.' . $currency,
                        ],
                    ],
                ],
            ];

            if ($specificStoreScope) {
                // Add 'Use Default' checkbox on storeview scope.
                $specialPriceCurrencies['special_price_currency_' . $currency]['arguments']['data']['config'][
                    'service'
                ]['template'] = 'ui/form/element/helper/service';
                if (!isset($specialCurrencyPricesSet[$currency])) {
                    $specialPriceCurrencies['special_price_currency_' . $currency]['arguments']['data']['config'][
                        'disabled'
                    ] = 1;
                }
            }
        }

        $meta['advanced_pricing_modal']['children']['advanced-pricing']['children']['special_price_currency'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'container',
                        'formElement' => 'container',
                        'breakLine' => false,
                        'label' => __('Special price currencies'),
                        'required' => '0',
                        'sortOrder' => 15,
                    ],
                ],
            ],
            'children' => $specialPriceCurrencies,
        ];

        $currencies = [];

        foreach ($this->currencyModel->getConfigAllowCurrencies() as $currency) {
            $currencies['currency_price_' . $currency] = [
                'arguments' => [
                    'data' => [
                        'config' => [
                            'componentType' => 'field',
                            'formElement' => 'input',
                            'dataType' => 'price',
                            'label' => $currency,
                            'enableLable' => 'true',
                            'dataScope' => 'currency_price.' . $currency,
                        ],
                    ],
                ],
            ];
            if ($specificStoreScope) {
                // Add 'Use Default' checkbox on storeview scope.
                $currencies['currency_price_' . $currency]['arguments']['data']['config']['service']['template'] =
                    'ui/form/element/helper/service';
                if (!isset($currencyPricesSet[$currency])) {
                    $currencies['currency_price_' . $currency]['arguments']['data']['config']['disabled'] = 1;
                }
            }
        }

        $meta['product-details']['children']['container_currency_price'] = [
            'arguments' => [
                'data' => [
                    'config' => [
                        'componentType' => 'fieldset',
                        'breakLine' => false,
                        'label' => __('Currency price'),
                        'required' => '0',
                        'sortOrder' => 15,
                        'collapsible' => true,
                        'opened' => true,
                    ],
                ],
            ],
            'children' => $currencies,
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
        $currentStore = $this->storeManager->getStore();
        $storeviewId = (int) $currentStore->getId() === 0 ? null : $currentStore->getId();
        $productId = $this->locator->getProduct()->getId();

        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($productId, 'price', $storeviewId);
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }
        $defaultCurencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($productId, 'price', null);
        foreach ($defaultCurencyPriceObjects as $currencyPriceObject) {
            // The price was already set for the specific scope, so no need to set the default scope price.
            if (
                isset($currencyPriceData[$currencyPriceObject['currency']]) &&
                $currencyPriceData[$currencyPriceObject['currency']] !== ''
            ) {
                continue;
            }
            $currencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }

        if (count($currencyPriceData) == 0 && count($defaultCurencyPriceObjects) == 0) {
            // set default to '' for currencies otherwise we cannot save
            foreach ($this->currencyModel->getConfigAllowCurrencies() as $value) {
                $currencyPriceData[$value] = '';
            }
        }

        $data[$productId][self::DATA_SOURCE_DEFAULT]['currency_price'] = $currencyPriceData;

        $specialCurrencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData(
            $productId,
            'special',
            $storeviewId
        );

        $specialCurrencyPriceData = [];
        foreach ($specialCurrencyPriceObjects as $currencyPriceObject) {
            $specialCurrencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }

        $defaultSpecialCurrencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData(
            $productId,
            'special',
            null
        );
        foreach ($defaultSpecialCurrencyPriceObjects as $currencyPriceObject) {
            // The price was already set for the specific scope, so no need to set the default scope price.
            if (
                isset($specialCurrencyPriceData[$currencyPriceObject['currency']]) &&
                $specialCurrencyPriceData[$currencyPriceObject['currency']] !== ''
            ) {
                continue;
            }
            $specialCurrencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }

        if (count($specialCurrencyPriceData) == 0 && count($defaultSpecialCurrencyPriceObjects) == 0) {
            // set default to '' for currencies otherwise we cannot save
            foreach ($this->currencyModel->getConfigAllowCurrencies() as $value) {
                $specialCurrencyPriceData[$value] = '';
            }
        }

        $data[$productId][self::DATA_SOURCE_DEFAULT]['special_price_currency'] = $specialCurrencyPriceData;

        return $data;
    }
}

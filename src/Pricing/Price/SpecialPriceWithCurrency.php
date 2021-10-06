<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Pricing\Price;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\SpecialPrice;
use Magento\Framework\Pricing\Adjustment\CalculatorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;
use ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice;

class SpecialPriceWithCurrency extends SpecialPrice
{
    /**
     * @var Store
     */
    private $store;

    /**
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var Product
     */
    private $saleableItem;

    /**
     * @var CurrencyPrice
     */
    private $currencyPriceResourceModel;

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    public function __construct(
        Product $saleableItem,
        float $quantity,
        CalculatorInterface $calculator,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        TimezoneInterface $localeDate,
        Store $store,
        StoreManager $storeManager,
        CurrencyPrice $currencyPriceResourceModel,
        RealBaseCurrency $realBaseCurrency
    ) {
        parent::__construct($saleableItem, $quantity, $calculator, $priceCurrency, $localeDate);
        $this->store = $store;
        $this->storeManager = $storeManager;
        $this->saleableItem = $saleableItem;
        $this->currencyPriceResourceModel = $currencyPriceResourceModel;
        $this->realBaseCurrency = $realBaseCurrency;
    }

    public function getSpecialPrice()
    {
        $currencyRate = $this->realBaseCurrency->getRealCurrentCurrencyRate();
        $specialPrice = $this->product->getSpecialPrice();
        if ($specialPrice !== null && $specialPrice !== false && $this->isPercentageDiscount()) {
            return $specialPrice;
        }

        $currenctCurrencyCode = $this->store->getCurrentCurrencyCode();

        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData(
            $this->saleableItem->getId(),
            'special'
        );
        $currencyPriceData = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }
        $this->saleableItem->setData('special_price_currency', $currencyPriceData);

        if (
            isset($this->saleableItem->getData('special_price_currency')[$currenctCurrencyCode]) &&
            $this->saleableItem->getData('special_price_currency')[$currenctCurrencyCode] !== ''
        ) {
            $convertedPrice = (float) $this->saleableItem->getData('special_price_currency')[$currenctCurrencyCode];
        } elseif ($specialPrice !== null) {
            $convertedPrice = $currencyRate * $specialPrice;
        } else {
            $convertedPrice = null;
        }

        return $convertedPrice;
    }
}

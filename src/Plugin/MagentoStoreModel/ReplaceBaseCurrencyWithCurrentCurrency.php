<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoStoreModel;

use Magento\Directory\Model\Currency;
use Magento\Store\Model\Store;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;

class ReplaceBaseCurrencyWithCurrentCurrency
{

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    private $currencyFactory;

    /**
     * ReplaceBaseCurrencyWithCurrentCurrency constructor.
     *
     * @param RealBaseCurrency                         $realBaseCurrency
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     */
    public function __construct(
        RealBaseCurrency $realBaseCurrency,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory
    )
    {
        $this->realBaseCurrency = $realBaseCurrency;
        $this->currencyFactory = $currencyFactory;
    }

    /**
     * @param Store    $subject
     * @param \Closure $proceed
     *
     * @return Currency
     */
    public function aroundGetBaseCurrency(Store $subject, \Closure $proceed): Currency {
        return $subject->getCurrentCurrency();
    }

    /**
     * @param Store    $subject
     * @param \Closure $proceed
     *
     * @return string
     */
    public function aroundGetBaseCurrencyCode(Store $subject, \Closure $proceed): string {
        return $subject->getCurrentCurrencyCode();
    }

    /**
     * @param Store    $subject
     * @param \Closure $proceed
     *
     * @return Currency
     */
    public function aroundGetCurrentCurrency(Store $subject, \Closure $proceed): Currency {
        $currency = $subject->getData('current_currency');

        if ($currency === null) {
            $currency = $this->currencyFactory->create()->load($subject->getCurrentCurrencyCode());
            $baseCurrency = $this->realBaseCurrency->getRealBaseCurrency();

            if (!$baseCurrency->getRate($currency)) {
                $currency = $baseCurrency;
                $subject->setCurrentCurrencyCode($baseCurrency->getCode());
            }
            $subject->setData('current_currency', $currency);
        }
        return $currency;
    }

    /**
     * Get allowed store currency codes
     *
     * If base currency is not allowed in current website config scope,
     * then it can be disabled with $skipBaseNotAllowed
     *
     * Note: we override the original to use the realBaseCurrency, instead of the regular base currency we override with this plugin.
     *
     * @param bool $skipBaseNotAllowed
     * @return array
     */
    public function aroundGetAvailableCurrencyCodes(Store $subject, \Closure $proceed, $skipBaseNotAllowed = false) : array
    {
        $codes = $subject->getData('available_currency_codes');
        if (null === $codes) {
            $codes = explode(',', $subject->getConfig(Currency::XML_PATH_CURRENCY_ALLOW));
            // add base currency, if it is not in allowed currencies
            $baseCurrencyCode = $this->realBaseCurrency->getRealBaseCurrencyCode();
            if (!in_array($baseCurrencyCode, $codes)) {
                $codes[] = $baseCurrencyCode;

                // save base currency code index for further usage
                $disallowedBaseCodeIndex = array_keys($codes);
                $disallowedBaseCodeIndex = array_pop($disallowedBaseCodeIndex);
                $subject->setData('disallowed_base_currency_code_index', $disallowedBaseCodeIndex);
            }
            $subject->setData('available_currency_codes', $codes);
        }

        // remove base currency code, if it is not allowed by config (optional)
        if ($skipBaseNotAllowed) {
            $disallowedBaseCodeIndex = $subject->getData('disallowed_base_currency_code_index');
            if (null !== $disallowedBaseCodeIndex) {
                unset($codes[$disallowedBaseCodeIndex]);
            }
        }
        return $codes;
    }

}

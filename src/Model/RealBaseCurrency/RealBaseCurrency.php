<?php

namespace ReachDigital\CurrencyPricing\Model\RealBaseCurrency;

use Magento\Directory\Model\Currency;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;

class RealBaseCurrency
{

    /**
     * @var Store
     */
    private $store;

    /**
     * @var \Magento\Directory\Model\CurrencyFactory
     */
    private $currencyFactory;

    /**
     * @var \Magento\Framework\App\Config\ReinitableConfigInterface
     */
    private $config;

    /**
     * RealBaseCurrency constructor.
     *
     * @param Store                                                   $store
     * @param \Magento\Directory\Model\CurrencyFactory                $currencyFactory
     * @param \Magento\Framework\App\Config\ReinitableConfigInterface $config
     */
    public function __construct(
        Store $store,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Framework\App\Config\ReinitableConfigInterface $config
    )
    {
        $this->store = $store;
        $this->currencyFactory = $currencyFactory;
        $this->config = $config;
    }

    /**
     * Since we hide the getBaseCurrency method in the store through the ReplaceBaseCurrencyWithCurrentCurrency plugin we provide this method in case we need the actual base currency.
     * @return Currency
     */
    public function getRealBaseCurrency(): Currency
    {
        $currency = $this->store->getData('base_currency');
        if (null === $currency) {
            $currency = $this->currencyFactory->create()->load($this->getRealBaseCurrencyCode());
            $this->store->setData('base_currency', $currency);
        }
        return $currency;
    }

    /**
     * Since we hide the getBaseCurrencyCode method in the store through the ReplaceBaseCurrencyWithCurrentCurrency plugin we provide this method in case we need the actual base currency.
     * @return string
     */
    public function getRealBaseCurrencyCode(): string {
        $configValue = $this->store->getConfig(Store::XML_PATH_PRICE_SCOPE);
        if ($configValue == Store::PRICE_SCOPE_GLOBAL) {
            return $this->config->getValue(Currency::XML_PATH_CURRENCY_BASE, ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
        }
        return $this->store->getConfig(Currency::XML_PATH_CURRENCY_BASE);
    }
}

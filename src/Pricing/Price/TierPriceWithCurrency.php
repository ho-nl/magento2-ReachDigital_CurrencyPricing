<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Pricing\Price;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\TierPrice;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Group\RetrieverInterface as CustomerGroupRetrieverInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Pricing\Adjustment\CalculatorInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\Store;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;

class TierPriceWithCurrency extends TierPrice
{

    /**
     * @var Store
     */
    private $store;

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    /**
     * @var Product
     */
    private $saleableItem;

    /**
     * @var TimezoneInterface
     */
    private $localeDate;

    public function __construct(
        Product $saleableItem,
        float $quantity,
        CalculatorInterface $calculator,
        \Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
        Session $customerSession,
        GroupManagementInterface $groupManagement,
        Store $store,
        RealBaseCurrency $realBaseCurrency,
        TimezoneInterface $localeDate,
        ?CustomerGroupRetrieverInterface $customerGroupRetriever = null
    ) {
        parent::__construct($saleableItem, $quantity, $calculator, $priceCurrency, $customerSession, $groupManagement,
            $customerGroupRetriever);
        $this->store = $store;
        $this->realBaseCurrency = $realBaseCurrency;
        $this->saleableItem = $saleableItem;
        $this->localeDate = $localeDate;
    }

    /**
     * Can apply tier price
     *
     * @param array $currentTierPrice
     * @param int $prevPriceGroup
     * @param float|string $prevQty
     * @return bool
     */
    protected function canApplyTierPrice(array $currentTierPrice, $prevPriceGroup, $prevQty)
    {
        if (!parent::canApplyTierPrice($currentTierPrice, $prevPriceGroup, $prevQty)) {
            return false;
        }
        $isSpecialPriceActive = $this->localeDate->isScopeDateInInterval($this->saleableItem->getStore(), $this->saleableItem->getSpecialFromDate(), $this->saleableItem->getSpecialToDate());

        if (!$isSpecialPriceActive && isset($currentTierPrice['is_special']) && $currentTierPrice['is_special'] === '1') {
            return false;
        }
        $currenctCurrencyCode = $this->store->getCurrentCurrencyCode();
        if (!isset($currentTierPrice['currency']) || $currentTierPrice['currency'] === null) {
            $realBaseCurrencyCode = $this->realBaseCurrency->getRealBaseCurrencyCode();
            return $currenctCurrencyCode === $realBaseCurrencyCode;
        }
        return $currentTierPrice['currency'] === $currenctCurrencyCode;
    }
}

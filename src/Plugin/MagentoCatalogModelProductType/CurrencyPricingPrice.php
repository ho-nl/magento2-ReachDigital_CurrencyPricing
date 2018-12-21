<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Store\Model\Store;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;

class CurrencyPricingPrice
{

    /**
     * @var GroupManagementInterface
     */
    private $groupManagement;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Store
     */
    private $store;

    /**
     * @var RealBaseCurrency
     */
    private $realBaseCurrency;

    /**
     * CurrencyPricingPrice constructor.
     *
     * @param GroupManagementInterface                          $groupManagement
     * @param Session                                           $customerSession
     * @param Store                                             $store
     * @param RealBaseCurrency                                  $realBaseCurrency
     */
    public function __construct(
        GroupManagementInterface $groupManagement,
        Session $customerSession,
        Store $store,
        RealBaseCurrency $realBaseCurrency
    )
    {
        $this->groupManagement = $groupManagement;
        $this->customerSession = $customerSession;
        $this->store = $store;
        $this->realBaseCurrency = $realBaseCurrency;
    }

    /**
     * Plugin for Price::getBasePrice().
     * Ensures that the returned price is already correct for the current currency of the customer.
     *
     * Normally conversion using the rate of the current currency is done later by magento, but this is circumvented by changing the BaseCurrency returned by the store into the CurrentCurrency (See ReplaceBaseCurrencyWithCurrentCurrency plugin).
     *
     * @param Price    $subject
     * @param \Closure $proceed
     *
     * @param Product  $product
     * @param float    $qty
     *
     * @return float|array
     */
    public function aroundGetBasePrice(Price $subject, \Closure $proceed, Product $product, $qty = null) {
        $currenctCurrencyCode = $this->store->getCurrentCurrencyCode();
        $currencyRate = $this->realBaseCurrency->getRealCurrentCurrencyRate();
        $price = (float) $product->getPrice();

        if ($product->getData('currency_price')[$currenctCurrencyCode] !== '') {
            $convertedPrice = (float)$product->getData('currency_price')[$currenctCurrencyCode];
        } else {
            $convertedPrice = $currencyRate * $price;
        }
        $tierPrice = $this->_applyTierPrice($product, $qty, $convertedPrice);
        $specialPrice = $this->_applySpecialPrice($subject, $product, $convertedPrice, $currencyRate);
        return min(
            $tierPrice,
            $specialPrice
        );
    }

    /**
     * Apply special price for product if not return price that was before
     *
     * @param Price     $subject
     * @param   Product $product
     * @param   float   $finalPrice
     *
     * @return  float
     */
    protected function _applySpecialPrice(Price $subject, Product $product, float $finalPrice, float $currencyRate) :float
    {
        return $subject->calculateSpecialPrice(
            $finalPrice,
            $product->getSpecialPrice() * $currencyRate,
            $product->getSpecialFromDate(),
            $product->getSpecialToDate(),
            $product->getStore()
        );
    }

    /**
     * Apply tier price for product if not return price that was before
     *
     * @param   Product $product
     * @param   float $qty
     * @param   float $finalPrice
     * @return  float
     */
    protected function _applyTierPrice(Product $product, $qty, float $finalPrice) :float
    {
        if ($qty === null) {
            return $finalPrice;
        }

        $tierPrice = $product->getTierPrice($qty);
        if (is_numeric($tierPrice)) {
            $finalPrice = min($finalPrice, $tierPrice);
        }
        return $finalPrice;
    }

    /**
     * @param Price    $subject
     * @param \Closure $proceed
     *
     * @param float    $qty
     * @param Product  $product
     *
     * @return float|array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundGetTierPrice(Price $subject, \Closure $proceed, float $qty, Product $product) {

        $allGroupsId = $this->getAllCustomerGroupsId();

        $prices = $this->getExistingPrices($product);
        if ($prices === null || !is_array($prices)) {
            if ($qty !== null) {
                return $product->getPrice();
            } else {
                return [
                    [
                        'price' => $product->getPrice(),
                        'website_price' => $product->getPrice(),
                        'price_qty' => 1,
                        'cust_group' => $allGroupsId,
                    ]
                ];
            }
        }

        $custGroup = $this->_getCustomerGroupId($product);
        if ($qty) {
            $prevQty = 1;
            $prevPrice = $product->getPrice() * $this->realBaseCurrency->getRealCurrentCurrencyRate();
            $prevGroup = $allGroupsId;

            foreach ($prices as $price) {
                if ($price['cust_group'] != $custGroup && $price['cust_group'] != $allGroupsId) {
                    // tier not for current customer group nor is for all groups
                    continue;
                }
                if ($qty < $price['price_qty']) {
                    // tier is higher than product qty
                    continue;
                }
                if ($price['price_qty'] < $prevQty) {
                    // higher tier qty already found
                    continue;
                }
                if ($price['price_qty'] == $prevQty &&
                    $prevGroup != $allGroupsId &&
                    $price['cust_group'] == $allGroupsId) {
                    // found tier qty is same as current tier qty but current tier group is ALL_GROUPS
                    continue;
                }
                if (!$this->isTierPriceAllowed($price)) {
                    continue;
                }
                if ($price['website_price'] < $prevPrice) {
                    $prevPrice = $price['website_price'];
                    $prevQty = $price['price_qty'];
                    $prevGroup = $price['cust_group'];
                }
            }
            return $prevPrice;
        } else {
            $qtyCache = [];
            foreach ($prices as $priceKey => $price) {
                if ($price['cust_group'] != $custGroup && $price['cust_group'] != $allGroupsId) {
                    unset($prices[$priceKey]);
                } elseif (isset($qtyCache[$price['price_qty']])) {
                    $priceQty = $qtyCache[$price['price_qty']];
                    if ($prices[$priceQty]['website_price'] > $price['website_price']) {
                        unset($prices[$priceQty]);
                        $qtyCache[$price['price_qty']] = $priceKey;
                    } else {
                        unset($prices[$priceKey]);
                    }
                } else {
                    $qtyCache[$price['price_qty']] = $priceKey;
                }
            }
        }

        return $prices ?: [];
    }

    /**
     * This is a simplified version of Price::getExistingPrices.
     * It no longer takes the $key as an argument but assumes that this is 'tier_price' (as the original also seems to assume given the reference to tear_price in the comments).
     * It also assumes $returnRawData is true.
     *
     * I am fairly convinced that this entire function could be replaced by $product->getTierPrices() but not completely sure.
     *
     * @param Product $product
     * @return array
     */
    protected function getExistingPrices(Product $product)
    {
        $prices = $product->getData('tier_price');

        if ($prices === null) {
            $attribute = $product->getResource()->getAttribute('tier_price');
            if ($attribute) {
                $attribute->getBackend()->afterLoad($product);
                $prices = $product->getData('tier_price');
            }
        }

        return $prices;
    }

    /**
     * @param Product $product
     * @return int
     */
    protected function _getCustomerGroupId(Product $product): int
    {
        if ($product->getCustomerGroupId() !== null) {
            return $product->getCustomerGroupId();
        }
        return $this->customerSession->getCustomerGroupId();
    }

    /**
     * Gets the CUST_GROUP_ALL id
     *
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getAllCustomerGroupsId()
    {
        // ex: 32000
        return $this->groupManagement->getAllCustomersGroup()->getId();
    }

    /**
     * Determines whether the given tierPrice is allowed to be used given the users settings etc..
     * @param $price
     *
     * @return bool
     */
    public function isTierPriceAllowed($price) :bool
    {
        $currenctCurrencyCode = $this->store->getCurrentCurrencyCode();
        if (!isset($price['currency']) || $price['currency'] === null) {
            $realBaseCurrencyCode = $this->realBaseCurrency->getRealBaseCurrencyCode();
            return $currenctCurrencyCode === $realBaseCurrencyCode;
        }
        return $price['currency'] === $currenctCurrencyCode;
    }
}

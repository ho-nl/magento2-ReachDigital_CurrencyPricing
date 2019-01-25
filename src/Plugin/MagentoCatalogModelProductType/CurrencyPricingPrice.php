<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType;

use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
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
     * @var StoreManager
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @var ProductTierPriceInterfaceFactory
     */
    private $tierPriceFactory;

    /**
     * @var ProductTierPriceExtensionFactory|null
     */
    private $tierPriceExtensionFactory;

    /**
     * @var \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice
     */
    private $currencyPriceResourceModel;

    /**
     * CurrencyPricingPrice constructor.
     *
     * @param GroupManagementInterface                                   $groupManagement
     * @param Session                                                    $customerSession
     * @param Store                                                      $store
     * @param RealBaseCurrency                                           $realBaseCurrency
     * @param StoreManager                                               $storeManager
     * @param ScopeConfigInterface                                       $config
     * @param ProductTierPriceInterfaceFactory $tierPriceFactory
     * @param ProductTierPriceExtensionFactory|null                      $tierPriceExtensionFactory
     */
    public function __construct(
        GroupManagementInterface $groupManagement,
        Session $customerSession,
        Store $store,
        RealBaseCurrency $realBaseCurrency,
        StoreManager $storeManager,
        ScopeConfigInterface $config,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice $currencyPriceResourceModel,
        ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null
    )
    {
        $this->groupManagement = $groupManagement;
        $this->customerSession = $customerSession;
        $this->store = $store;
        $this->realBaseCurrency = $realBaseCurrency;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->tierPriceFactory = $tierPriceFactory;
        $this->tierPriceExtensionFactory = $tierPriceExtensionFactory ?: ObjectManager::getInstance()
            ->get(ProductTierPriceExtensionFactory::class);
        $this->currencyPriceResourceModel= $currencyPriceResourceModel;
    }

    public function aroundGetPrice(\Magento\Catalog\Model\Product\Type\Price $subject, \Closure $proceed, Product $product) {
        $currenctCurrencyCode = $this->store->getCurrentCurrencyCode();
        $currencyRate = $this->realBaseCurrency->getRealCurrentCurrencyRate();
        $price = (float) $product->getData('price');

        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($product->getId(), 'price');
        $currencyPriceData = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] = $currencyPriceObject['price'] === '0' ? '' : (string)$currencyPriceObject['price'];
        }
        $product->setData('currency_price', $currencyPriceData);

        if (isset($product->getData('currency_price')[$currenctCurrencyCode]) && $product->getData('currency_price')[$currenctCurrencyCode] !== '') {
            $convertedPrice = (float)$product->getData('currency_price')[$currenctCurrencyCode];
        } else {
            $convertedPrice = $currencyRate * $price;
        }

        return $convertedPrice;
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
        $currencyRate = $this->realBaseCurrency->getRealCurrentCurrencyRate();
        $convertedPrice = $product->getPrice();
        $tierPrice = $this->_applyTierPrice($product, $qty, $convertedPrice);
        $specialPrice = $this->_applySpecialPrice($subject, $product, $convertedPrice, $currencyRate);
        return min(
            $convertedPrice,
            $tierPrice,
            $specialPrice
        );
    }

    /**
     * Plugin for Price::setTierPrices().
     *
     * Ensures that the currency for the tier prices is also set.
     *
     * @param Price      $subject
     * @param \Closure   $proceed
     *
     * @param Product    $product
     * @param array|null $tierPrices
     *
     * @return Price
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundSetTierPrices(Price $subject, \Closure $proceed, Product $product, array $tierPrices = null): Price {
        // null array means leave everything as is
        if ($tierPrices === null) {
            return $subject;
        }

        $allGroupsId = $this->getAllCustomerGroupsId();
        $websiteId = $this->getWebsiteForPriceScope();

        // build the new array of tier prices
        $prices = [];
        foreach ($tierPrices as $price) {
            $extensionAttributes = $price->getExtensionAttributes();
            $priceWebsiteId = $websiteId;
            if (isset($extensionAttributes) && is_numeric($extensionAttributes->getWebsiteId())) {
                $priceWebsiteId = (string)$extensionAttributes->getWebsiteId();
            }
            $prices[] = [
                'website_id' => $priceWebsiteId,
                'cust_group' => $price->getCustomerGroupId(),
                'website_price' => $price->getValue(),
                'price' => $price->getValue(),
                'all_groups' => ($price->getCustomerGroupId() == $allGroupsId),
                'price_qty' => $price->getQty(),
                'percentage_value' => $extensionAttributes ? $extensionAttributes->getPercentageValue() : null,
                'currency' => $price->getData('currency')
            ];
        }
        $product->setData('tier_price', $prices);

        return $subject;
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
        // TODO Allow special price to be set per currency.
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
            $tierPrice = $product->getTierPrice(1);
        } else {
            $tierPrice = $product->getTierPrice($qty);
        }
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
    public function aroundGetTierPrice(Price $subject, \Closure $proceed, $qty, Product $product) {

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
            $prevPrice = PHP_INT_MAX;
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
     * Gets list of product tier prices including currency
     *
     * @param Price    $subject
     * @param \Closure $proceed
     * @param Product  $product
     *
     * @return \Magento\Catalog\Api\Data\ProductTierPriceInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundGetTierPrices(Price $subject, \Closure $proceed, Product $product): array
    {
        $prices = [];
        $tierPrices = $this->getExistingPrices($product);
        foreach ($tierPrices as $price) {
            /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice */
            $tierPrice = $this->tierPriceFactory->create()
                ->setExtensionAttributes($this->tierPriceExtensionFactory->create());
            $tierPrice->setCustomerGroupId($price['cust_group']);
            if (array_key_exists('website_price', $price)) {
                $value = $price['website_price'];
            } else {
                $value = $price['price'];
            }
            $tierPrice->setValue($value);
            $tierPrice->setQty($price['price_qty']);
            if (isset($price['percentage_value'])) {
                $tierPrice->getExtensionAttributes()->setPercentageValue($price['percentage_value']);
            }
            $websiteId = isset($price['website_id']) ? $price['website_id'] : $this->getWebsiteForPriceScope();
            $tierPrice->getExtensionAttributes()->setWebsiteId($websiteId);

            if (array_key_exists('currency', $price)) {
                $tierPrice['currency'] = $price['currency'];
            }

            $prices[] = $tierPrice;
        }
        return $prices;
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
     * Returns the website to use for group or tier prices, based on the price scope setting
     *
     * @return int|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getWebsiteForPriceScope()
    {
        $websiteId = 0;
        $value = $this->config->getValue('catalog/price/scope', \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE);
        if ($value != 0) {
            // use the website associated with the current store
            $websiteId = $this->storeManager->getWebsite()->getId();
        }
        return $websiteId;
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

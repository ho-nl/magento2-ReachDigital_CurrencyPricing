<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelResourceModel;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Model\AbstractModel;
use ReachDigital\CurrencyPricing\Model\CurrencyPriceFactory;

class ProductWithCurrencyPrices
{

    /**
     * @var CurrencyPriceFactory
     */
    private $currencyPriceFactory;

    /**
     * @var \ReachDigital\CurrencyPricing\Model\ResourceModel|\ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice
     */
    private $currencyPriceResourceModel;

    /**
     * ProductWithCurrencyPrices constructor.
     *
     * @param CurrencyPriceFactory                                            $currencyPriceFactory
     * @param \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice $currencyPriceResourceModel
     */
    function __construct(
        CurrencyPriceFactory $currencyPriceFactory,
        \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice $currencyPriceResourceModel)
    {
        $this->currencyPriceFactory = $currencyPriceFactory;
        $this->currencyPriceResourceModel = $currencyPriceResourceModel;
    }

    /**
     * @param Product       $subject
     * @param \Closure      $proceed
     * @param AbstractModel $object
     *
     * @return Product
     */
    public function aroundSave(Product $subject, \Closure $proceed, AbstractModel $object): Product
    {
        /** @var Product $returnValue */
        $returnValue = $proceed($object);
        $currencyPrices = $object->getData('currency_price');
        $originalPrices = $this->currencyPriceResourceModel->loadPriceData($object->getId(), 'price');
        // TODO Fix CurrencyPrices being null when creating new Product.
        if ($currencyPrices !== null) {
            foreach ($currencyPrices as $currency => $currencyPrice) {
                $original = null;
                foreach ($originalPrices as $originalPrice) {
                    if ($originalPrice['currency'] === $currency) {
                        $original = $originalPrice;
                        break;
                    }
                }
                $this->savePrice($currency, $currencyPrice, $object->getId(),
                    $original === null ? null : $original['currency_price_id']);
            }
        }
        return $returnValue;
    }

    private function savePrice($currency, $currencyPrice, $priceId, $currencyPriceId)
    {
        $dataArray = [
            'currency' => $currency,
            'type' => 'price',
            'price' => $currencyPrice,
            'entity_id' => $priceId
        ];
        if ($currencyPriceId !== null) {
            $dataArray['currency_price_id'] = $currencyPriceId;
        }
        $currencyPriceObject = new \Magento\Framework\DataObject(
            $dataArray
        );

        $this->currencyPriceResourceModel->savePriceData($currencyPriceObject);
    }

    /**
     * @param Product       $subject
     * @param               $result
     * @param AbstractModel $object
     *
     * @param               $entityId
     * @param array         $attributes
     *
     * @return Product
     */
    public function afterLoad(Product $subject, $result, AbstractModel $object, $entityId, $attributes = []): Product
    {
        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceData($entityId, 'price');
        $currencyPriceData = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] = $currencyPriceObject['price'] === '0' ? '' : (string)$currencyPriceObject['price'];
        }
        $object->setData('currency_price' , $currencyPriceData);
        return $result;
    }

}

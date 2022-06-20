<?php

namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelResourceModel;

use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\AbstractModel;
use ReachDigital\CurrencyPricing\Model\CurrencyPrice;
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
     * @var RequestInterface
     */
    private $request;

    /**
     * ProductWithCurrencyPrices constructor.
     *
     * @param CurrencyPriceFactory                                            $currencyPriceFactory
     * @param \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice $currencyPriceResourceModel
     */
    function __construct(
        CurrencyPriceFactory $currencyPriceFactory,
        \ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice $currencyPriceResourceModel,
        RequestInterface $request
    ) {
        $this->currencyPriceFactory = $currencyPriceFactory;
        $this->currencyPriceResourceModel = $currencyPriceResourceModel;
        $this->request = $request;
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
        $storeId = $object->getStoreId() === 0 ? null : $object->getStoreId();
        $originalPrices = $this->currencyPriceResourceModel->loadPriceData($object->getId(), 'price', $storeId);
        if ($currencyPrices === null) {
            $currencyPrices = [];
        }
        $savedPriceIds = [];
        $useDefault = $this->request->getParam('use_default');
        foreach ($currencyPrices as $currency => $value) {
            if (
                isset($useDefault['currency_price_' . $currency]) &&
                $useDefault['currency_price_' . $currency] === '1'
            ) {
                unset($currencyPrices[$currency]);
            }
        }
        foreach ($currencyPrices as $currency => $currencyPrice) {
            $original = null;
            foreach ($originalPrices as $originalPrice) {
                if ($originalPrice['currency'] === $currency && (int) $originalPrice['storeview_id'] === $storeId) {
                    $original = $originalPrice;
                    break;
                }
            }
            $this->savePrice(
                $currency,
                $currencyPrice,
                $object->getId(),
                $original === null ? null : $original['currency_price_id'],
                'price',
                $storeId
            );
            if ($original !== null) {
                $savedPriceIds[] = $original['currency_price_id'];
            }
        }
        foreach ($originalPrices as $currencyPrice) {
            if (!isset($savedPriceIds[$currencyPrice['currency_price_id']])) {
                $currencyPriceModel = $this->currencyPriceFactory->create();
                $currencyPriceModel->setData($currencyPrice);
                $this->currencyPriceResourceModel->delete($currencyPriceModel);
            }
        }
        $object->unsetData('currency_price');

        $specialCurrencyPrices = $object->getData('special_price_currency');
        $originalCurrencyPrices = $this->currencyPriceResourceModel->loadPriceData(
            $object->getId(),
            'special',
            $storeId
        );
        if ($specialCurrencyPrices === null) {
            $specialCurrencyPrices = [];
        }
        $useDefault = $this->request->getParam('use_default');
        foreach ($specialCurrencyPrices as $currency => $value) {
            if (
                isset($useDefault['special_price_currency_' . $currency]) &&
                $useDefault['special_price_currency_' . $currency] === '1'
            ) {
                unset($specialCurrencyPrices[$currency]);
            }
        }
        foreach ($specialCurrencyPrices as $currency => $currencyPrice) {
            $original = null;
            foreach ($originalCurrencyPrices as $originalPrice) {
                if ($originalPrice['currency'] === $currency && (int) $originalPrice['storeview_id'] === $storeId) {
                    $original = $originalPrice;
                    break;
                }
            }
            $this->savePrice(
                $currency,
                $currencyPrice,
                $object->getId(),
                $original === null ? null : $original['currency_price_id'],
                'special',
                $storeId
            );
            if ($original !== null) {
                $savedPriceIds[] = $original['currency_price_id'];
            }
        }
        foreach ($originalCurrencyPrices as $currencyPrice) {
            if (!isset($savedPriceIds[$currencyPrice['currency_price_id']])) {
                $currencyPriceModel = $this->currencyPriceFactory->create();
                $currencyPriceModel->setData($currencyPrice);
                $this->currencyPriceResourceModel->delete($currencyPriceModel);
            }
        }
        $object->unsetData('special_price_currency');

        return $returnValue;
    }

    private function savePrice($currency, $currencyPrice, $priceId, $currencyPriceId, $type, $storeviewId)
    {
        $dataArray = [
            'currency' => $currency,
            'type' => $type,
            'price' => $currencyPrice,
            'entity_id' => $priceId,
            'storeview_id' => $storeviewId,
        ];
        if ($currencyPriceId !== null) {
            $dataArray['currency_price_id'] = $currencyPriceId;
        }
        $currencyPriceObject = new \Magento\Framework\DataObject($dataArray);

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
        $currencyPriceObjects = $this->currencyPriceResourceModel->loadPriceDataForDisplay($entityId, 'price');
        $currencyPriceData = [];
        foreach ($currencyPriceObjects as $currencyPriceObject) {
            $currencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }
        $object->setData('currency_price', $currencyPriceData);

        $specialCurrencyPriceObjects = $this->currencyPriceResourceModel->loadPriceDataForDisplay($entityId, 'special');
        $specialCurrencyPriceData = [];
        foreach ($specialCurrencyPriceObjects as $currencyPriceObject) {
            $specialCurrencyPriceData[$currencyPriceObject['currency']] =
                (int) $currencyPriceObject['price'] === 0 ? '' : (string) $currencyPriceObject['price'];
        }
        $object->setData('special_price_currency', $specialCurrencyPriceData);
        return $result;
    }
}

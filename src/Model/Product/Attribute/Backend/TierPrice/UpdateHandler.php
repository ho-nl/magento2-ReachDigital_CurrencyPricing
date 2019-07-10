<?php

namespace ReachDigital\CurrencyPricing\Model\Product\Attribute\Backend\TierPrice;

use Magento\Framework\EntityManager\Operation\ExtensionInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice;

class UpdateHandler extends \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\UpdateHandler
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var \Magento\Customer\Api\GroupManagementInterface
     */
    protected $groupManagement;

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    private $metadataPoll;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice
     */
    private $tierPriceResource;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Api\ProductAttributeRepositoryInterface $attributeRepository
     * @param \Magento\Customer\Api\GroupManagementInterface $groupManagement
     * @param \Magento\Framework\EntityManager\MetadataPool $metadataPool
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice $tierPriceResource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ProductAttributeRepositoryInterface $attributeRepository,
        GroupManagementInterface $groupManagement,
        MetadataPool $metadataPool,
        Tierprice $tierPriceResource
    ) {
        parent::__construct($storeManager,
            $attributeRepository,
            $groupManagement,
            $metadataPool,
            $tierPriceResource);
        $this->storeManager = $storeManager;
        $this->attributeRepository = $attributeRepository;
        $this->groupManagement = $groupManagement;
        $this->metadataPoll = $metadataPool;
        $this->tierPriceResource = $tierPriceResource;
    }

    /**
     * @param \Magento\Catalog\Api\Data\ProductInterface|object $entity
     * @param array $arguments
     * @return \Magento\Catalog\Api\Data\ProductInterface|object
     * @throws \Magento\Framework\Exception\InputException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute($entity, $arguments = [])
    {
        $attribute = $this->attributeRepository->get('tier_price');
        $priceRows = $entity->getData($attribute->getName());
        if (null !== $priceRows) {
            if (!is_array($priceRows)) {
                throw new \Magento\Framework\Exception\InputException(
                    __('Tier prices data should be array, but actually other type is received')
                );
            }
            $websiteId = (int)$this->storeManager->getStore($entity->getStoreId())->getWebsiteId();
            $isGlobal = $attribute->isScopeGlobal() || $websiteId === 0;
            $identifierField = $this->metadataPoll->getMetadata(ProductInterface::class)->getLinkField();
            $productId = (int)$entity->getData($identifierField);

            // prepare original data to compare
            $origPrices = [];
            $originalId = $entity->getOrigData($identifierField);
            if (empty($originalId) || $entity->getData($identifierField) == $originalId) {
                $origPrices = $entity->getOrigData($attribute->getName());
            }

            $old = $this->prepareOldTierPriceToCompare($origPrices);
            // prepare data for save
            $new = $this->prepareNewDataForSave($priceRows, $isGlobal);

            $delete = array_diff_key($old, $new);
            $insert = array_diff_key($new, $old);
            $update = array_intersect_key($new, $old);

            $isAttributeChanged = $this->deleteValues($productId, $delete);
            $isAttributeChanged |= $this->insertValues($productId, $insert);
            $isAttributeChanged |= $this->updateValues($update, $old);

            if ($isAttributeChanged) {
                $valueChangedKey = $attribute->getName() . '_changed';
                $entity->setData($valueChangedKey, 1);
            }
        }

        return $entity;
    }

    /**
     * Get additional tier price fields
     *
     * @param array $objectArray
     * @return array
     */
    protected function getAdditionalFields(array $objectArray): array
    {
        $percentageValue = $this->getPercentage($objectArray);
        return [
            'value' => $percentageValue ? null : $objectArray['price'],
            'percentage_value' => $percentageValue ?: null,
        ];
    }

    /**
     * Check whether price has percentage value.
     *
     * @param array $priceRow
     * @return int|null
     */
    protected function getPercentage(array $priceRow)
    {
        return isset($priceRow['percentage_value']) && is_numeric($priceRow['percentage_value'])
            ? (int)$priceRow['percentage_value']
            : null;
    }

    /**
     * Update existing tier prices for processed product
     *
     * @param array $valuesToUpdate
     * @param array $oldValues
     * @return boolean
     */
    private function updateValues(array $valuesToUpdate, array $oldValues): bool
    {
        $isChanged = false;
        foreach ($valuesToUpdate as $key => $value) {
            if ((!empty($value['value']) && (float)$oldValues[$key]['price'] !== (float)$value['value'])
                || $this->_valuesUpdated($valuesToUpdate, $oldValues)
                || $this->getPercentage($oldValues[$key]) !== $this->getPercentage($value)
            ) {
                $price = new \Magento\Framework\DataObject(
                    array_merge(
                        $this->_getAdditionalFields($valuesToUpdate),
                        [
                            'value_id' => $oldValues[$key]['price_id'],
                            'value' => $value['value'],
                            'percentage_value' => $this->getPercentage($value)
                        ])
                );
                $this->tierPriceResource->savePriceData($price);
                $isChanged = true;
            }
        }

        return $isChanged;
    }

    /**
     * Insert new tier prices for processed product.
     *
     * @param int $productId
     * @param array $valuesToInsert
     * @return bool
     */
    private function insertValues(int $productId, array $valuesToInsert): bool
    {
        $isChanged = false;
        $identifierField = $this->metadataPoll->getMetadata(ProductInterface::class)->getLinkField();
        foreach ($valuesToInsert as $data) {
            $price = new \Magento\Framework\DataObject($data);
            $price->setData(
                $identifierField,
                $productId
            );
            $this->tierPriceResource->savePriceData($price);
            $isChanged = true;
        }

        return $isChanged;
    }

    /**
     * Delete tier price values for processed product.
     *
     * @param int $productId
     * @param array $valuesToDelete
     * @return bool
     */
    private function deleteValues(int $productId, array $valuesToDelete): bool
    {
        $isChanged = false;
        foreach ($valuesToDelete as $data) {
            $this->tierPriceResource->deletePriceData($productId, null, $data['price_id']);
            $isChanged = true;
        }

        return $isChanged;
    }

    /**
     * Get generated price key based on price data.
     *
     * @param array $priceData
     * @return string
     */
    private function getPriceKey(array $priceData): string
    {
        $key = implode(
            '-',
            array_merge($this->_getAdditionalUniqueFields($priceData), [$priceData['website_id'], $priceData['cust_group'], (int)$priceData['price_qty']])
        );

        return $key;
    }

    /**
     * Prepare tier price data by provided price row data
     *
     * @param array $data
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareTierPrice(array $data): array
    {
        $useForAllGroups = (int)$data['cust_group'] === $this->groupManagement->getAllCustomersGroup()->getId();
        $customerGroupId = $useForAllGroups ? 0 : $data['cust_group'];
        $tierPrice = array_merge(
            $this->getAdditionalFields($data),
            $this->_getAdditionalFields($data),
            [
                'website_id' => $data['website_id'],
                'all_groups' => (int)$useForAllGroups,
                'customer_group_id' => $customerGroupId,
                'value' => $data['price'] ?? null,
                'qty' => (int)$data['price_qty']
            ]
        );

        return $tierPrice;
    }

    /**
     * Check by id is website global.
     *
     * @param int $websiteId
     * @return bool
     */
    private function isWebsiteGlobal(int $websiteId): bool
    {
        return $websiteId === 0;
    }

    /**
     * Prepare old data to compare.
     *
     * @param array|null $origPrices
     * @return array
     */
    private function prepareOldTierPriceToCompare($origPrices): array
    {
        $old = [];
        if (is_array($origPrices)) {
            foreach ($origPrices as $data) {
                $key = $this->getPriceKey($data);
                $old[$key] = $data;
            }
        }

        return $old;
    }

    /**
     * Prepare new data for save.
     *
     * @param array $priceRows
     * @param bool $isGlobal
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function prepareNewDataForSave($priceRows, $isGlobal = true): array
    {
        $new = [];
        $priceRows = array_filter($priceRows);
        foreach ($priceRows as $data) {
            if (empty($data['delete'])
                && (!empty($data['price_qty'])
                    || isset($data['cust_group'])
                    || $isGlobal === $this->isWebsiteGlobal((int)$data['website_id']))
            ) {
                $key = $this->getPriceKey($data);
                $new[$key] = $this->prepareTierPrice($data);
            }
        }

        return $new;
    }

    /**
     * @return array
     */
    protected function getAdditionalFieldNames() :array {
        return ['currency'];
    }

    /**
     * @return array
     */
    protected function getAdditionalUniqueFieldNames() :array {
        return ['currency'];
    }

    /**
     * Returns a list of values from the $data array where they are part of this tierPrice.
     * @param $data
     *
     * @return array
     */
    protected function _getAdditionalFields($data) :array {
        return $this->copyFields($data, $this->getAdditionalFieldNames());
    }

    /**
     * Returns a list of values from the $data array when they are part of the unique key for this tierPrice.
     * @param $data
     *
     * @return array
     */
    protected function _getAdditionalUniqueFields($data) :array {
        return $this->copyFields($data, $this->getAdditionalUniqueFieldNames());
    }

    /**
     * @param $data
     * @param $fieldNames
     *
     * @return array
     */
    private function copyFields($data, $fieldNames) :array {
        $result = [];
        foreach ($fieldNames as $fieldName) {
            if (isset($data[$fieldName])) {
                $result[$fieldName] = $data[$fieldName];
            }
        }
        return $result;
    }

    /**
     * Checks whether any values have been updated.
     * @param $valuesToUpdate
     * @param $oldValues
     *
     * @return bool
     */
    protected function _valuesUpdated($valuesToUpdate, $oldValues): bool
    {
        foreach ($this->getAdditionalFieldNames() as $fieldName) {
            if (($valuesToUpdate[$fieldName] ?? null) !== ($oldValues[$fieldName] ?? null)) {
                return true;
            }
        }
        return false;
    }
}

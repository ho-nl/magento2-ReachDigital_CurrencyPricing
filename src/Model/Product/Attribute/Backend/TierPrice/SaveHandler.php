<?php
/**
 * Copyright © Reach Digital (https://www.reachdigital.io/)
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Model\Product\Attribute\Backend\TierPrice;

use Magento\Framework\EntityManager\Operation\ExtensionInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice;

class SaveHandler extends \Magento\Catalog\Model\Product\Attribute\Backend\TierPrice\SaveHandler
{
    /**
     * Get additional tier price fields
     *
     * @param array $objectArray
     * @return array
     */
    protected function getAdditionalFields(array $objectArray): array
    {
        return array_merge($this->_getAdditionalFields($objectArray),
            parent::getAdditionalFields($objectArray)
        );
    }

    /**
     * @return array
     */
    protected function getAdditionalFieldNames() :array {
        return ['currency', 'is_special'];
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
}

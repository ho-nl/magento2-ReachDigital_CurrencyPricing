<?php

namespace ReachDigital\CurrencyPricing\Model\Product\Attribute\Backend;

use Magento\Catalog\Model\Product\Attribute\Backend\Tierprice;
use Magento\Framework\Phrase;

class TierpriceWithCurrency extends Tierprice
{
    /**
     * Add currency to unique fields
     *
     * @param array $objectArray
     * @return array
     */
    protected function _getAdditionalUniqueFields($objectArray) :array
    {
        $uniqueFields = parent::_getAdditionalUniqueFields($objectArray);
        $uniqueFields['currency'] = $objectArray['currency'];
        return $uniqueFields;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalFields($objectArray) :array
    {
        return [
            'currency' => $objectArray['currency'] ?? null
        ];
    }

    /**
     * Error message when duplicates
     *
     * @return Phrase
     */
    protected function _getDuplicateErrorMessage() :Phrase
    {
        return __('We found a duplicate website, tier price, customer group, currency and quantity.');
    }

    /**
     * @param array $valuesToUpdate
     * @param array $oldValues
     * @return bool
     */
    protected function updateValues(array $valuesToUpdate, array $oldValues) :bool
    {
        $isChanged = false;
        foreach ($valuesToUpdate as $key => $value) {
            if (!empty($value['currency']) && $oldValues[$key]['currency'] !== $value['currency']) {
                $currency = new \Magento\Framework\DataObject(
                    [
                        'value_id' => $oldValues[$key]['price_id'],
                        'currency' => $value['currency'],
                    ]
                );
                $this->_getResource()->savePriceData($currency);

                $isChanged = true;
            }
        }
        return $isChanged;
    }
}

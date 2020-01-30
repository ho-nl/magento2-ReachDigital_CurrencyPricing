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
        if (isset($objectArray['currency'])) {
            $uniqueFields['currency'] = $objectArray['currency'];
        }
        if (isset($objectArray['is_special'])) {
            $uniqueFields['is_special'] = $objectArray['is_special'];
        }
        return $uniqueFields;
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalFields($objectArray) :array
    {
        return [
            'currency' => $objectArray['currency'] ?? null,
            'is_special' => $objectArray['is_special']
        ];
    }

    /**
     * Error message when duplicates
     *
     * @return Phrase
     */
    protected function _getDuplicateErrorMessage() :Phrase
    {
        return __('We found a duplicate website, tier price, customer group, currency, is_special and quantity.');
    }

    /**
     * Update Price values in DB
     *
     * Updates price values in DB from array comparing to old values. Returns bool if updated
     *
     * @param array $valuesToUpdate
     * @param array $oldValues
     * @return bool
     */
    protected function updateValues(array $valuesToUpdate, array $oldValues) :bool
    {
        $isChanged = false;
        foreach ($valuesToUpdate as $key => $value) {
            if ((!empty($value['currency']) && $oldValues[$key]['currency'] !== $value['currency'])
                    || (!empty($value['is_special']) && $oldValues[$key]['is_special'] !== $value['is_special'])) {
                $currency = new \Magento\Framework\DataObject(
                    [
                        'value_id' => $oldValues[$key]['price_id'],
                        'currency' => $value['currency'],
                        'is_special' => $value['is_special']
                    ]
                );
                $this->_getResource()->savePriceData($currency);

                $isChanged = true;
            }
        }
        return $isChanged;
    }
}

<?php

namespace ReachDigital\CurrencyPricing\Model\ResourceModel;

use Magento\Framework\DataObject;

class CurrencyPrice extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize connection and define main table
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('catalog_product_entity_currency_price', 'currency_price_id');
    }

    /**
     * Load Tier Prices for product
     *
     * @param int $productId
     * @return array
     */
    public function loadPriceData($productId, $type): array
    {
        $select = $this->getSelect();
        $select->where('entity_id = ?', $productId);
        $select->where('type = ?', $type);

        return $this->getConnection()->fetchAll($select);
    }

    /**
     * @return \Magento\Framework\DB\Select
     */
    public function getSelect()
    {
        $columns = [
            'currency_price_id' => 'currency_price_id',
            'currency' => 'currency',
            'price' => 'price',
            'type' => 'type',
            'entity_id' => 'entity_id',
        ];

        $select = $this->getConnection()->select()
            ->from($this->getMainTable(), $columns);

        return $select;
    }

    /**
     * Save currency price object
     *
     * @param DataObject $priceObject
     *
     * @return CurrencyPrice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function savePriceData(DataObject $priceObject)
    {
        $connection = $this->getConnection();
        $data = $this->_prepareDataForTable($priceObject, $this->getMainTable());

        if ($priceObject->getData('price') === '' && !empty($data[$this->getIdFieldName()])) {
            $where = $connection->quoteInto($this->getIdFieldName() . ' = ?', $data[$this->getIdFieldName()]);
            unset($data[$this->getIdFieldName()]);
            $connection->delete($this->getMainTable(), $where);
        } elseif (!empty($data[$this->getIdFieldName()])) {
            $where = $connection->quoteInto($this->getIdFieldName() . ' = ?', $data[$this->getIdFieldName()]);
            unset($data[$this->getIdFieldName()]);
            $connection->update($this->getMainTable(), $data, $where);
        } else {
            $connection->insert($this->getMainTable(), $data);
        }
        return $this;
    }
}

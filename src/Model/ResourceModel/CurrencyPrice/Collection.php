<?php
namespace ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init('ReachDigital\CurrencyPricing\Model\CurrencyPrice', 'ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice');
    }
}

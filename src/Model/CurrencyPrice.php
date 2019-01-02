<?php

namespace ReachDigital\CurrencyPricing\Model;

class CurrencyPrice extends \Magento\Framework\Model\AbstractModel
{
    protected function _construct()
    {
        $this->_init('ReachDigital\CurrencyPricing\Model\ResourceModel\CurrencyPrice');
    }

}

<?php

namespace ReachDigital\CurrencyPricing\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\Store;

class AddToCartAfter implements ObserverInterface
{

    /**
     * @var Store
     */
    private $store;

    /**
     * AddToCartAfter constructor.
     *
     * @param Store $store
     */
    function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function execute(Observer $observer)
    {
        $quoteItem = $observer->getQuoteItem();
        $currentCurrencyCode = $this->store->getCurrentCurrencyCode();
        $quoteItem->setCurrency($currentCurrencyCode);
    }
}

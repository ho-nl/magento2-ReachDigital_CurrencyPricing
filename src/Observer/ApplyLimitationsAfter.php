<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Observer;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

class ApplyLimitationsAfter implements ObserverInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(ResourceConnection $resourceConnection, StoreManagerInterface $storeManager)
    {
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $connection = $this->resourceConnection->getConnection();
        $result = $observer->getCollection();
        $fromPart = $result->getSelect()->getPart(Select::FROM);
        if (!isset($fromPart['price_index']['joinCondition'])) {
            return;
        }
        $priceIndexFromStatement = $fromPart['price_index']['joinCondition'];
        if (\strpos($priceIndexFromStatement, 'price_index.currency') === false) {
            $priceIndexFromStatement = join(' AND ', [
                $priceIndexFromStatement,
                $connection->quoteInto(
                    'price_index.currency = ?',
                    $this->storeManager->getStore($result->getStoreId())->getCurrentCurrencyCode()
                ),
                $connection->quoteInto('price_index.storeview_id = ?', $result->getStoreId()),
            ]);
            $fromPart['price_index']['joinCondition'] = $priceIndexFromStatement;
            $result->getSelect()->setPart(Select::FROM, $fromPart);
        }
    }
}

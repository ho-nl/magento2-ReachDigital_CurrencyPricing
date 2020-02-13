<?php
declare(strict_types=1);

namespace ReachDigital\CurrencyPricing\Model\ResourceModel\Product;

use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Indexer\Product\Price\PriceTableResolver;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\Collection\ProductLimitationFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Indexer\DimensionFactory;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Magento\Customer\Model\Indexer\CustomerGroupDimensionProvider;
use Magento\Framework\App\ObjectManager;
use Magento\Catalog\Model\ResourceModel\Category;

class CollectionWithCurrency extends Collection
{

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PriceTableResolver|null
     */
    private $priceTableResolver;

    /**
     * @var DimensionFactory|null
     */
    private $dimensionFactory;

    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactory $entityFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Data\Collection\Db\FetchStrategyInterface $fetchStrategy,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Eav\Model\Config $eavConfig,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Eav\Model\EntityFactory $eavEntityFactory,
        \Magento\Catalog\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Validator\UniversalFactory $universalFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $catalogProductFlatState,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Catalog\Model\Product\OptionFactory $productOptionFactory,
        \Magento\Catalog\Model\ResourceModel\Url $catalogUrl,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        GroupManagementInterface $groupManagement,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection = null,
        ProductLimitationFactory $productLimitationFactory = null,
        MetadataPool $metadataPool = null,
        TableMaintainer $tableMaintainer = null,
        PriceTableResolver $priceTableResolver = null,
        DimensionFactory $dimensionFactory = null,
        Category $categoryResourceModel = null
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $eavConfig, $resource,
            $eavEntityFactory, $resourceHelper, $universalFactory, $storeManager, $moduleManager,
            $catalogProductFlatState, $scopeConfig, $productOptionFactory, $catalogUrl, $localeDate, $customerSession,
            $dateTime, $groupManagement, $connection, $productLimitationFactory, $metadataPool, $tableMaintainer,
            $priceTableResolver, $dimensionFactory, $categoryResourceModel);
        $this->storeManager = $storeManager;
        $this->dimensionFactory = $dimensionFactory
            ?: ObjectManager::getInstance()->get(DimensionFactory::class);
        $this->priceTableResolver = $priceTableResolver ?: ObjectManager::getInstance()->get(PriceTableResolver::class);
    }

    /**
     * Join Product Price Table with left-join possibility
     *
     * @see \Magento\Catalog\Model\ResourceModel\Product\Collection::_productLimitationJoinPrice()
     * @param bool $joinLeft
     * @return $this
     */
    protected function _productLimitationPrice($joinLeft = false)
    {
        $filters = $this->_productLimitationFilters;
        if (!$filters->isUsingPriceIndex() ||
            !isset($filters['website_id']) ||
            (string)$filters['website_id'] === '' ||
            !isset($filters['customer_group_id']) ||
            (string)$filters['customer_group_id'] === ''
        ) {
            return $this;
        }

        // Preventing overriding price loaded from EAV because we want to use the one from index
        $this->removeAttributeToSelect('price');

        $connection = $this->getConnection();
        $select = $this->getSelect();
        $joinCond = join(
            ' AND ',
            [
                'price_index.entity_id = e.entity_id',
                $connection->quoteInto('price_index.website_id = ?', $filters['website_id']),
                $connection->quoteInto('price_index.customer_group_id = ?', $filters['customer_group_id']),
                $connection->quoteInto('price_index.currency = ?', $this->storeManager->getStore($this->getStoreId())->getCurrentCurrencyCode())
            ]
        );

        $fromPart = $select->getPart(\Magento\Framework\DB\Select::FROM);
        if (!isset($fromPart['price_index'])) {
            $least = $connection->getLeastSql(['price_index.min_price', 'price_index.tier_price']);
            $minimalExpr = $connection->getCheckSql(
                'price_index.tier_price IS NOT NULL',
                $least,
                'price_index.min_price'
            );
            $colls = [
                'price',
                'tax_class_id',
                'final_price',
                'minimal_price' => $minimalExpr,
                'min_price',
                'max_price',
                'tier_price',
            ];

            $tableName = [
                'price_index' => $this->priceTableResolver->resolve(
                    'catalog_product_index_price',
                    [
                        $this->dimensionFactory->create(
                            CustomerGroupDimensionProvider::DIMENSION_NAME,
                            (string)$filters['customer_group_id']
                        ),
                        $this->dimensionFactory->create(
                            WebsiteDimensionProvider::DIMENSION_NAME,
                            (string)$filters['website_id']
                        )
                    ]
                )
            ];

            if ($joinLeft) {
                $select->joinLeft($tableName, $joinCond, $colls);
            } else {
                $select->join($tableName, $joinCond, $colls);
            }
            // Set additional field filters
            foreach ($this->_priceDataFieldFilters as $filterData) {
                $select->where(sprintf(...$filterData));
            }
        } else {
            $fromPart['price_index']['joinCondition'] = $joinCond;
            $select->setPart(\Magento\Framework\DB\Select::FROM, $fromPart);
        }
        //Clean duplicated fields
        $this->_resourceHelper->prepareColumnsList($select);

        return $this;
    }
}

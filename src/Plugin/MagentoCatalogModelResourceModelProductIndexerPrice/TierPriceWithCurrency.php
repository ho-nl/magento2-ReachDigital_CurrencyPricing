<?php
namespace ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType;

use Magento\Catalog\Model\ResourceModel\Product\Indexer\Price\TierPrice;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\Backend\Tierprice as TierPriceResourceModel;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\DB\Select;

class TierPriceWithCurrency
{
    /**
     * @var TierPriceResourceModel
     */
    private $tierPriceResourceModel;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @param TierPriceResourceModel $tierPriceResourceModel
     * @param MetadataPool $metadataPool
     * @param ProductAttributeRepositoryInterface $attributeRepository
     */
    public function __construct(
        TierPriceResourceModel $tierPriceResourceModel,
        MetadataPool $metadataPool,
        ProductAttributeRepositoryInterface $attributeRepository
    ) {
        $this->tierPriceResourceModel = $tierPriceResourceModel;
        $this->metadataPool = $metadataPool;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @param TierPrice $subject
     * @param \Closure  $proceed
     * @param array     $entityIds
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function aroundReindexEntity(TierPrice $subject, \Closure $proceed, array $entityIds = []): void {
        $subject->getConnection()->delete($subject->getMainTable(), ['entity_id IN (?)' => $entityIds]);

        //separate by variations for increase performance
        $tierPriceVariations = [
            [true, true], //all websites; all customer groups
            [true, false], //all websites; specific customer group
            [false, true], //specific website; all customer groups
            [false, false], //specific website; specific customer group
        ];
        foreach ($tierPriceVariations as $variation) {
            list ($isAllWebsites, $isAllCustomerGroups) = $variation;
            $select = $this->getTierPriceSelect($subject, $isAllWebsites, $isAllCustomerGroups, $entityIds);
            $query = $select->insertFromSelect($subject->getMainTable());
            $subject->getConnection()->query($query);
        }
    }

    /**
     * Join websites table.
     * If $isAllWebsites is true, for each website will be used default value for all websites,
     * otherwise per each website will be used their own values.
     *
     * @param TierPrice $subject
     * @param Select    $select
     * @param bool      $isAllWebsites
     */
    private function joinWebsites(TierPrice $subject, Select $select, bool $isAllWebsites): void
    {
        $websiteTable = ['website' => $subject->getTable('store_website')];
        if ($isAllWebsites) {
            $select->joinCross($websiteTable, [])
                ->where('website.website_id > ?', 0)
                ->where('tier_price.website_id = ?', 0);
        } else {
            $select->join($websiteTable, 'website.website_id = tier_price.website_id', [])
                ->where('tier_price.website_id > 0');
        }
    }

    /**
     * Join customer groups table.
     * If $isAllCustomerGroups is true, for each customer group will be used default value for all customer groups,
     * otherwise per each customer group will be used their own values.
     *
     * @param TierPrice $subject
     * @param Select    $select
     * @param bool      $isAllCustomerGroups
     */
    private function joinCustomerGroups(TierPrice $subject, Select $select, bool $isAllCustomerGroups): void
    {
        $customerGroupTable = ['customer_group' => $subject->getTable('customer_group')];
        if ($isAllCustomerGroups) {
            $select->joinCross($customerGroupTable, [])
                ->where('tier_price.all_groups = ?', 1)
                ->where('tier_price.customer_group_id = ?', 0);
        } else {
            $select->join($customerGroupTable, 'customer_group.customer_group_id = tier_price.customer_group_id', [])
                ->where('tier_price.all_groups = ?', 0);
        }
    }

    /**
     * Join price table and return price value.
     *
     * @param TierPrice        $subject
     * @param Select           $select
     * @param string           $linkField
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function joinPrice(TierPrice $subject, Select $select, string $linkField): string
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute $priceAttribute */
        $priceAttribute = $this->attributeRepository->get('price');
        $select->joinLeft(
            ['entity_price_default' => $priceAttribute->getBackend()->getTable()],
            'entity_price_default.' . $linkField . ' = entity.' . $linkField
            . ' AND entity_price_default.attribute_id = ' . $priceAttribute->getAttributeId()
            . ' AND entity_price_default.store_id = 0',
            []
        );
        $priceValue = 'entity_price_default.value';

        if (!$priceAttribute->isScopeGlobal()) {
            $select->joinLeft(
                ['store_group' => $subject->getTable('store_group')],
                'store_group.group_id = website.default_group_id',
                []
            )->joinLeft(
                ['entity_price_store' => $priceAttribute->getBackend()->getTable()],
                'entity_price_store.' . $linkField . ' = entity.' . $linkField
                . ' AND entity_price_store.attribute_id = ' . $priceAttribute->getAttributeId()
                . ' AND entity_price_store.store_id = store_group.default_store_id',
                []
            );
            $priceValue = $subject->getConnection()
                ->getIfNullSql('entity_price_store.value', 'entity_price_default.value');
        }

        return (string) $priceValue;
    }

    /**
     * Build select for getting tier price data.
     *
     * @param TierPrice        $subject
     * @param bool             $isAllWebsites
     * @param bool             $isAllCustomerGroups
     * @param array            $entityIds
     *
     * @return Select
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Exception
     */
    private function getTierPriceSelect(TierPrice $subject, bool $isAllWebsites, bool $isAllCustomerGroups, array $entityIds = []): Select
    {
        $entityMetadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $entityMetadata->getLinkField();

        $select = $subject->getConnection()->select();
        $select->from(['tier_price' => $this->tierPriceResourceModel->getMainTable()], [])
            ->where('tier_price.qty = ?', 1);

        $select->join(
            ['entity' => $subject->getTable('catalog_product_entity')],
            "entity.{$linkField} = tier_price.{$linkField}",
            []
        );
        if (!empty($entityIds)) {
            $select->where('entity.entity_id IN (?)', $entityIds);
        }
        $this->joinWebsites($subject, $select, $isAllWebsites);
        $this->joinCustomerGroups($subject, $select, $isAllCustomerGroups);

        $priceValue = $this->joinPrice($subject, $select, $linkField);
        $tierPriceValue = 'tier_price.value';
        $tierPricePercentageValue = 'tier_price.percentage_value';
        $tierPriceValueExpr = $subject->getConnection()->getCheckSql(
            $tierPriceValue,
            $tierPriceValue,
            sprintf('(1 - %s / 100) * %s', $tierPricePercentageValue, $priceValue)
        );
        $select->columns(
            [
                'entity.entity_id',
                'customer_group.customer_group_id',
                'website.website_id',
                'tier_price' => $tierPriceValueExpr,
            ]
        );

        return $select;
    }
}

<?php
namespace ReachDigital\CurrencyPricing\Test\Integration\Plugin\MagentoCatalogModelProductType;

use Customweb\OPPCw\Model\Config\Source\Eps\Currency;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use ReachDigital\CurrencyPricing\Model\RealBaseCurrency\RealBaseCurrency;
use ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice;
use Magento\TestFramework\ObjectManager;

/**
 * Class CurrencyPricingPriceTest
 * @magentoAppArea adminhtml
 *
 * @package ReachDigital\CurrencyPricing\Test\Integration\Plugin\MagentoCatalogModelProductType
 */
class CurrencyPricingPriceTest extends TestCase
{
    public static function createProductCurrency(): void
    {
        include __DIR__ . '/../../_files/product_currency.php';
    }

    protected function setUp()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        // Override the Uri to prevent errors while running this fixture.
        $httpRequest = $objectManager->get(\Magento\Framework\App\Request\Http::class);
        $httpRequest->setUri('');

        /** @var Store $store */
        $store = $objectManager->get(Store::class);
        $store->setData('available_currency_codes', ['EUR', 'USD', 'GBP', 'KRW', 'MXN', 'AUD']);

        /** @var RealBaseCurrency $realBaseCurrency */
        $realBaseCurrency = $objectManager->get(RealBaseCurrency::class);
        $realBaseCurrency->getRealBaseCurrency()->setRates(['KRW' => 119.82, 'GBP' => 0.7807, 'MXN' => 18.9923, 'AUD' => 1.3886]);
    }

    protected function tearDown()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        /** @var Registry $registry */
        $registry = $objectManager->get(Registry::class);
        $registry->unregister('isSecureArea');
        $registry->register('isSecureArea', true);

        try {
            $productRepository->deleteById('fintest');
        } catch (NoSuchEntityException $e) {
        } catch (StateException $e) {
        }
    }

    private function getProduct() :Product
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);
        return $productRepository->get('fintest');
    }

    private function getStore() :Store
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Store $store */
        return $objectManager->get(Store::class);
    }

    private function getPrice() :Price
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Store $store */
        return $objectManager->get(Price::class);
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldReturnRegularPrice(): void
    {
        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();
        $store->setCurrentCurrencyCode('USD');
        $this->assertEquals(10, $price->getBasePrice($product));
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shoudlReturnCurrencyPriceGBP(): void
    {
        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();
        $store->setCurrentCurrencyCode('GBP');
        $this->assertEquals(7, $price->getBasePrice($product));
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shoudlApplyTierPriceForTwoProducts(): void
    {
        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();
        $store->setCurrentCurrencyCode('GBP');
        $this->assertEquals(6, $price->getBasePrice($product, 2));
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldUseTierPrice(): void
    {
        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();
        $store->setCurrentCurrencyCode('EUR');
        $this->assertEquals(7, $price->getBasePrice($product));
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldApplyCurrencyRate(): void
    {
        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('KRW');
        $this->assertEquals(1198.2, $price->getBasePrice($product));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldReturnCurrencyPriceDespiteLowerConvertedPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        $currencyPrice = $product->getData('currency_price');
        $currencyPrice['GBP'] = 8;
        $product->setData('currency_price', $currencyPrice);

        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setCurrentCurrencyCode('GBP');
        $this->assertEquals(8, $price->getBasePrice($product));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldModifyCurrencyPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        $currencyPrice = $product->getData('currency_price');
        $currencyPrice['GBP'] = 6;
        $product->setData('currency_price', $currencyPrice);

        $productRepository->save($product);

        $product = $this->getProduct();

        // Check that the currency price was indeed modified.
        $store->setCurrentCurrencyCode('GBP');
        $this->assertEquals(6, $price->getBasePrice($product));
        // Check that the other Currency price was not altered or removed.
        $store->setCurrentCurrencyCode('MXN');
        $this->assertEquals(180, $price->getBasePrice($product));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldDeleteCurrencyPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        $currencyPrice = $product->getData('currency_price');
        $currencyPrice['GBP'] = '';
        $product->setData('currency_price', $currencyPrice);

        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('GBP');
        $this->assertEquals(7.807, $price->getBasePrice($product));
        // Check that the other Currency price was not altered or removed.
        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('MXN');
        $this->assertEquals(180, $price->getBasePrice($product));
    }

    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldAddCurrencyPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        $currencyPrice = $product->getData('currency_price');
        $currencyPrice['KRW'] = 1200;
        $product->setData('currency_price', $currencyPrice);

        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('KRW');
        $this->assertEquals(1200, $price->getBasePrice($product));
        // Check that the other Currency price was not altered or removed.
        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('MXN');
        $this->assertEquals(180, $price->getBasePrice($product));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldModifyTierPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface[] $tierPrices */
        $tierPrices = $product->getTierPrices();
        /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface $tierPrice */
        foreach($tierPrices as $tierPrice) {
            if ($tierPrice['currency'] === 'EUR') {
                $tierPrice['value'] = 5;
            }
        }
        $product->setTierPrices($tierPrices);
        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setCurrentCurrencyCode('EUR');
        $this->assertEquals(5, $price->getBasePrice($product));
        // Check that the other tier price was not altered or removed.
        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('AUD');
        $this->assertEquals(13, $price->getBasePrice($product, 3));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldAddTierPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface[] $tierPrices */
        $tierPrices = $product->getTierPrices();
        /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory $tierPriceFactory */
        $tierPriceFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory::class);

        $tierPrices[] = $tierPriceFactory->create(
            [
                'data' => [
                    'customer_group_id' => \Magento\Customer\Model\Group::CUST_GROUP_ALL,
                    'qty' => 1,
                    'value' => 1000,
                    'currency' => 'KRW'
                ]
            ]
        );
        $product->setTierPrices($tierPrices);
        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('KRW');
        $this->assertEquals(1000, $price->getBasePrice($product));
        // Check that the other tier price was not altered or removed.
        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('AUD');
        $this->assertEquals(13, $price->getBasePrice($product, 3));
    }


    /**
     * @test
     *
     * @covers             \ReachDigital\CurrencyPricing\Plugin\MagentoCatalogModelProductType\CurrencyPricingPrice
     * @magentoAppIsolation enabled
     * @magentoDataFixture createProductCurrency
     * @throws \Exception
     */
    public function shouldRemoveTierPrice(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductRepository $productRepository */
        $productRepository = $objectManager->create(ProductRepository::class);

        $store = $this->getStore();
        $product = $this->getProduct();
        $price = $this->getPrice();

        /** @var \Magento\Catalog\Api\Data\ProductTierPriceInterface[] $tierPrices */
        $tierPrices = $product->getTierPrices();

        $newTierPrices = [];
        foreach ($tierPrices as $tierPrice) {
            if ($tierPrice['currency'] !== 'EUR') {
                $newTierPrices[] = $tierPrice;
            }
        }
        $product->setTierPrices($newTierPrices);
        $productRepository->save($product);

        $product = $this->getProduct();

        $store->setCurrentCurrencyCode('EUR');
        $this->assertEquals(12, $price->getBasePrice($product));
        // Check that the other tier price was not altered or removed.
        $store->setData('current_currency', null);
        $store->setCurrentCurrencyCode('AUD');
        $this->assertEquals(13, $price->getBasePrice($product, 3));
    }

}

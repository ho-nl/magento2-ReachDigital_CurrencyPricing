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
        /** @var Store $store */
        $store = $objectManager->get(Store::class);
        $store->setData('available_currency_codes', ['EUR', 'USD', 'GBP', 'KRW']);

        /** @var RealBaseCurrency $realBaseCurrency */
        $realBaseCurrency = $objectManager->get(RealBaseCurrency::class);
        $realBaseCurrency->getRealBaseCurrency()->setRates(['KRW' => 119.82, 'GBP' => 0.7807]);
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
        try {
            return $productRepository->get('fintest');
        } catch (NoSuchEntityException $e) {
            return null;
        }
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

        $store->setCurrentCurrencyCode('KRW');
        $this->assertEquals(1198.2, $price->getBasePrice($product));
    }

}

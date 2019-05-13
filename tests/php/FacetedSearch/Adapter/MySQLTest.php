<?php

namespace PrestaShop\Module\FacetedSearch\Tests\Adapter;

use stdClass;
use Db;
use Context;
use StockAvailable;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use Ps_FacetedSearch;

class MySQLTest extends TestCase
{
    private $adapter;

    protected function setUp()
    {
        require_once __DIR__ . '/../MockProxy.php';

        $this->adapter = new MySQL();

        $mock = $this->getMockBuilder('StockAvailable')
              ->setMethods(['addSqlShopRestriction'])
              ->getMock();
        $mock->expects($this->any())
            ->method('addSqlShopRestriction')
            ->with(null, null, 'sa')
            ->will($this->returnValue(''));

        StockAvailable::setStaticExpectations($mock);

        $stdClass = new stdClass();
        $stdClass->shop = new stdClass();
        $stdClass->shop->id = 1;
        $stdClass->language = new stdClass();
        $stdClass->language->id = 2;
        $stdClass->country = new stdClass();
        $stdClass->country->id = 3;
        $stdClass->currency = new stdClass();
        $stdClass->currency->id = 4;

        $contextMock = $this->getMockBuilder('Context')
              ->setMethods(['getContext'])
              ->getMock();
        $contextMock->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($stdClass));
        Context::setStaticExpectations($contextMock);
    }

    public function testGetEmptyQuery()
    {
        $this->assertEquals(
            'SELECT  FROM ps_product p WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    /**
     * @dataProvider oneSelectFieldDataProvider
     */
    public function testGetQueryWithOneSelectField($type, $expected)
    {
        $this->adapter->addSelectField($type);

        $this->assertEquals(
            $expected,
            $this->adapter->getQuery()
        );
    }

    public function testGetMinMaxPriceValue()
    {
        $dbInstanceMock = $this->getMockBuilder('Db')
                ->setMethods(['executeS'])
                ->getMock();
        $dbInstanceMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT psi.price_min, MIN(price_min) as min, MAX(price_max) as max FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.active = TRUE')
            ->will(
                $this->returnValue(
                    [
                        [
                            'price_min' => '11',
                            'min' => '11',
                            'max' => '35',
                        ]
                    ]
                )
            );

        $dbMock = $this->getMockBuilder('Db')
                ->setMethods(['getInstance'])
                ->getMock();

        $dbMock->expects($this->any())
            ->method('getInstance')
            ->will($this->returnValue($dbInstanceMock));

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [11.0, 35.0],
            $this->adapter->getMinMaxPriceValue()
        );
    }

    public function oneSelectFieldDataProvider()
    {
        return [
            ['id_product', 'SELECT p.id_product FROM ps_product p WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_product_attribute', 'SELECT pa.id_product_attribute FROM ps_product p STRAIGHT_JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_attribute', 'SELECT pac.id_attribute FROM ps_product p STRAIGHT_JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) STRAIGHT_JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_attribute_group', 'SELECT a.id_attribute_group FROM ps_product p STRAIGHT_JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) STRAIGHT_JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) STRAIGHT_JOIN ps_attribute a ON (a.id_attribute = pac.id_attribute) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_feature', 'SELECT fp.id_feature FROM ps_product p INNER JOIN ps_feature_product fp ON (p.id_product = fp.id_product) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_shop', 'SELECT ps.id_shop FROM ps_product p INNER JOIN ps_product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = 1) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_feature_value', 'SELECT fp.id_feature_value FROM ps_product p LEFT JOIN ps_feature_product fp ON (p.id_product = fp.id_product) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_cateogry', 'SELECT p.id_cateogry FROM ps_product p WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['position', 'SELECT cp.position FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['name', 'SELECT pl.name FROM ps_product p INNER JOIN ps_product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = 2) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['nleft', 'SELECT c.nleft FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['nright', 'SELECT c.nright FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['level_depth', 'SELECT c.level_depth FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['out_of_stock', 'SELECT sa.out_of_stock FROM ps_product p LEFT JOIN ps_stock_available sa ON (p.id_product=sa.id_product AND 0 = sa.id_product_attribute ) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['quantity', 'SELECT sa.quantity FROM ps_product p LEFT JOIN ps_stock_available sa ON (p.id_product=sa.id_product AND 0 = sa.id_product_attribute ) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['price_min', 'SELECT psi.price_min FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['price_max', 'SELECT psi.price_max FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['range_start', 'SELECT psi.range_start FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['range_end', 'SELECT psi.range_end FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_group', 'SELECT cg.id_group FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) LEFT JOIN ps_category_group cg ON (cg.id_category = c.id_category) WHERE p.active = TRUE ORDER BY p.id_product DESC LIMIT 0, 20'],
        ];
    }
}

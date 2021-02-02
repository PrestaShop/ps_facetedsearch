<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\FacetedSearch\Tests\Adapter;

use Configuration;
use Context;
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShop\Module\FacetedSearch\Product\Search;
use Product;
use stdClass;
use StockAvailable;

class MySQLTest extends MockeryTestCase
{
    private $adapter;

    protected function setUp()
    {
        $this->adapter = new MySQL();

        $mock = Mockery::mock(StockAvailable::class);
        $mock->shouldReceive('addSqlShopRestriction')
            ->with(null, null, 'sa')
            ->andReturn('');

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

        $contextMock = Mockery::mock(Context::class);
        $contextMock->shouldReceive('getContext')
            ->andReturn($stdClass);
        Context::setStaticExpectations($contextMock);

        $configurationMock = Mockery::mock(Configuration::class);
        $configurationMock->shouldReceive('get')
            ->with('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST')
            ->andReturn(0);
        Configuration::setStaticExpectations($configurationMock);
    }

    public function testGetEmptyQuery()
    {
        $this->assertEquals(
            'SELECT  FROM ps_product p ORDER BY p.id_product DESC LIMIT 0, 20',
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
        $dbInstanceMock = Mockery::mock(Db::class)->makePartial();
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT psi.price_min, MIN(price_min) as min, MAX(price_max) as max FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3)')
            ->andReturn(
                [
                    [
                        'price_min' => '11',
                        'min' => '11',
                        'max' => '35',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class)->makePartial();
        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [11.0, 35.0],
            $this->adapter->getMinMaxPriceValue()
        );
    }

    public function testGetMinMaxValueForWeight()
    {
        $dbInstanceMock = Mockery::mock(Db::class);
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT MIN(weight) as min, MAX(weight) as max FROM ps_product p')
            ->andReturn(
                [
                    [
                        'min' => '10',
                        'max' => '42',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [10.0, 42.0],
            $this->adapter->getMinMaxValue('weight')
        );
    }

    public function testCount()
    {
        $dbInstanceMock = Mockery::mock(Db::class);
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT COUNT(DISTINCT p.id_product) c FROM ps_product p')
            ->andReturn(
                [
                    [
                        'c' => '100',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class);

        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            100,
            $this->adapter->count()
        );
    }

    public function testValueCount()
    {
        $dbInstanceMock = Mockery::mock(Db::class);
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT p.weight, COUNT(DISTINCT p.id_product) c FROM ps_product p GROUP BY p.weight')
            ->andReturn(
                [
                    [
                        'weight' => '10',
                        'c' => '100',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [
                0 => [
                    'weight' => '10',
                    'c' => '100',
                ],
            ],
            $this->adapter->valueCount('weight')
        );
    }

    public function testValueCountWithInitialPopulation()
    {
        $this->adapter->useFiltersAsInitialPopulation();
        $dbInstanceMock = Mockery::mock(Db::class);
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT p.id_product, p.weight, COUNT(DISTINCT p.id_product) c FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product)) p GROUP BY p.weight')
            ->andReturn(
                [
                    [
                        'weight' => '10',
                        'c' => '1000',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [
                0 => [
                    'weight' => '10',
                    'c' => '1000',
                ],
            ],
            $this->adapter->valueCount('weight')
        );
    }

    public function testValueCountWithInitialPopulationAndStockManagement()
    {
        $this->adapter->useFiltersAsInitialPopulation();
        $this->adapter->getInitialPopulation()->addOperationsFilter(
            Search::STOCK_MANAGEMENT_FILTER,
            [[['quantity', [0], '>=']]]
        );

        $dbInstanceMock = Mockery::mock(Db::class);
        $dbInstanceMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT p.id_product, p.weight, COUNT(DISTINCT p.id_product) c FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product) WHERE ((sa.quantity>=0))) p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) WHERE ((sa.quantity>=0)) GROUP BY p.weight')
            ->andReturn(
                [
                    [
                        'weight' => '10',
                        'c' => '1000',
                    ],
                ]
            );

        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('getInstance')
            ->andReturn($dbInstanceMock);

        Db::setStaticExpectations($dbMock);
        $this->assertEquals(
            [
                0 => [
                    'weight' => '10',
                    'c' => '1000',
                ],
            ],
            $this->adapter->valueCount('weight')
        );
    }

    public function testGetQueryWithAllSelectField()
    {
        $this->adapter->setSelectFields(
            [
                'id_product',
                'id_product_attribute',
                'id_attribute',
                'id_attribute_group',
                'id_feature',
                'id_shop',
                'id_feature_çvalue',
                'id_category',
                'name',
                'nleft',
                'nright',
                'level_depth',
                'out_of_stock',
                'quantity',
                'price_min',
                'price_max',
                'range_start',
                'range_end',
                'id_group',
                'manufacturer_name',
            ]
        );

        $this->assertEquals(
            'SELECT p.id_product, pa.id_product_attribute, pac.id_attribute, a.id_attribute_group, fp.id_feature, ps.id_shop, p.id_feature_çvalue, cp.id_category, pl.name, c.nleft, c.nright, c.level_depth, sa.out_of_stock, SUM(sa.quantity) as quantity, psi.price_min, psi.price_max, psi.range_start, psi.range_end, cg.id_group, m.name FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) INNER JOIN ps_attribute a ON (a.id_attribute = pac.id_attribute) INNER JOIN ps_feature_product fp ON (p.id_product = fp.id_product) INNER JOIN ps_product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = 1 AND ps.active = TRUE) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = 2) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) LEFT JOIN ps_category_group cg ON (cg.id_category = c.id_category) INNER JOIN ps_manufacturer m ON (p.id_manufacturer = m.id_manufacturer) ORDER BY p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithManyFilters()
    {
        $this->adapter->setSelectFields(
            [
                'id_product',
                'out_of_stock',
                'quantity',
                'price_min',
                'price_max',
                'range_start',
                'range_end',
            ]
        );

        $this->adapter->addFilter('condition', ['new', 'used'], '=');
        $this->adapter->addFilter('weight', [10], '=');
        $this->adapter->addFilter('price_min', [10], '>=');
        $this->adapter->addFilter('price_min', [100], '<=');
        $this->adapter->addFilter('id_product', [2, 20, 200], '=');

        $this->assertEquals(
            'SELECT p.id_product, sa.out_of_stock, SUM(sa.quantity) as quantity, psi.price_min, psi.price_max, psi.range_start, psi.range_end FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) WHERE p.condition IN (\'new\', \'used\') AND p.weight=\'10\' AND psi.price_min>=10 AND psi.price_min<=100 AND p.id_product IN (2, 20, 200) ORDER BY p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    /**
     * @dataProvider getManyOperationsFilters
     */
    public function testGetQueryWithManyOperationsFilters($fields, $operationsFilter, $expected)
    {
        $this->adapter->setSelectFields($fields);

        $this->adapter->addOperationsFilter(
            'out_of_stock_filter',
            $operationsFilter
        );

        $this->assertEquals(
            $expected,
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithGroup()
    {
        $this->adapter->addSelectField('id_product');
        $this->adapter->addGroupBy('id_product');
        $this->adapter->addGroupBy('id_feature_value');
        $this->adapter->addGroupBy('p.something_defined_by_me');

        $this->assertEquals(
            'SELECT p.id_product FROM ps_product p GROUP BY p.id_product, fp.id_feature_value, p.something_defined_by_me ORDER BY p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithPriceOrderFieldInDesc()
    {
        $this->adapter->addSelectField('id_product');
        $this->adapter->setOrderField('price');

        $this->assertEquals(
            'SELECT p.id_product FROM ps_product p ORDER BY psi.price_max DESC, p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithPriceOrderFieldInAsc()
    {
        $this->adapter->addSelectField('id_product');
        $this->adapter->setOrderField('price');
        $this->adapter->setOrderDirection('asc');

        $this->assertEquals(
            'SELECT p.id_product FROM ps_product p ORDER BY psi.price_min ASC, p.id_product DESC LIMIT 0, 20',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithPriceOrderFieldInAscWithInitialPopulation()
    {
        $this->adapter->addSelectField('manufacturer_name');
        $this->adapter->useFiltersAsInitialPopulation();
        $this->adapter->setOrderField('manufacturer_name');
        $this->adapter->setOrderDirection('asc');

        $this->assertEquals(
            'SELECT p.id_product FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales, m.name FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product) INNER JOIN ps_manufacturer m ON (p.id_manufacturer = m.id_manufacturer)) p INNER JOIN ps_manufacturer m ON (p.id_manufacturer = m.id_manufacturer) ORDER BY m.name ASC, p.id_product DESC',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithPositionOrderFieldInAscWithInitialPopulation()
    {
        $this->adapter->addSelectField('id_product');
        $this->adapter->useFiltersAsInitialPopulation();
        $this->adapter->setOrderField('position');
        $this->adapter->setOrderDirection('desc');

        $this->assertEquals(
            'SELECT p.id_product FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales, cp.position FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product)) p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) ORDER BY p.position DESC, p.id_product DESC',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithComputeShowLastEnabled()
    {
        $configurationMock = Mockery::mock(Configuration::class);
        $configurationMock->shouldReceive('get')
            ->with('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST')
            ->andReturn(true);
        Configuration::setStaticExpectations($configurationMock);

        $productMock = Mockery::namedMock(Product::class);
        $productMock->shouldReceive('isAvailableWhenOutOfStock')
            ->with(2)
            ->andReturn(true);

        $this->adapter->addSelectField('id_product');
        $this->adapter->useFiltersAsInitialPopulation();
        $this->adapter->setOrderField('position');
        $this->adapter->setOrderDirection('desc');

        $this->assertEquals(
            'SELECT p.id_product, sa.out_of_stock FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales, cp.position FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product)) p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) ORDER BY IFNULL(p.quantity, 0) <= 0, IFNULL(p.quantity, 0) <= 0 AND FIELD(sa.out_of_stock, 0) ASC, p.position DESC, p.id_product DESC',
            $this->adapter->getQuery()
        );
    }

    public function testGetQueryWithComputeShowLastEnabledAndDenyOrderOutOfStockProducts()
    {
        $configurationMock = Mockery::mock(Configuration::class);
        $configurationMock->shouldReceive('get')
            ->with('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST')
            ->andReturn(true);
        Configuration::setStaticExpectations($configurationMock);

        $productMock = Mockery::namedMock(Product::class);
        $productMock->shouldReceive('isAvailableWhenOutOfStock')
            ->with(2)
            ->andReturn(false);

        $this->adapter->addSelectField('id_product');
        $this->adapter->useFiltersAsInitialPopulation();
        $this->adapter->setOrderField('position');
        $this->adapter->setOrderDirection('desc');

        $this->assertEquals(
            'SELECT p.id_product, sa.out_of_stock FROM (SELECT p.id_product, p.id_manufacturer, SUM(sa.quantity) as quantity, p.condition, p.weight, p.price, psales.quantity as sales, cp.position FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_sale psales ON (psales.id_product = p.id_product) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product)) p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) ORDER BY IFNULL(p.quantity, 0) <= 0, IFNULL(p.quantity, 0) <= 0 AND FIELD(sa.out_of_stock, 1) DESC, p.position DESC, p.id_product DESC',
            $this->adapter->getQuery()
        );
    }

    public function getManyOperationsFilters()
    {
        return [
            [
                'fields' => [
                    'id_product',
                    'out_of_stock',
                    'quantity',
                    'price_min',
                    'price_max',
                    'range_start',
                    'range_end',
                ],
                'operationsFilter' => [
                    [
                        ['quantity', [0], '>='],
                        ['out_of_stock', [1, 3, 4], '='],
                    ],
                    [
                        ['quantity', [0], '>'],
                        ['out_of_stock', [1], '='],
                    ],
                ],
                'expected' => 'SELECT p.id_product, sa.out_of_stock, SUM(sa.quantity) as quantity, psi.price_min, psi.price_max, psi.range_start, psi.range_end FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) LEFT JOIN ps_stock_available sa_1 ON (p.id_product = sa_1.id_product AND IFNULL(pac.id_product_attribute, 0) = sa_1.id_product_attribute) WHERE ((sa.quantity>=0 AND sa_1.out_of_stock IN (1, 3, 4)) OR (sa.quantity>0 AND sa_1.out_of_stock=1)) ORDER BY p.id_product DESC LIMIT 0, 20',
            ],
            [
                'fields' => [
                    'id_product',
                    'quantity',
                ],
                'operationsFilter' => [
                    [
                        ['id_attribute', [2]],
                        ['id_attribute', [4]],
                    ],
                    [
                        ['quantity', [0], '>'],
                        ['out_of_stock', [1], '='],
                    ],
                ],
                'expected' => 'SELECT p.id_product, SUM(sa.quantity) as quantity FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_attribute_combination pac_1 ON (pa.id_product_attribute = pac_1.id_product_attribute) LEFT JOIN ps_stock_available sa_1 ON (p.id_product = sa_1.id_product AND IFNULL(pac.id_product_attribute, 0) = sa_1.id_product_attribute) WHERE ((pac.id_attribute=2 AND pac_1.id_attribute=4) OR (sa.quantity>0 AND sa_1.out_of_stock=1)) ORDER BY p.id_product DESC LIMIT 0, 20',
            ],
            [
                'fields' => [
                    'id_product',
                    'quantity',
                ],
                'operationsFilter' => [
                    [
                        ['id_attribute', [2]],
                        ['id_attribute', [4, 5, 6]],
                        ['id_attribute', [7, 8, 9]],
                    ],
                    [
                        ['quantity', [0], '>'],
                        ['out_of_stock', [0], '='],
                    ],
                ],
                'expected' => 'SELECT p.id_product, SUM(sa.quantity) as quantity FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_product_attribute_combination pac_1 ON (pa.id_product_attribute = pac_1.id_product_attribute) LEFT JOIN ps_product_attribute_combination pac_2 ON (pa.id_product_attribute = pac_2.id_product_attribute) LEFT JOIN ps_stock_available sa_1 ON (p.id_product = sa_1.id_product AND IFNULL(pac.id_product_attribute, 0) = sa_1.id_product_attribute) WHERE ((pac.id_attribute=2 AND pac_1.id_attribute IN (4, 5, 6) AND pac_2.id_attribute IN (7, 8, 9)) OR (sa.quantity>0 AND sa_1.out_of_stock=0)) ORDER BY p.id_product DESC LIMIT 0, 20',
            ],
        ];
    }

    public function oneSelectFieldDataProvider()
    {
        return [
            ['id_product', 'SELECT p.id_product FROM ps_product p ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_product_attribute', 'SELECT pa.id_product_attribute FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_attribute', 'SELECT pac.id_attribute FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_attribute_group', 'SELECT a.id_attribute_group FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) INNER JOIN ps_attribute a ON (a.id_attribute = pac.id_attribute) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_feature', 'SELECT fp.id_feature FROM ps_product p INNER JOIN ps_feature_product fp ON (p.id_product = fp.id_product) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_shop', 'SELECT ps.id_shop FROM ps_product p INNER JOIN ps_product_shop ps ON (p.id_product = ps.id_product AND ps.id_shop = 1 AND ps.active = TRUE) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_feature_value', 'SELECT fp.id_feature_value FROM ps_product p LEFT JOIN ps_feature_product fp ON (p.id_product = fp.id_product) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_category', 'SELECT cp.id_category FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['position', 'SELECT cp.position FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['name', 'SELECT pl.name FROM ps_product p INNER JOIN ps_product_lang pl ON (p.id_product = pl.id_product AND pl.id_shop = 1 AND pl.id_lang = 2) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['nleft', 'SELECT c.nleft FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['nright', 'SELECT c.nright FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['level_depth', 'SELECT c.level_depth FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['out_of_stock', 'SELECT sa.out_of_stock FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['quantity', 'SELECT SUM(sa.quantity) as quantity FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['price_min', 'SELECT psi.price_min FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['price_max', 'SELECT psi.price_max FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['range_start', 'SELECT psi.range_start FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['range_end', 'SELECT psi.range_end FROM ps_product p INNER JOIN ps_layered_price_index psi ON (psi.id_product = p.id_product AND psi.id_shop = 1 AND psi.id_currency = 4 AND psi.id_country = 3) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['id_group', 'SELECT cg.id_group FROM ps_product p INNER JOIN ps_category_product cp ON (p.id_product = cp.id_product) INNER JOIN ps_category c ON (cp.id_category = c.id_category AND c.active=1) LEFT JOIN ps_category_group cg ON (cg.id_category = c.id_category) ORDER BY p.id_product DESC LIMIT 0, 20'],
            ['manufacturer_name', 'SELECT m.name FROM ps_product p INNER JOIN ps_manufacturer m ON (p.id_manufacturer = m.id_manufacturer) ORDER BY p.id_product DESC LIMIT 0, 20'],
        ];
    }
}

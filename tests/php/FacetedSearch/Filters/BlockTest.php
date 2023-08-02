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

namespace PrestaShop\Module\FacetedSearch\Tests\Filters;

use Combination;
use Configuration;
use Context;
use Db;
use Group;
use Manufacturer;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShop\Module\FacetedSearch\Definition\Availability;
use PrestaShop\Module\FacetedSearch\Filters\Block;
use PrestaShop\Module\FacetedSearch\Filters\DataAccessor;
use PrestaShop\Module\FacetedSearch\Filters\Provider;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShopBundle\Translation\TranslatorComponent;
use Shop;
use stdClass;
use StockAvailable;

class BlockTest extends MockeryTestCase
{
    /** @var Context */
    private $contextMock;

    /** @var Shop */
    private $shopMock;

    /** @var Db */
    private $dbMock;

    /** @var Block */
    private $block;

    protected function setUp()
    {
        $mock = Mockery::mock(Configuration::class);

        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_HOME_CATEGORY' => 1,
                    'PS_WEIGHT_UNIT' => 'kg',
                    'PS_STOCK_MANAGEMENT' => '1',
                    'PS_ORDER_OUT_OF_STOCK' => '1',
                    'PS_UNIDENTIFIED_GROUP' => '1',
                    'PS_LAYERED_FILTER_CATEGORY_DEPTH' => 3,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        $this->contextMock = Mockery::mock(Context::class);
        $this->contextMock->shop = new stdClass();
        $this->contextMock->shop->id = 1;
        $this->contextMock->language = new stdClass();
        $this->contextMock->language->id = 2;
        $this->contextMock->country = new stdClass();
        $this->contextMock->country->id = 3;
        $this->contextMock->currency = new stdClass();
        $this->contextMock->currency->format = '##,##';
        $this->contextMock->currency->iso_code = 'EUR';
        $this->contextMock->currency->sign = '€';
        $this->contextMock->currency->id = 4;
        $this->contextMock->customer = new stdClass();
        $this->contextMock->customer->id_default_group = 5;

        $this->contextMock->shouldReceive('getContext')
            ->andReturn($this->contextMock);
        Context::setStaticExpectations($this->contextMock);

        $this->dbMock = Mockery::mock(Db::class);
        $dbMock = Mockery::mock(Db::class)->makePartial();
        $dbMock->shouldReceive('getInstance')
            ->andReturn($this->dbMock);

        Db::setStaticExpectations($dbMock);

        $mock = Mockery::mock(StockAvailable::class);
        $mock->shouldReceive('addSqlShopRestriction')
            ->with(null, null, 'sa')
            ->andReturn('');

        StockAvailable::setStaticExpectations($mock);

        $this->shopMock = Mockery::mock(Shop::class);
        Shop::setStaticExpectations($this->shopMock);

        $this->adapterMock = Mockery::mock(MySQL::class)->makePartial();
        $this->adapterMock->resetAll();

        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getIdCategory')
            ->andReturn(12);
        $query->shouldReceive('getQueryType')
            ->andReturn('category');

        $this->block = new Block(
            $this->adapterMock,
            $this->contextMock,
            $this->dbMock,
            new DataAccessor($this->dbMock),
            $query,
            new Provider($this->dbMock)
        );
    }

    public function testGetEmptyFiltersBlock()
    {
        $this->mockLayeredCategory([]);
        $this->assertEquals(
            ['filters' => []],
            $this->block->getFilterBlock(
                10,
                []
            )
        );
    }

    public function testGetFiltersBlockWithPriceDeactivated()
    {
        $group = new stdClass();
        $group->show_prices = false;

        $groupMock = Mockery::mock(Group::class);
        $groupMock->shouldReceive('getCurrent')
            ->once()
            ->andReturn($group);

        Group::setStaticExpectations($groupMock);

        $this->mockLayeredCategory([['type' => 'price']]);
        $this->assertEquals(
            ['filters' => [[]]],
            $this->block->getFilterBlock(
                10,
                [
                    'weight' => [
                        '14',
                        '40',
                    ],
                    'price' => [
                        '24',
                        '42',
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithPrice()
    {
        $group = new stdClass();
        $group->show_prices = true;

        $groupMock = Mockery::mock(Group::class);
        $groupMock->shouldReceive('getCurrent')
            ->once()
            ->andReturn($group);

        Group::setStaticExpectations($groupMock);

        $this->mockTranslator('Price', [], 'Modules.Facetedsearch.Shop', 'Price');
        $this->mockLayeredCategory([['type' => 'price', 'filter_show_limit' => false]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('getMinMaxPriceValue')
            ->andReturn([10.0, 100.0]);
        $this->adapterMock->shouldReceive('getInitialPopulation')
            ->andReturn($adapterInitialMock);
        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'price',
                        'type' => 'price',
                        'id_key' => 0,
                        'name' => 'Price',
                        'max' => 100.0,
                        'min' => 10.0,
                        'unit' => '€',
                        'specifications' => [
                            'positivePattern' => '##,##',
                            'negativePattern' => '##,##',
                            'symbol' => [
                                '.',
                                ',',
                                ';',
                                '%',
                                '-',
                                '+',
                                'E',
                                '×',
                                '‰',
                                '∞',
                                'NaN',
                            ],
                            'maxFractionDigits' => 2,
                            'minFractionDigits' => 2,
                            'groupingUsed' => true,
                            'primaryGroupSize' => 3,
                            'secondaryGroupSize' => 3,
                            'currencyCode' => 'EUR',
                            'currencySymbol' => '€',
                        ],
                        'filter_show_limit' => 0,
                        'filter_type' => 3,
                        'nbr' => 10,
                        'value' => [
                            '24',
                            '42',
                        ],
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'weight' => [
                        '14',
                        '40',
                    ],
                    'price' => [
                        '24',
                        '42',
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithWeight()
    {
        $this->mockTranslator('Weight', [], 'Modules.Facetedsearch.Shop', 'Weight');
        $this->mockLayeredCategory([['type' => 'weight', 'filter_show_limit' => false]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('getMinMaxValue')
            ->with('p.weight')
            ->andReturn([10.0, 100.0]);
        $this->adapterMock->shouldReceive('getInitialPopulation')
            ->andReturn($adapterInitialMock);
        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'weight',
                        'type' => 'weight',
                        'id_key' => 0,
                        'name' => 'Weight',
                        'max' => 100.0,
                        'min' => 10.0,
                        'unit' => 'kg',
                        'specifications' => null,
                        'filter_show_limit' => 0,
                        'filter_type' => 3,
                        'nbr' => 10,
                        'value' => [
                            '14',
                            '40',
                        ],
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'weight' => [
                        '14',
                        '40',
                    ],
                    'price' => [
                        '24',
                        '42',
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutWeight()
    {
        $this->mockTranslator('Weight', [], 'Modules.Facetedsearch.Shop', 'Weight');
        $this->mockLayeredCategory([['type' => 'weight', 'filter_show_limit' => false]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('getMinMaxValue')
            ->with('p.weight')
            ->andReturn([0, 0]);
        $this->adapterMock->shouldReceive('getInitialPopulation')
            ->andReturn($adapterInitialMock);
        $this->assertEquals(
            [
                'filters' => [
                    [],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'weight' => [
                        '14',
                        '40',
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithQuantities()
    {
        $this->mockTranslator([
            [['Availability', [], 'Modules.Facetedsearch.Shop'], 'Availability'],
            [['Not available', [], 'Modules.Facetedsearch.Shop'], 'Not available'],
            [['In stock', [], 'Modules.Facetedsearch.Shop'], 'In stock'],
            [['Available', [], 'Modules.Facetedsearch.Shop'], 'Available'],
        ]);
        $this->mockLayeredCategory([['type' => 'availability', 'filter_show_limit' => 0, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT COUNT(DISTINCT p.id_product) c FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) LEFT JOIN ps_stock_available sa_1 ON (p.id_product = sa_1.id_product AND IFNULL(pac.id_product_attribute, 0) = sa_1.id_product_attribute) WHERE ((sa.quantity<=0 AND sa_1.out_of_stock=0))')
            ->andReturn([
                ['c' => 1000],
            ]);

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT COUNT(DISTINCT p.id_product) c FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) WHERE ((sa.out_of_stock IN (1, 2)) OR (sa.quantity>0))')
            ->andReturn([
                ['c' => 100],
            ]);

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT COUNT(DISTINCT p.id_product) c FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) LEFT JOIN ps_stock_available sa ON (p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute) WHERE ((sa.quantity>0))')
            ->andReturn([
                ['c' => 50],
            ]);

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('quantity')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'availability',
                        'type' => 'availability',
                        'id_key' => 0,
                        'name' => 'Availability',
                        'values' => [
                            Availability::IN_STOCK => [
                                'name' => 'In stock',
                                'nbr' => 50,
                            ],
                            Availability::AVAILABLE => [
                                'name' => 'Available',
                                'nbr' => 100,
                                'checked' => true,
                            ],
                            Availability::NOT_AVAILABLE => [
                                'name' => 'Not available',
                                'nbr' => 1000,
                            ],
                        ],
                        'filter_show_limit' => 0,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'availability' => [
                        Availability::AVAILABLE,
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithCondition()
    {
        $this->mockTranslator([
            [['New', [], 'Modules.Facetedsearch.Shop'], 'New'],
            [['Used', [], 'Modules.Facetedsearch.Shop'], 'Used'],
            [['Refurbished', [], 'Modules.Facetedsearch.Shop'], 'Refurbished'],
            [['Condition', [], 'Modules.Facetedsearch.Shop'], 'Condition'],
        ]);
        $this->mockLayeredCategory([['type' => 'condition', 'filter_show_limit' => 0, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('valueCount')
            ->with('condition')
            ->andReturn([['c' => 100, 'condition' => 'new']]);
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('condition')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'condition',
                        'type' => 'condition',
                        'id_key' => 0,
                        'name' => 'Condition',
                        'values' => [
                            'new' => [
                                'name' => 'New',
                                'nbr' => 100,
                                'checked' => true,
                            ],
                            'used' => [
                                'name' => 'Used',
                                'nbr' => 0,
                            ],
                            'refurbished' => [
                                'name' => 'Refurbished',
                                'nbr' => 0,
                            ],
                        ],
                        'filter_show_limit' => 0,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'condition' => [
                        'new',
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutManufacturer()
    {
        $mock = Mockery::mock(Manufacturer::class);

        $mock->shouldReceive('getManufacturers')
            ->once()
            ->with(false, 2)
            ->andReturn([]);

        Manufacturer::setStaticExpectations($mock);

        $this->mockLayeredCategory([['type' => 'manufacturer', 'filter_show_limit' => 0, 'filter_type' => 1]]);

        $this->assertEquals(
            [
                'filters' => [
                    [],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'manufacturer' => [1],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithManufacturer()
    {
        $mock = Mockery::mock(Manufacturer::class);

        $mock->shouldReceive('getManufacturers')
            ->once()
            ->with(false, 2)
            ->andReturn(
                [
                    [
                        'id_manufacturer' => '2',
                        'name' => 'Graphic Corner',
                    ],
                    [
                        'id_manufacturer' => '1',
                        'name' => 'Studio Design',
                    ],
                ]
            );

        Manufacturer::setStaticExpectations($mock);
        $this->mockTranslator('Brand', [], 'Modules.Facetedsearch.Shop', 'Brand');

        $this->mockLayeredCategory([['type' => 'manufacturer', 'filter_show_limit' => 0, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('valueCount')
            ->with('id_manufacturer')
            ->andReturn(
                [
                    ['id_manufacturer' => 1, 'c' => 100],
                    ['id_manufacturer' => 2, 'c' => 10],
                    ['id_manufacturer' => 3, 'c' => 100],
                    ['c' => 0],
                ]
            );
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('id_manufacturer')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'manufacturer',
                        'type' => 'manufacturer',
                        'id_key' => 0,
                        'name' => 'Brand',
                        'values' => [
                            1 => [
                                'name' => 'Studio Design',
                                'nbr' => 100,
                                'checked' => true,
                            ],
                            [
                                'name' => 'Graphic Corner',
                                'nbr' => 10,
                            ],
                        ],
                        'filter_show_limit' => 0,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'manufacturer' => [1],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutAttributes()
    {
        $this->mockCombination();
        $this->mockLayeredCategory([['type' => 'id_attribute_group', 'id_value' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->once()
            ->with('with_attributes_1')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'id_attribute_group' => [1 => 'Something'],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutAttributesAndSelectedFilters()
    {
        $this->mockCombination();
        $this->mockLayeredCategory([['type' => 'id_attribute_group', 'id_value' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                ]
            )
        );
    }

    public function testGetFiltersBlockWithAttributesWithoutAttributesGroups()
    {
        $this->mockCombination(true);
        $this->mockLayeredCategory([['type' => 'id_attribute_group', 'id_value' => 1]]);
        $this->shopMock->shouldReceive('addSqlAssociation')
            ->with('attribute', 'a')
            ->andReturn('');

        $this->dbMock->shouldReceive('executeS')
            ->with('SELECT pac.id_attribute, COUNT(DISTINCT p.id_product) c FROM ps_product p LEFT JOIN ps_product_attribute pa ON (p.id_product = pa.id_product) LEFT JOIN ps_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) INNER JOIN ps_attribute a ON (a.id_attribute = pac.id_attribute) WHERE ((a.id_attribute_group=1)) GROUP BY pac.id_attribute')
            ->andReturn([]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                ]
            )
        );
    }

    public function testGetFiltersBlockWithAttributesWithAttributesGroupsWithoutFeatureActive()
    {
        $this->mockCombination(
            true,
            [
                [
                    'id_attribute_group' => '1',
                    'attribute_group_name' => 'Size',
                    'is_color_group' => '0',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_attribute_group' => '2',
                    'attribute_group_name' => 'Color',
                    'is_color_group' => '1',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_attribute_group' => '3',
                    'attribute_group_name' => 'Dimension',
                    'is_color_group' => '0',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_attribute_group' => '4',
                    'attribute_group_name' => 'Paper Type',
                    'is_color_group' => '0',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_attribute_group' => '5',
                    'attribute_group_name' => 'azdazd',
                    'is_color_group' => '0',
                    'url_name' => null,
                    'meta_title' => null,
                ],
            ]
        );
        $this->mockLayeredCategory([['type' => 'id_attribute_group', 'id_value' => 1, 'filter_show_limit' => true, 'filter_type' => 1]]);
        $this->shopMock->shouldReceive('addSqlAssociation')
            ->with('attribute', 'a')
            ->andReturn(
                'INNER JOIN ps_attribute_shop attribute_shop ' .
                'ON (attribute_shop.id_attribute = a.id_attribute AND attribute_shop.id_shop = 1)'
            );

        $attributes = [
            [
                'id_attribute' => '2',
                'color' => '#AAB2BD',
                'name' => 'Grey',
                'id_attribute_group' => '2',
                'url_name' => null,
                'meta_title' => null,
            ],
            [
                'id_attribute' => '2',
                'color' => '#CFC4A6',
                'name' => 'Taupe',
                'id_attribute_group' => '2',
                'url_name' => null,
                'meta_title' => null,
            ],
        ];
        $this->dbMock->shouldReceive('executeS')
            ->with(
                'SELECT DISTINCT a.`id_attribute`, a.`color`, al.`name`, agl.`id_attribute_group`, IF(lialv.`url_name` IS NULL OR lialv.`url_name` = "", NULL, lialv.`url_name`) AS url_name, IF(lialv.`meta_title` IS NULL OR lialv.`meta_title` = "", NULL, lialv.`meta_title`) AS meta_title FROM `ps_attribute_group` ag INNER JOIN `ps_attribute_group_lang` agl ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = 2) INNER JOIN `ps_attribute` a ON a.`id_attribute_group` = ag.`id_attribute_group` INNER JOIN `ps_attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = 2)INNER JOIN ps_attribute_group_shop attribute_group_shop ON (attribute_group_shop.id_attribute_group = ag.id_attribute_group AND attribute_group_shop.id_shop = 1) INNER JOIN ps_attribute_shop attribute_shop ON (attribute_shop.id_attribute = a.id_attribute AND attribute_shop.id_shop = 1) LEFT JOIN `ps_layered_indexable_attribute_lang_value` lialv ON (a.`id_attribute` = lialv.`id_attribute` AND lialv.`id_lang` = 2) WHERE ag.id_attribute_group = 1 ORDER BY agl.`name` ASC, a.`position` ASC'
            )
            ->andReturn($attributes);
        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('valueCount')
            ->with('id_attribute')
            ->andReturn(
                [
                    [
                        'id_attribute' => '1',
                        'c' => '2',
                    ],
                    [
                        'id_attribute' => '2',
                        'c' => '2',
                    ],
                    [
                        'id_attribute' => '3',
                        'c' => '2',
                    ],
                    [
                        'id_attribute' => '4',
                        'c' => '2',
                    ],
                ]
            );

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->andReturn($adapterInitialMock);

        $this->dbMock->shouldReceive('getRow')
            ->with('SELECT url_name, meta_title FROM ps_layered_indexable_attribute_group_lang_value WHERE id_attribute_group=2 AND id_lang=2')
            ->andReturn([]);

        $this->dbMock->shouldReceive('getRow')
            ->with('SELECT url_name, meta_title FROM ps_layered_indexable_attribute_lang_value WHERE id_attribute=2 AND id_lang=2')
            ->andReturn([]);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'id_attribute_group',
                        'type' => 'id_attribute_group',
                        'id_key' => '2',
                        'name' => 'Color',
                        'is_color_group' => true,
                        'values' => [
                            2 => [
                                'color' => '#CFC4A6',
                                'name' => 'Taupe',
                                'nbr' => '2',
                                'url_name' => null,
                                'meta_title' => null,
                                'checked' => true,
                            ],
                        ],
                        'url_name' => null,
                        'meta_title' => null,
                        'filter_show_limit' => true,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'id_attribute_group' => [
                        [2],
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutFeatures()
    {
        $this->mockFeatures([]);
        $this->mockLayeredCategory([['type' => 'id_feature', 'id_value' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('with_features_1')
            ->once()
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'id_feature' => [1 => 'Something'],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutFeaturesWithoutSearchFilter()
    {
        $this->mockFeatures([]);
        $this->mockLayeredCategory([['type' => 'id_feature', 'id_value' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->once()
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                ]
            )
        );
    }

    public function testGetFiltersBlockWithoutFeaturesWithoutSearchFilterAndFeatures()
    {
        $this->mockFeatures(
            [
                [
                    'id_feature' => '1',
                    'position' => '0',
                    'id_lang' => '1',
                    'name' => 'Composition',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature' => '2',
                    'position' => '1',
                    'id_lang' => '1',
                    'name' => 'Property',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature' => '9',
                    'position' => '2',
                    'id_lang' => '1',
                    'name' => 'FeatureExample',
                    'url_name' => null,
                    'meta_title' => null,
                ],
            ]
        );
        $this->mockLayeredCategory([['type' => 'id_feature', 'id_value' => 1, 'filter_show_limit' => 2, 'filter_type' => 2]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('valueCount')
            ->with('id_feature_value')
            ->andReturn(
                [
                    [
                        'id_feature' => '1',
                        'id_feature_value' => '4',
                        'c' => '2',
                    ],
                    [
                        'id_feature' => '1',
                        'id_feature_value' => '21',
                        'c' => '3',
                    ],
                ]
            );
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->once()
            ->andReturn($adapterInitialMock);

        $this->mockFeatureValues(
            1,
            [
                [
                    'id_feature_value' => '14',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'azdazd',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '13',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'azdazd',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '16',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'CeciEstUneFutureValue',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '3',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Ceramic',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '4',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Cotton',
                    'url_name' => 'something',
                    'meta_title' => 'weird',
                ],
                [
                    'id_feature_value' => '6',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Matt paper',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '1',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Polyester',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '5',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Recycled cardboard',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '2',
                    'id_feature' => '1',
                    'custom' => '0',
                    'id_lang' => '1',
                    'value' => 'Wool',
                    'url_name' => null,
                    'meta_title' => null,
                ],
                [
                    'id_feature_value' => '21',
                    'id_feature' => '1',
                    'custom' => '1',
                    'id_lang' => '1',
                    'value' => 'Test Custom value',
                    'url_name' => 'url-custom-21',
                    'meta_title' => 'title-custom-21',
                ],
            ]
        );

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'id_feature',
                        'type' => 'id_feature',
                        'id_key' => '1',
                        'values' => [
                            4 => [
                                'nbr' => '2',
                                'name' => 'Cotton',
                                'url_name' => 'something',
                                'meta_title' => 'weird',
                                'checked' => true,
                            ],
                            21 => [
                                'nbr' => '3',
                                'name' => 'Test Custom value',
                                'url_name' => 'url-custom-21',
                                'meta_title' => 'title-custom-21',
                            ],
                        ],
                        'name' => 'Composition',
                        'url_name' => null,
                        'meta_title' => null,
                        'filter_show_limit' => 2,
                        'filter_type' => 2,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'id_feature' => [[4]],
                ]
            )
        );
    }

    private function mockCombination($isActive = false, $attributeGroups = [])
    {
        $mock = Mockery::mock(Combination::class);

        $mock->shouldReceive('isFeatureActive')
            ->andReturn($isActive);

        Combination::setStaticExpectations($mock);

        if ($isActive) {
            $this->shopMock->shouldReceive('addSqlAssociation')
                ->with('attribute_group', 'ag')
                ->andReturn(
                    'INNER JOIN ps_attribute_group_shop attribute_group_shop ON ' .
                    '(attribute_group_shop.id_attribute_group = ag.id_attribute_group ' .
                    'AND attribute_group_shop.id_shop = 1)'
                );

            $this->shopMock->shouldReceive('addSqlAssociation')
                ->with('attribute', 'a')
                ->andReturn(
                    'INNER JOIN ps_attribute_shop attribute_shop ON ' .
                    '(attribute_shop.id_attribute = a.id_attribute AND attribute_shop.id_shop = 1)'
                );
            $this->dbMock->shouldReceive('executeS')
                ->once()
                ->with(
                    'SELECT ag.id_attribute_group, agl.public_name as attribute_group_name, is_color_group, ' .
                    'IF(liaglv.`url_name` IS NULL OR liaglv.`url_name` = "", NULL, liaglv.`url_name`) AS url_name, ' .
                    'IF(liaglv.`meta_title` IS NULL OR liaglv.`meta_title` = "", NULL, liaglv.`meta_title`) AS meta_title, ' .
                    'IFNULL(liag.indexable, TRUE) AS indexable ' .
                    'FROM `ps_attribute_group` ag ' .
                    'INNER JOIN ps_attribute_group_shop attribute_group_shop ' .
                    'ON (attribute_group_shop.id_attribute_group = ag.id_attribute_group AND attribute_group_shop.id_shop = 1) ' .
                    'LEFT JOIN `ps_attribute_group_lang` agl ' .
                    'ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = 2) ' .
                    'LEFT JOIN `ps_layered_indexable_attribute_group` liag ' .
                    'ON (ag.`id_attribute_group` = liag.`id_attribute_group`) ' .
                    'LEFT JOIN `ps_layered_indexable_attribute_group_lang_value` AS liaglv ' .
                    'ON (ag.`id_attribute_group` = liaglv.`id_attribute_group` AND agl.`id_lang` = 2) ' .
                    'GROUP BY ag.id_attribute_group ORDER BY ag.`position` ASC'
                )
                ->andReturn($attributeGroups);
        }
    }

    private function mockFeatures($features = [])
    {
        $this->shopMock
            ->shouldReceive('addSqlAssociation')
            ->with('feature', 'f')
            ->andReturn(
                'INNER JOIN ps_feature_shop feature_shop ON ' .
                '(feature_shop.id_feature = f.id_feature AND feature_shop.id_shop = 1)'
            );

        $this->dbMock
            ->shouldReceive('executeS')
            ->once()
            ->with(
                'SELECT DISTINCT f.id_feature, f.*, fl.*, ' .
                'IF(liflv.`url_name` IS NULL OR liflv.`url_name` = "", NULL, liflv.`url_name`) AS url_name, ' .
                'IF(liflv.`meta_title` IS NULL OR liflv.`meta_title` = "", NULL, liflv.`meta_title`) AS meta_title, ' .
                'lif.indexable ' .
                'FROM `ps_feature` f ' .
                'INNER JOIN ps_feature_shop feature_shop ON ' .
                '(feature_shop.id_feature = f.id_feature AND feature_shop.id_shop = 1) ' .
                'LEFT JOIN `ps_feature_lang` fl ON (f.`id_feature` = fl.`id_feature` AND fl.`id_lang` = 2) ' .
                'LEFT JOIN `ps_layered_indexable_feature` lif ' .
                'ON (f.`id_feature` = lif.`id_feature`) ' .
                'LEFT JOIN `ps_layered_indexable_feature_lang_value` liflv ' .
                'ON (f.`id_feature` = liflv.`id_feature` AND liflv.`id_lang` = 2) ' .
                'ORDER BY f.`position` ASC'
            )
            ->andReturn($features);
    }

    private function mockFeatureValues($idFeature, $featureValues = [])
    {
        $this->dbMock
            ->shouldReceive('executeS')
            ->once()
            ->with(
                'SELECT v.*, vl.*, ' .
                'IF(lifvlv.`url_name` IS NULL OR lifvlv.`url_name` = "", NULL, lifvlv.`url_name`) AS url_name, ' .
                'IF(lifvlv.`meta_title` IS NULL OR lifvlv.`meta_title` = "", NULL, lifvlv.`meta_title`) AS meta_title ' .
                'FROM `ps_feature_value` v ' .
                'LEFT JOIN `ps_feature_value_lang` vl ' .
                'ON (v.`id_feature_value` = vl.`id_feature_value` AND vl.`id_lang` = 2) ' .
                'LEFT JOIN `ps_layered_indexable_feature_value_lang_value` lifvlv ' .
                'ON (v.`id_feature_value` = lifvlv.`id_feature_value` AND lifvlv.`id_lang` = 2) ' .
                'WHERE v.`id_feature` = ' . (int) $idFeature . ' ' .
                'ORDER BY vl.`value` ASC'
            )
            ->andReturn($featureValues);
    }

    /**
     * Mock translator
     *
     * @param string|array $value
     * @param array $params
     * @param string $domain
     * @param string $returnValue
     */
    private function mockTranslator($value, $params = [], $domain = '', $returnValue = null)
    {
        $translatorMock = Mockery::mock(TranslatorComponent::class);

        if (is_array($value)) {
            foreach ($value as $val) {
                $translatorMock->shouldReceive('trans')
                    ->once()
                    ->with($val[0][0], $val[0][1], $val[0][2])
                    ->andReturn($val[1]);
            }
        } else {
            $translatorMock->shouldReceive('trans')
                ->once()
                ->with($value, $params, $domain)
                ->andReturn($returnValue);
        }

        $this->contextMock
            ->shouldReceive('getTranslator')
            ->andReturn($translatorMock);
    }

    /**
     * Mock layered category result
     */
    private function mockLayeredCategory($result)
    {
        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category
            WHERE controller = \'category\'
            AND id_category = 12
            AND id_shop = 1
            GROUP BY `type`, id_value ORDER BY position ASC')
            ->andReturn($result);
    }
}

<?php
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\FacetedSearch\Tests\Filters;

use stdClass;
use Group;
use Tools;
use Db;
use Context;
use Configuration;
use Manufacturer;
use Combination;
use Shop;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Filters\Block;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShopBundle\Translation\TranslatorComponent;

class BlockTest extends MockeryTestCase
{
    /** @var Context */
    private $contextMock;

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
                    'PS_ORDER_OUT_OF_STOCK' => '0',
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

        $this->dbMock = Mockery::mock(Db::class);
        $toolsMock = Mockery::mock(Tools::class);
        $toolsMock->shouldReceive('getValue')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'id_category' => 12,
                    'id_category_layered' => 11,
                ];

                return $valueMap[$arg];
            });
        Tools::setStaticExpectations($toolsMock);

        $this->adapterMock = Mockery::mock(MySQL::class)->makePartial();

        $this->block = new Block($this->adapterMock, $this->contextMock, $this->dbMock);
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
                        'filter_show_limit' => false,
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
                        'filter_show_limit' => false,
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
            [['Availability', [], 'Modules.Facetedsearch.Shop'], 'Quantity'],
            [['Not available', [], 'Modules.Facetedsearch.Shop'], 'Not available'],
            [['In stock', [], 'Modules.Facetedsearch.Shop'], 'In stock'],
        ]);
        $this->mockLayeredCategory([['type' => 'quantity', 'filter_show_limit' => false, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('count')
            ->once()
            ->andReturn(100);

        $adapterInitialMock
            ->shouldReceive('valueCount')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'quantity' => [['c' => 100]],
                    'out_of_stock' => [['out_of_stock' => 0, 'c' => 10]],
                ];

                return $valueMap[$arg];
            });

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('quantity')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'quantity',
                        'type' => 'quantity',
                        'id_key' => 0,
                        'name' => 'Quantity',
                        'values' => [
                            [
                                'name' => 'Not available',
                                'nbr' => 90,
                            ],
                            [
                                'name' => 'In stock',
                                'nbr' => 10,
                                'checked' => true,
                            ],
                        ],
                        'filter_show_limit' => false,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'quantity' => [
                        1,
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithQuantitiesWithOufOfStockOneData()
    {
        $this->mockTranslator([
            [['Availability', [], 'Modules.Facetedsearch.Shop'], 'Quantity'],
            [['Not available', [], 'Modules.Facetedsearch.Shop'], 'Not available'],
            [['In stock', [], 'Modules.Facetedsearch.Shop'], 'In stock'],
        ]);

        $this->mockLayeredCategory([['type' => 'quantity', 'filter_show_limit' => false, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('count')
            ->once()
            ->andReturn(100);

        $adapterInitialMock
            ->shouldReceive('valueCount')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'quantity' => [['c' => 100]],
                    'out_of_stock' => [['out_of_stock' => 1, 'c' => 10]],
                ];

                return $valueMap[$arg];
            });

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('quantity')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'quantity',
                        'type' => 'quantity',
                        'id_key' => 0,
                        'name' => 'Quantity',
                        'values' => [
                            [
                                'name' => 'Not available',
                                'nbr' => 90,
                            ],
                            [
                                'name' => 'In stock',
                                'nbr' => 10,
                                'checked' => true,
                            ],
                        ],
                        'filter_show_limit' => false,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'quantity' => [
                        1,
                    ],
                ]
            )
        );
    }

    public function testGetFiltersBlockWithQuantitiesWithOufOfStockTwoData()
    {
        $this->mockTranslator([
            [['Availability', [], 'Modules.Facetedsearch.Shop'], 'Quantity'],
            [['Not available', [], 'Modules.Facetedsearch.Shop'], 'Not available'],
            [['In stock', [], 'Modules.Facetedsearch.Shop'], 'In stock'],
        ]);
        $this->mockLayeredCategory([['type' => 'quantity', 'filter_show_limit' => false, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
        $adapterInitialMock->resetAll();
        $adapterInitialMock->shouldReceive('count')
            ->once()
            ->andReturn(100);

        $adapterInitialMock
            ->shouldReceive('valueCount')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'quantity' => [['c' => 100]],
                    'out_of_stock' => [['out_of_stock' => 2, 'c' => 10]],
                ];

                return $valueMap[$arg];
            });

        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('quantity')
            ->andReturn($adapterInitialMock);

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'quantity',
                        'type' => 'quantity',
                        'id_key' => 0,
                        'name' => 'Quantity',
                        'values' => [
                            [
                                'name' => 'Not available',
                                'nbr' => 110,
                            ],
                            [
                                'name' => 'In stock',
                                'nbr' => -10,
                                'checked' => true,
                            ],
                        ],
                        'filter_show_limit' => false,
                        'filter_type' => 1,
                    ],
                ],
            ],
            $this->block->getFilterBlock(
                10,
                [
                    'quantity' => [
                        1,
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
        $this->mockLayeredCategory([['type' => 'condition', 'filter_show_limit' => false, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
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
                        'filter_show_limit' => false,
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

        $this->mockLayeredCategory([['type' => 'manufacturer', 'filter_show_limit' => false, 'filter_type' => 1]]);

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

        $this->mockLayeredCategory([['type' => 'manufacturer', 'filter_show_limit' => false, 'filter_type' => 1]]);

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
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
                        'filter_show_limit' => false,
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
        $this->adapterMock->shouldReceive('getFilteredSearchAdapter')
            ->with('id_attribute')
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

        $adapterInitialMock = Mockery::mock(MySQL::class)->makePartial();
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

    private function mockCombination($isActive = false)
    {
        $mock = Mockery::mock(Combination::class);

        $mock->shouldReceive('isFeatureActive')
            ->once()
            ->andReturn($isActive);

        Combination::setStaticExpectations($mock);

        if ($isActive) {
            $mock = Mockery::mock(Shop::class);
            $mock->shouldReceive('addSqlAssociation')
                ->once()
                ->with('attribute_group', 'ag')
                ->andReturn(
                    'INNER JOIN ps_attribute_group_shop attribute_group_shop ON ' .
                    '(attribute_group_shop.id_attribute_group = ag.id_attribute_group ' .
                    'AND attribute_group_shop.id_shop = 1)'
                );

            Shop::setStaticExpectations($mock);

            $this->dbMock->shouldReceive('executeS')
                ->once()
                ->with(
                    'SELECT ag.id_attribute_group, agl.name as attribute_group_name, is_color_group ' .
                    'FROM `ps_attribute_group` ag INNER JOIN ps_attribute_group_shop attribute_group_shop ' .
                    'ON (attribute_group_shop.id_attribute_group = ag.id_attribute_group AND ' .
                    'attribute_group_shop.id_shop = 1) LEFT JOIN `ps_attribute_group_lang` agl ON ' .
                    '(ag.`id_attribute_group` = agl.`id_attribute_group` AND `id_lang` = 2) ' .
                    'GROUP BY ag.id_attribute_group ORDER BY ag.`position` ASC'
                )
                ->andReturn([]);
        }
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
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 12 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->andReturn($result);
    }
}

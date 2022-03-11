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

namespace PrestaShop\Module\FacetedSearch\Tests\Product;

use Configuration;
use Context;
use FrontController;
use Group;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShop\Module\FacetedSearch\Product\Search;
use stdClass;
use Tools;

class SearchTest extends MockeryTestCase
{
    /**
     * @var Search
     */
    private $search;

    protected function setUp()
    {
        $mock = Mockery::mock(Configuration::class);
        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_STOCK_MANAGEMENT' => true,
                    'PS_ORDER_OUT_OF_STOCK' => true,
                    'PS_HOME_CATEGORY' => true,
                    'PS_LAYERED_FULL_TREE' => false,
                    'PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY' => true,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        $groupMock = Mockery::mock(Group::class);
        $groupMock->shouldReceive('isFeatureActive')
            ->andReturn(true);
        $groupMock->shouldReceive('getCurrent')
            ->andReturnUsing(function () {
                $group = new Group();
                $group->id = 1;

                return $group;
            });

        Group::setStaticExpectations($groupMock);

        $frontControllerMock = Mockery::mock(FrontController::class);
        $frontControllerMock->shouldReceive('getCurrentCustomerGroups')
            ->andReturn([]);

        FrontController::setStaticExpectations($frontControllerMock);

        $contextMock = Mockery::mock(Context::class);
        $contextMock->shop = new stdClass();
        $contextMock->shop->id = 1;

        Context::setStaticExpectations($contextMock);

        $this->search = new Search($contextMock);
    }

    public function testGetFacetedSearchTypeAdapter()
    {
        $this->assertInstanceOf(
            MySQL::class,
            $this->search->getSearchAdapter()
        );
    }

    public function testInitSearchWithoutCorrectSelectedFilters()
    {
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

        $this->search->initSearch(['quantity' => []]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );
        $this->assertEquals([], $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray());
    }

    public function testInitSearchWithEmptyFilters()
    {
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

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );
        $this->assertEquals([], $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray());
    }

    public function testInitSearchWithAllFilters()
    {
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

        $this->search->initSearch(
            [
                'id_feature' => [
                    [1, 2],
                ],
                'id_attribute_group' => [
                    [4, 5],
                ],
                'category' => [
                    [6],
                ],
                'quantity' => [
                    0,
                ],
                'weight' => [
                    '10',
                    '40',
                ],
                'price' => [
                    '50',
                    '200',
                ],
                'manufacturer' => [
                    '10',
                ],
                'condition' => [
                    '1',
                ],
            ]
        );

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'weight' => [
                    '>=' => [
                        [
                            10.0,
                        ],
                    ],
                    '<=' => [
                        [
                            40.0,
                        ],
                    ],
                ],
                'price_min' => [
                    '<=' => [
                        [
                            200.0,
                        ],
                    ],
                ],
                'price_max' => [
                    '>=' => [
                        [
                            50.0,
                        ],
                    ],
                ],
                'id_manufacturer' => [
                    '=' => [
                        [
                            '10',
                        ],
                    ],
                ],
                'condition' => [
                    '=' => [
                        [
                            '1',
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                        [
                            6,
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );

        $this->assertEquals(
            [
                'with_stock_management' => [
                    [
                        [
                            'quantity',
                            [
                                0,
                            ],
                            '<=',
                        ],
                        [
                            'out_of_stock',
                            [
                                0,
                            ],
                            '=',
                        ],
                    ],
                ],
                'with_attributes_0' => [
                    [
                        [
                            'id_attribute',
                            [
                                4,
                                5,
                            ],
                        ],
                    ],
                ],
                'with_features_0' => [
                    [
                        [
                            'id_feature_value',
                            [
                                1,
                                2,
                            ],
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray()
        );
    }

    public function testInitSearchWithManyFeatures()
    {
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

        $this->search->initSearch(
            [
                'id_feature' => [
                    [1],
                    [2, 3, 4],
                ],
            ]
        );

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );

        $this->assertEquals(
            [
                'with_features_0' => [
                    [
                        [
                            'id_feature_value',
                            [
                                1,
                            ],
                        ],
                    ],
                ],
                'with_features_1' => [
                    [
                        [
                            'id_feature_value',
                            [
                                2,
                                3,
                                4,
                            ],
                        ],
                    ],
                ],
           ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray()
        );
    }

    public function testInitSearchWithManyAttributes()
    {
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

        $this->search->initSearch(
            [
                'id_attribute_group' => [
                    [1],
                    [2, 3, 4],
                ],
            ]
        );

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );

        $this->assertEquals(
            [
                'with_attributes_0' => [
                    [
                        [
                            'id_attribute',
                            [
                                1,
                            ],
                        ],
                    ],
                ],
                'with_attributes_1' => [
                    [
                        [
                            'id_attribute',
                            [
                                2,
                                3,
                                4,
                            ],
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray()
        );
    }

    public function testInitSearchWithQuantityFiltersWithoutStockManagement()
    {
        $mock = Mockery::mock(Configuration::class);
        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_STOCK_MANAGEMENT' => false,
                    'PS_ORDER_OUT_OF_STOCK' => true,
                    'PS_HOME_CATEGORY' => true,
                    'PS_LAYERED_FULL_TREE' => false,
                    'PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY' => true,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        $contextMock = Mockery::mock(Context::class);
        $contextMock->shop = new stdClass();
        $contextMock->shop->id = 1;

        Context::setStaticExpectations($contextMock);

        $this->search = new Search($contextMock);

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

        $this->search->initSearch(['quantity' => [0]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );
        $this->assertEquals([], $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray());
    }

    public function testInitSearchWithFirstQuantityFilters()
    {
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

        $this->search->initSearch(['quantity' => [1]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'with_stock_management' => [
                    [
                        [
                            'out_of_stock',
                            [
                                1,
                                2,
                            ],
                            '=',
                        ],
                    ],
                    [
                        [
                            'quantity',
                            [
                                0,
                            ],
                            '>',
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray()
        );
    }

    public function testInitSearchWithSecondQuantityFilters()
    {
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

        $this->search->initSearch(['quantity' => [2]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'with_stock_management' => [
                    [
                        [
                            'quantity',
                            [
                                0,
                            ],
                            '>',
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getOperationsFilters()->toArray()
        );
    }

    public function testInitSearchWithoutGroupFeature()
    {
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

        $groupMock = Mockery::mock(Group::class);
        $groupMock->shouldReceive('isFeatureActive')
            ->andReturn(false);

        Group::setStaticExpectations($groupMock);

        $this->search->initSearch(['quantity' => [2]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );
    }

    public function testInitSearchWithUserBelongingToGroups()
    {
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

        $frontControllerMock = Mockery::mock(FrontController::class);
        $frontControllerMock->shouldReceive('getCurrentCustomerGroups')
            ->andReturn([2, 999]);

        FrontController::setStaticExpectations($frontControllerMock);

        $this->search->initSearch(['quantity' => [2]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            null,
                        ],
                    ],
                ],
                'id_shop' => [
                    '=' => [
                        [
                            1,
                        ],
                    ],
                ],
                'visibility' => [
                    '=' => [
                        [
                            'both',
                            'catalog',
                        ],
                    ],
                ],
                'id_group' => [
                    '=' => [
                        [
                            2,
                            999,
                        ],
                    ],
                ],
            ],
            $this->search->getSearchAdapter()->getInitialPopulation()->getFilters()->toArray()
        );
    }

    public function testAddFilter()
    {
        $this->search->addFilter('weight', [10, 20]);
        $this->search->addFilter('id_feature', [[10, 20]]);
    }
}

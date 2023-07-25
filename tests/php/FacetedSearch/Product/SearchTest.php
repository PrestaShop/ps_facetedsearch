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
use PrestaShop\Module\FacetedSearch\Definition\Availability;
use PrestaShop\Module\FacetedSearch\Product\Search;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use stdClass;

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

        // Initialize the search engine
        $this->search = new Search($contextMock);

        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getIdCategory')
            ->andReturn(12);
        $query->shouldReceive('getQueryType')
            ->andReturn('category');

        $this->search->setQuery($query);
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
        $this->search->initSearch(['availability' => []]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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

    /**
     * Tests basic initial load of category, when full tree is disabled and we want only products from default category
     */
    public function testInitSearchWithEmptyFilters()
    {
        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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

    /**
     * Tests basic initial load of category, when full tree is enabled
     */
    public function testInitSearchWithEmptyFiltersAndFullTree()
    {
        $mock = Mockery::mock(Configuration::class);
        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_STOCK_MANAGEMENT' => true,
                    'PS_ORDER_OUT_OF_STOCK' => true,
                    'PS_HOME_CATEGORY' => true,
                    'PS_LAYERED_FULL_TREE' => true,
                    'PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY' => false,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'nleft' => [
                    '>=' => [
                        [
                            101,
                        ],
                    ],
                ],
                'nright' => [
                    '<=' => [
                        [
                            102,
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

    /**
     * Tests filtering with user filters, including a specific selected category
     */
    public function testInitSearchWithAllFilters()
    {
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
                'availability' => [
                    Availability::NOT_AVAILABLE,
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
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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

        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getIdCategory')
            ->andReturn(12);
        $query->shouldReceive('getQueryType')
            ->andReturn('category');
        $this->search->setQuery($query);

        $this->search->initSearch(['availability' => [Availability::NOT_AVAILABLE]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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
        $this->search->initSearch(['availability' => [Availability::AVAILABLE]]);

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
        $this->search->initSearch(['availability' => [Availability::IN_STOCK]]);

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
        $groupMock = Mockery::mock(Group::class);
        $groupMock->shouldReceive('isFeatureActive')
            ->andReturn(false);

        Group::setStaticExpectations($groupMock);

        $this->search->initSearch(['availability' => [Availability::IN_STOCK]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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
        $frontControllerMock = Mockery::mock(FrontController::class);
        $frontControllerMock->shouldReceive('getCurrentCustomerGroups')
            ->andReturn([2, 999]);

        FrontController::setStaticExpectations($frontControllerMock);

        $this->search->initSearch(['availability' => [Availability::IN_STOCK]]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_category_default' => [
                    '=' => [
                        [
                            12,
                        ],
                    ],
                ],
                'id_category' => [
                    '=' => [
                        [
                            12,
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

    /* Test initial filters on manufacturer controller */
    public function testInitSearchWithEmptyFiltersOnManufacturerController()
    {
        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getIdManufacturer')
            ->andReturn(100);
        $query->shouldReceive('getQueryType')
            ->andReturn('manufacturer');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_manufacturer' => [
                    '=' => [
                        [
                            100,
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

    /* Test initial filters on supplier controller */
    public function testInitSearchWithEmptyFiltersOnSupplierController()
    {
        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getIdSupplier')
            ->andReturn(100);
        $query->shouldReceive('getQueryType')
            ->andReturn('supplier');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'id_supplier' => [
                    '=' => [
                        [
                            100,
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

    /* Test initial filters on pricesdrop controller */
    public function testInitSearchWithEmptyFiltersOnPricesdropController()
    {
        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getQueryType')
            ->andReturn('prices-drop');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'reduction' => [
                    '>' => [
                        [
                            0,
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

    /* Test initial filters on bestsales controller */
    public function testInitSearchWithEmptyFiltersOnBestsalesController()
    {
        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getQueryType')
            ->andReturn('best-sales');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'sales' => [
                    '>' => [
                        [
                            0,
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

    /* Test initial filters on newproducts controller with days new product set to 30 */
    public function testInitSearchWithEmptyFiltersOnNewproductsController()
    {
        $mock = Mockery::mock(Configuration::class);
        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_STOCK_MANAGEMENT' => true,
                    'PS_ORDER_OUT_OF_STOCK' => true,
                    'PS_HOME_CATEGORY' => true,
                    'PS_NB_DAYS_NEW_PRODUCT' => 30,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getQueryType')
            ->andReturn('new-products');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'date_add' => [
                    '>' => [
                        [
                            "'" . date('Y-m-d 00:00:00', strtotime('-29 days')) . "'",
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

    /* Test initial filters on newproducts controller, with days new product set to 0 */
    public function testInitSearchWithEmptyFiltersOnNewproductsControllerZero()
    {
        $mock = Mockery::mock(Configuration::class);
        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_STOCK_MANAGEMENT' => true,
                    'PS_ORDER_OUT_OF_STOCK' => true,
                    'PS_HOME_CATEGORY' => true,
                    'PS_NB_DAYS_NEW_PRODUCT' => 0,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        // Initialize fake query
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getQueryType')
            ->andReturn('new-products');
        $this->search->setQuery($query);

        $this->search->initSearch([]);

        $this->assertEquals([], $this->search->getSearchAdapter()->getFilters()->toArray());
        $this->assertEquals([], $this->search->getSearchAdapter()->getOperationsFilters()->toArray());
        $this->assertEquals(
            [
                'date_add' => [
                    '>' => [
                        [
                            "'" . date('Y-m-d 00:00:00', strtotime('+ 1 days')) . "'",
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

    public function testAddFilter()
    {
        $this->search->addFilter('weight', [10, 20]);
        $this->search->addFilter('id_feature', [[10, 20]]);
    }
}

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

namespace PrestaShop\Module\FacetedSearch\Tests\Product;

use Configuration;
use Context;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShop\Module\FacetedSearch\Product\Search;
use Tools;
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
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

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
                    '>=' => [
                        [
                            50.0,
                        ],
                    ],
                ],
                'price_max' => [
                    '<=' => [
                        [
                            200.0,
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

    public function testAddFilter()
    {
        $this->search->addFilter('weight', [10, 20]);
        $this->search->addFilter('id_feature', [[10, 20]]);
    }
}

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

use Configuration;
use Context;
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Filters\Converter;
use PrestaShop\Module\FacetedSearch\Filters\DataAccessor;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use Shop;
use stdClass;
use Tools;

class ConverterTest extends MockeryTestCase
{
    /** @var Context */
    private $contextMock;

    /** @var Db */
    private $dbMock;

    /** @var Block */
    private $converter;

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

        $this->shopMock = Mockery::mock(Shop::class);
        Shop::setStaticExpectations($this->shopMock);

        $this->converter = new Converter(
            $this->contextMock,
            $this->dbMock,
            new URLSerializer(),
            new DataAccessor($this->dbMock)
        );
    }

    public function testGetFacetsFromFilterBlocksWithEmptyArray()
    {
        $this->assertEquals(
            [],
            $this->converter->getFacetsFromFilterBlocks(
                []
            )
        );
    }

    /**
     * Test different scenario for facets filter
     *
     * @dataProvider facetsProvider
     */
    public function testGetFacetsFromFilterBlocks($filterBlocks, $expected)
    {
        $this->assertEquals(
            $expected,
            $this->converter->getFacetsFromFilterBlocks(
                [$filterBlocks]
            )
        );
    }

    public function facetsProvider()
    {
        return [
            // Empty
            [
                [],
                [],
            ],
            // Categories
            [
                [
                    'type_lite' => 'category',
                    'type' => 'category',
                    'id_key' => 0,
                    'name' => 'Categories',
                    'values' => [
                        7 => [
                            'name' => 'Stationery',
                            'nbr' => '3',
                        ],
                        8 => [
                            'name' => 'Home Accessories',
                            'nbr' => '8',
                            'checked' => true,
                        ],
                    ],
                    'filter_show_limit' => '0',
                    'filter_type' => Converter::WIDGET_TYPE_RADIO,
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Categories',
                            'type' => 'category',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => 0,
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => 'Home Accessories',
                                        'type' => 'category',
                                        'active' => true,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 8,
                                        'value' => 8,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                1 => Filter::__set_state(
                                    [
                                        'label' => 'Stationery',
                                        'type' => 'category',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 3,
                                        'value' => 7,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => false,
                            'widgetType' => 'radio',
                        ]
                    ),
                ],
            ],

            // Attribute group
            [
                [
                    'type_lite' => 'id_attribute_group',
                    'type' => 'id_attribute_group',
                    'id_key' => '2',
                    'name' => 'Color',
                    'is_color_group' => true,
                    'values' => [
                        8 => [
                            'name' => 'White',
                            'nbr' => '3',
                            'url_name' => null,
                            'meta_title' => null,
                            'color' => '#ffffff',
                        ],
                        11 => [
                            'name' => 'Black',
                            'nbr' => '3',
                            'url_name' => null,
                            'meta_title' => null,
                            'color' => '#434A54',
                        ],
                        12 => [
                            'name' => 'Weird',
                            'nbr' => '3',
                            'url_name' => null,
                            'meta_title' => null,
                            'color' => '',
                        ],
                    ],
                    'filter_show_limit' => '0',
                    'filter_type' => Converter::WIDGET_TYPE_DROPDOWN,
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Color',
                            'type' => 'attribute_group',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => '0',
                                'id_attribute_group' => '2',
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => 'White',
                                        'type' => 'attribute_group',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [
                                            'color' => '#ffffff',
                                        ],
                                        'magnitude' => 3,
                                        'value' => 8,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Black',
                                        'type' => 'attribute_group',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [
                                            'color' => '#434A54',
                                        ],
                                        'magnitude' => 3,
                                        'value' => 11,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Weird',
                                        'type' => 'attribute_group',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [
                                            'texture' => '/theme/12.jpg',
                                        ],
                                        'magnitude' => 3,
                                        'value' => 12,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => false,
                            'widgetType' => 'dropdown',
                        ]
                    ),
                ],
            ],

            // Feature values
            [
                [
                    'type_lite' => 'id_feature',
                    'type' => 'id_feature',
                    'id_key' => '2',
                    'values' => [
                        5 => [
                            'nbr' => '2',
                            'name' => '2',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                        6 => [
                            'nbr' => '2',
                            'name' => '1',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                        7 => [
                            'nbr' => '2',
                            'name' => '2.2',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                        8 => [
                            'nbr' => '2',
                            'name' => '2.1',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                        9 => [
                            'nbr' => '3',
                            'name' => 'Removable cover',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                        10 => [
                            'nbr' => '3',
                            'name' => '120 pages',
                            'url_name' => null,
                            'meta_title' => null,
                        ],
                    ],
                    'name' => 'Property',
                    'url_name' => null,
                    'meta_title' => null,
                    'filter_show_limit' => '0',
                    'filter_type' => '0',
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Property',
                            'type' => 'feature',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => 0,
                                'id_feature' => '2',
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => '1',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 2,
                                        'value' => 6,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => '2',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 2,
                                        'value' => 5,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => '2.1',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 2,
                                        'value' => 8,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => '2.2',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 2,
                                        'value' => 7,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => '120 pages',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 3,
                                        'value' => 10,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Removable cover',
                                        'type' => 'feature',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 3,
                                        'value' => 9,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => true,
                            'widgetType' => 'checkbox',
                        ]
                    ),
                ],
            ],

            // Quantity
            [
                [
                    'type_lite' => 'quantity',
                    'type' => 'quantity',
                    'id_key' => 0, 'name' => 'Availability',
                    'values' => [
                        0 => [
                            'name' => 'Not available',
                            'nbr' => 0,
                        ],
                        1 => [
                            'name' => 'In stock',
                            'nbr' => 11,
                        ],
                    ],
                    'filter_show_limit' => '0',
                    'filter_type' => '0',
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Availability',
                            'type' => 'availability',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => 0,
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => 'In stock',
                                        'type' => 'availability',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 11,
                                        'value' => 1,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Not available',
                                        'type' => 'availability',
                                        'active' => false,
                                        'displayed' => false,
                                        'properties' => [],
                                        'magnitude' => 0,
                                        'value' => 0,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => true,
                            'widgetType' => 'checkbox',
                        ]
                    ),
                ],
            ],
            // Manufacturer
            [
                [
                    'type_lite' => 'manufacturer',
                    'type' => 'manufacturer',
                    'id_key' => 0, 'name' => 'Brand',
                    'values' => [
                        1 => [
                            'name' => 'Studio Design',
                            'nbr' => '7',
                        ],
                        2 => [
                            'name' => 'Graphic Corner',
                            'nbr' => '3',
                        ],
                    ],
                    'filter_show_limit' => '0',
                    'filter_type' => '0',
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Brand',
                            'type' => 'manufacturer',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => 0,
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => 'Graphic Corner',
                                        'type' => 'manufacturer',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 3,
                                        'value' => 2,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Studio Design',
                                        'type' => 'manufacturer',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 7,
                                        'value' => 1,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => true,
                            'widgetType' => 'checkbox',
                        ]
                    ),
                ],
            ],

            // Condition
            [
                [
                    'type_lite' => 'condition',
                    'type' => 'condition',
                    'id_key' => 0, 'name' => 'Condition',
                    'values' => [
                        'new' => [
                            'name' => 'New',
                            'nbr' => '11',
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
                    'filter_show_limit' => '0',
                    'filter_type' => '0',
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Condition',
                            'type' => 'condition',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => '0',
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => 'New',
                                        'type' => 'condition',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [],
                                        'magnitude' => 11,
                                        'value' => 'new',
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Refurbished',
                                        'type' => 'condition',
                                        'active' => false,
                                        'displayed' => false,
                                        'properties' => [],
                                        'magnitude' => 0,
                                        'value' => 'refurbished',
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                                Filter::__set_state(
                                    [
                                        'label' => 'Used',
                                        'type' => 'condition',
                                        'active' => false,
                                        'displayed' => false,
                                        'properties' => [],
                                        'magnitude' => 0,
                                        'value' => 'used',
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => true,
                            'widgetType' => 'checkbox',
                        ]
                    ),
                ],
            ],

            // Price
            [
                [
                    'type_lite' => 'price',
                    'type' => 'price',
                    'id_key' => 0, 'name' => 'Price',
                    'max' => 35.0, 'min' => 11.0, 'unit' => '$',
                    'specifications' => [
                        'symbol' => [
                            0 => '.',
                            1 => ',',
                            2 => ';',
                            3 => '%',
                            4 => '-',
                            5 => '+',
                            6 => 'E',
                            7 => '×',
                            8 => '‰',
                            9 => '∞',
                            10 => 'NaN',
                        ],
                        'currencyCode' => 'USD',
                        'currencySymbol' => '$',
                        'positivePattern' => '¤#,##0.00',
                        'negativePattern' => '-¤#,##0.00',
                        'maxFractionDigits' => 2,
                        'minFractionDigits' => 2,
                        'groupingUsed' => true,
                        'primaryGroupSize' => 3,
                        'secondaryGroupSize' => 3,
                    ],
                    'filter_show_limit' => 0,
                    'filter_type' => 3, 'nbr' => '11',
                    'value' => null,
                ],
                [
                    Facet::__set_state(
                        [
                            'label' => 'Price',
                            'type' => 'price',
                            'displayed' => true,
                            'properties' => [
                                'filter_show_limit' => 0,
                                'min' => 11.0,
                                'max' => 35.0,
                                'unit' => '$',
                                'specifications' => [
                                    'symbol' => [
                                        0 => '.',
                                        1 => ',',
                                        2 => ';',
                                        3 => '%',
                                        4 => '-',
                                        5 => '+',
                                        6 => 'E',
                                        7 => '×',
                                        8 => '‰',
                                        9 => '∞',
                                        10 => 'NaN',
                                    ],
                                    'currencyCode' => 'USD',
                                    'currencySymbol' => '$',
                                    'positivePattern' => '¤#,##0.00',
                                    'negativePattern' => '-¤#,##0.00',
                                    'maxFractionDigits' => 2,
                                    'minFractionDigits' => 2,
                                    'groupingUsed' => true,
                                    'primaryGroupSize' => 3,
                                    'secondaryGroupSize' => 3,
                                ],
                                'range' => true,
                            ],
                            'filters' => [
                                Filter::__set_state(
                                    [
                                        'label' => '',
                                        'type' => 'price',
                                        'active' => false,
                                        'displayed' => true,
                                        'properties' => [
                                            'symbol' => '$',
                                        ],
                                        'magnitude' => 11,
                                        'value' => null,
                                        'nextEncodedFacets' => [],
                                    ]
                                ),
                            ],
                            'multipleSelectionAllowed' => false,
                            'widgetType' => 'slider',
                        ]
                    ),
                ],
            ],
        ];
    }
}

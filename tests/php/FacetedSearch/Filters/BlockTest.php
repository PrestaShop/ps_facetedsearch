<?php

namespace PrestaShop\Module\FacetedSearch\Tests\Filters;

use stdClass;
use Group;
use Tools;
use Db;
use Context;
use Configuration;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FacetedSearch\Filters\Block;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL;
use PrestaShopBundle\Translation\TranslatorComponent;

class BlockTest extends TestCase
{
    /** @var Context */
    private $contextMock;

    /** @var Db */
    private $dbMock;

    /** @var Block */
    private $block;

    protected function setUp()
    {
        $mock = $this->getMockBuilder(Configuration::class)
              ->setMethods(['get'])
              ->getMock();

        $valueMap = [
            ['PS_HOME_CATEGORY', 1],
            ['PS_WEIGHT_UNIT', 'kg'],
            ['PS_STOCK_MANAGEMENT', '1'],
            ['PS_ORDER_OUT_OF_STOCK', '1'],
            ['PS_UNIDENTIFIED_GROUP', '1'],
            ['PS_LAYERED_FILTER_CATEGORY_DEPTH', null, null, null, 1, 3],
        ];
        $mock->method('get')
            ->will($this->returnValueMap($valueMap));

        Configuration::setStaticExpectations($mock);

        $this->contextMock = $this->getMockBuilder(Context::class)
                           ->setMethods(['getTranslator'])
                           ->getMock();
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

        $this->dbMock = $this->getMockBuilder(Db::class)
                      ->setMethods(['executeS'])
                      ->getMock();

        $toolsMock = $this->getMockBuilder(Tools::class)
                   ->setMethods(['getValue'])
                   ->getMock();
        $valueMap = [
            ['id_category', 12],
            ['id_category_layered', 11],
        ];
        $toolsMock->method('getValue')
            ->will($this->returnValueMap($valueMap));
        Tools::setStaticExpectations($toolsMock);

        $this->adapterMock = $this->getMockBuilder(MySQL::class)
                           ->setMethods(['getInitialPopulation', 'getFilteredSearchAdapter'])
                           ->getMock();

        $this->block = new Block($this->adapterMock, $this->contextMock, $this->dbMock);
    }

    public function testGetEmptyFiltersBlock()
    {
        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will($this->returnValue([]));
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

        $groupMock = $this->getMockBuilder(Group::class)
                   ->setMethods(['getCurrent'])
                   ->getMock();
        $groupMock->expects($this->once())
            ->method('getCurrent')
            ->will($this->returnValue($group));

        Group::setStaticExpectations($groupMock);

        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will(
                $this->returnValue(
                    [
                        ['type' => 'price'],
                    ]
                )
            );
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

        $groupMock = $this->getMockBuilder(Group::class)
                   ->setMethods(['getCurrent'])
                   ->getMock();
        $groupMock->expects($this->once())
            ->method('getCurrent')
            ->will($this->returnValue($group));

        Group::setStaticExpectations($groupMock);

        $translatorMock = $this->getMockBuilder(TranslatorComponent::class)
                        ->disableOriginalConstructor()
                        ->setMethods(['trans'])
                        ->getMock();

        $translatorMock->expects($this->once())
            ->method('trans')
            ->with('Price', [], 'Modules.Facetedsearch.Shop')
            ->will($this->returnValue('Price'));

        $this->contextMock->expects($this->once())
            ->method('getTranslator')
            ->will($this->returnValue($translatorMock));

        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will(
                $this->returnValue(
                    [
                        ['type' => 'price', 'filter_show_limit' => false],
                    ]
                )
            );

        $adapterInitialMock = $this->getMockBuilder(MySQL::class)
                            ->setMethods(['getMinMaxPriceValue'])
                            ->getMock();
        $adapterInitialMock->method('getMinMaxPriceValue')
            ->will($this->returnValue([10.0, 100.0]));
        $this->adapterMock->method('getInitialPopulation')
            ->will($this->returnValue($adapterInitialMock));
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
        $translatorMock = $this->getMockBuilder(TranslatorComponent::class)
                        ->disableOriginalConstructor()
                        ->setMethods(['trans'])
                        ->getMock();

        $translatorMock->expects($this->once())
            ->method('trans')
            ->with('Weight', [], 'Modules.Facetedsearch.Shop')
            ->will($this->returnValue('Weight'));

        $this->contextMock->expects($this->once())
            ->method('getTranslator')
            ->will($this->returnValue($translatorMock));

        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will(
                $this->returnValue(
                    [
                        ['type' => 'weight', 'filter_show_limit' => false],
                    ]
                )
            );

        $adapterInitialMock = $this->getMockBuilder(MySQL::class)
                            ->setMethods(['getMinMaxValue'])
                            ->getMock();
        $adapterInitialMock->method('getMinMaxValue')
            ->with('p.weight')
            ->will($this->returnValue([10.0, 100.0]));
        $this->adapterMock->method('getInitialPopulation')
            ->will($this->returnValue($adapterInitialMock));
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
                        'specifications' => [],
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
        $translatorMock = $this->getMockBuilder(TranslatorComponent::class)
                        ->disableOriginalConstructor()
                        ->setMethods(['trans'])
                        ->getMock();

        $translatorMock->expects($this->once())
            ->method('trans')
            ->with('Weight', [], 'Modules.Facetedsearch.Shop')
            ->will($this->returnValue('Weight'));

        $this->contextMock->expects($this->once())
            ->method('getTranslator')
            ->will($this->returnValue($translatorMock));

        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will(
                $this->returnValue(
                    [
                        ['type' => 'weight', 'filter_show_limit' => false],
                    ]
                )
            );

        $adapterInitialMock = $this->getMockBuilder(MySQL::class)
                            ->setMethods(['getMinMaxValue'])
                            ->getMock();
        $adapterInitialMock->method('getMinMaxValue')
            ->with('p.weight')
            ->will($this->returnValue([0, 0]));
        $this->adapterMock->method('getInitialPopulation')
            ->will($this->returnValue($adapterInitialMock));
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

    public function testGetFiltersBlockWithCondition()
    {
        $translatorMock = $this->getMockBuilder(TranslatorComponent::class)
                        ->disableOriginalConstructor()
                        ->setMethods(['trans'])
                        ->getMock();

        $valueMap = [
            ['New', [], 'Modules.Facetedsearch.Shop', 'New'],
            ['Used', [], 'Modules.Facetedsearch.Shop', 'New'],
            ['Refurbished', [], 'Modules.Facetedsearch.Shop', 'New'],
        ];

        $translatorMock->method('trans')
            ->will($this->returnValueMap($valueMap));

        $this->contextMock->expects($this->any())
            ->method('getTranslator')
            ->will($this->returnValue($translatorMock));

        $this->dbMock->expects($this->once())
            ->method('executeS')
            ->with('SELECT type, id_value, filter_show_limit, filter_type FROM ps_layered_category WHERE id_category = 0 AND id_shop = 1 GROUP BY `type`, id_value ORDER BY position ASC')
            ->will(
                $this->returnValue(
                    [
                        ['type' => 'condition', 'filter_show_limit' => false, 'filter_type' => 1],
                    ]
                )
            );

        $adapterInitialMock = $this->getMockBuilder(MySQL::class)
                            ->setMethods(['valueCount'])
                            ->getMock();
        $adapterInitialMock->method('valueCount')
            ->with('condition')
            ->will($this->returnValue([['c' => 100, 'condition' => 'new']]));
        $this->adapterMock->method('getFilteredSearchAdapter')
            ->with('condition')
            ->will($this->returnValue($adapterInitialMock));

        $this->assertEquals(
            [
                'filters' => [
                    [
                        'type_lite' => 'condition',
                        'type' => 'condition',
                        'id_key' => 0,
                        'name' => null,
                        'values' => [
                            'new' => [
                                'name' => null,
                                'nbr' => 100,
                                'checked' => true,
                            ],
                            'used' => [
                                'name' => null,
                                'nbr' => 0,
                            ],
                            'refurbished' => [
                                'name' => null,
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
}

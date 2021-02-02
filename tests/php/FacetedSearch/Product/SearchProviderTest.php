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
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Filters\Converter;
use PrestaShop\Module\FacetedSearch\Filters\DataAccessor;
use PrestaShop\Module\FacetedSearch\Product\SearchProvider;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use Ps_Facetedsearch;
use Smarty;
use Tools;

class SearchProviderTest extends MockeryTestCase
{
    /**
     * @var Search
     */
    private $provider;

    /**
     * @var Db
     */
    private $database;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var URLSerializer
     */
    private $serializer;

    /**
     * @var FacetCollection
     */
    private $facetCollection;

    /**
     * @var Ps_Facetedsearch
     */
    private $module;

    private function mockFacet($label, $data = ['filters' => []], $widgetType = 'checkbox')
    {
        $facet = Mockery::mock(Facet::class);
        $facet->shouldReceive('getLabel')
            ->andReturn($label);

        $facet->shouldReceive('toArray')
            ->andReturn($data);

        $facet->shouldReceive('getWidgetType')
            ->andReturn($widgetType);

        return $facet;
    }

    private function mockFilter($label, $active = false, $value = null, $properties = [])
    {
        $filter = Mockery::mock(Filter::class);
        $filter->shouldReceive('getLabel')
            ->andReturn($label);
        $filter->shouldReceive('isActive')
            ->andReturn($active);

        if ($value !== null) {
            $filter->shouldReceive('getValue')
                ->andReturn($value);
        }

        $filter->shouldReceive('getProperty')
            ->andReturnUsing(
                function ($arg) use ($properties) {
                    return $properties[$arg];
                }
            );

        return $filter;
    }

    protected function setUp()
    {
        $this->database = Mockery::mock(Db::class);
        $this->context = Mockery::mock(Context::class);
        $this->converter = Mockery::mock(Converter::class);
        $this->serializer = Mockery::mock(URLSerializer::class);
        $this->facetCollection = Mockery::mock(FacetCollection::class);

        $this->module = Mockery::mock(Ps_Facetedsearch::class);
        $this->module->shouldReceive('getDatabase')
            ->andReturn($this->database);
        $this->module->shouldReceive('getContext')
            ->andReturn($this->context);
        $this->module->shouldReceive('isAjax')
            ->andReturn(true);

        $mock = Mockery::mock(Configuration::class);

        $mock->shouldReceive('get')
            ->andReturnUsing(function ($arg) {
                $valueMap = [
                    'PS_LAYERED_SHOW_QTIES' => true,
                ];

                return $valueMap[$arg];
            });

        Configuration::setStaticExpectations($mock);

        $toolsMock = Mockery::mock(Tools::class);
        $toolsMock->shouldReceive('getCurrentUrlProtocolPrefix')
            ->andReturn('http://');
        Tools::setStaticExpectations($toolsMock);

        $this->provider = new SearchProvider(
            $this->module,
            $this->converter,
            $this->serializer,
            new DataAccessor($this->database)
        );
    }

    public function testRenderFacetsWithoutFacetsCollection()
    {
        $productContext = Mockery::mock(ProductSearchContext::class);
        $productSearchResult = Mockery::mock(ProductSearchResult::class);
        $productSearchResult->shouldReceive('getFacetCollection')
            ->once()
            ->andReturn(null);

        $this->assertEquals(
            '',
            $this->provider->renderFacets(
                $productContext,
                $productSearchResult
            )
        );
    }

    public function testRenderFacetsWithFacetsCollection()
    {
        $productContext = Mockery::mock(ProductSearchContext::class);
        $smarty = Mockery::mock(Smarty::class);
        $smarty->shouldReceive('assign')
            ->once()
            ->with(
                [
                    'show_quantities' => true,
                    'facets' => [
                        [
                            'filters' => [],
                        ],
                    ],
                    'js_enabled' => true,
                    'displayedFacets' => [],
                    'activeFilters' => [],
                    'sort_order' => 'product.position.asc',
                    'clear_all_link' => 'http://shop.prestashop.com/catalog?from=scratch',
                ]
            );
        $this->context->smarty = $smarty;
        $sortOrder = Mockery::mock(SortOrder::class);
        $sortOrder->shouldReceive('toString')
            ->once()
            ->andReturn('product.position.asc');
        $productSearchResult = Mockery::mock(ProductSearchResult::class);
        $productSearchResult->shouldReceive('getFacetCollection')
            ->once()
            ->andReturn($this->facetCollection);
        $productSearchResult->shouldReceive('getCurrentSortOrder')
            ->once()
            ->andReturn($sortOrder);

        $facet = $this->mockFacet('Test');
        $this->facetCollection->shouldReceive('getFacets')
            ->once()
            ->andReturn(
                [
                    $facet,
                ]
            );

        $this->module->shouldReceive('fetch')
            ->once()
            ->with(
                'module:ps_facetedsearch/views/templates/front/catalog/facets.tpl'
            )
            ->andReturn('');

        $this->assertEquals(
            '',
            $this->provider->renderFacets(
                $productContext,
                $productSearchResult
            )
        );
    }

    public function testRenderFacetsWithFacetsCollectionAndFilters()
    {
        $productContext = Mockery::mock(ProductSearchContext::class);
        $smarty = Mockery::mock(Smarty::class);
        $smarty->shouldReceive('assign')
            ->once()
            ->with(
                [
                    'show_quantities' => true,
                    'facets' => [
                        [
                            'displayed' => true,
                            'filters' => [
                                [
                                    'label' => 'Men',
                                    'type' => 'category',
                                    'nextEncodedFacets' => 'Categories-Men',
                                    'active' => false,
                                    'facetLabel' => 'Test',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch&q=Categories-Men',
                                ],
                                [
                                    'label' => 'Women',
                                    'type' => 'category',
                                    'nextEncodedFacets' => '',
                                    'active' => true,
                                    'facetLabel' => 'Test',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch&page=1',
                                ],
                            ],
                        ],
                        [
                            'displayed' => true,
                            'filters' => [
                                [
                                    'label' => '£22.00 - £35.00',
                                    'type' => 'price',
                                    'active' => false,
                                    'displayed' => true,
                                    'properties' => [],
                                    'magnitude' => 2,
                                    'value' => 0,
                                    'nextEncodedFacets' => '',
                                    'facetLabel' => 'Price',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch',
                                ],
                            ],
                        ],
                    ],
                    'js_enabled' => true,
                    'displayedFacets' => [
                        [
                            'displayed' => true,
                            'filters' => [
                                [
                                    'label' => 'Men',
                                    'type' => 'category',
                                    'nextEncodedFacets' => 'Categories-Men',
                                    'active' => false,
                                    'facetLabel' => 'Test',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch&q=Categories-Men',
                                ],
                                [
                                    'label' => 'Women',
                                    'type' => 'category',
                                    'nextEncodedFacets' => '',
                                    'active' => true,
                                    'facetLabel' => 'Test',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch&page=1',
                                ],
                            ],
                        ],
                        [
                            'displayed' => true,
                            'filters' => [
                                [
                                    'label' => '£22.00 - £35.00',
                                    'type' => 'price',
                                    'active' => false,
                                    'displayed' => true,
                                    'properties' => [],
                                    'magnitude' => 2,
                                    'value' => 0,
                                    'nextEncodedFacets' => '',
                                    'facetLabel' => 'Price',
                                    'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch',
                                ],
                            ],
                        ],
                    ],
                    'activeFilters' => [
                        [
                            'label' => 'Women',
                            'type' => 'category',
                            'nextEncodedFacets' => '',
                            'active' => true,
                            'facetLabel' => 'Test',
                            'nextEncodedFacetsURL' => 'http://shop.prestashop.com/catalog?from=scratch&page=1',
                        ],
                    ],
                    'sort_order' => 'product.position.asc',
                    'clear_all_link' => 'http://shop.prestashop.com/catalog?from=scratch',
                ]
            );
        $this->context->smarty = $smarty;
        $sortOrder = Mockery::mock(SortOrder::class);
        $sortOrder->shouldReceive('toString')
            ->once()
            ->andReturn('product.position.asc');
        $productSearchResult = Mockery::mock(ProductSearchResult::class);
        $productSearchResult->shouldReceive('getFacetCollection')
            ->once()
            ->andReturn($this->facetCollection);
        $productSearchResult->shouldReceive('getCurrentSortOrder')
            ->once()
            ->andReturn($sortOrder);

        $facet = $this->mockFacet(
            'Test',
            [
                'displayed' => true,
                'filters' => [
                    [
                        'label' => 'Men',
                        'type' => 'category',
                        'nextEncodedFacets' => 'Categories-Men',
                        'active' => false,
                    ],
                    [
                        'label' => 'Women',
                        'type' => 'category',
                        'nextEncodedFacets' => '',
                        'active' => true,
                    ],
                ],
            ]
        );
        $facetSlider = $this->mockFacet(
            'Price',
            [
                'displayed' => true,
                'filters' => [
                    [
                        'label' => '£22.00 - £35.00',
                        'type' => 'price',
                        'active' => false,
                        'displayed' => true,
                        'properties' => [],
                        'magnitude' => 2,
                        'value' => 0,
                        'nextEncodedFacets' => '',
                    ],
                ],
            ],
            'slider'
        );
        $this->facetCollection->shouldReceive('getFacets')
            ->once()
            ->andReturn(
                [
                    $facet,
                    $facetSlider,
                ]
            );

        $this->module->shouldReceive('fetch')
            ->once()
            ->with(
                'module:ps_facetedsearch/views/templates/front/catalog/facets.tpl'
            )
            ->andReturn('');

        $this->assertEquals(
            '',
            $this->provider->renderFacets(
                $productContext,
                $productSearchResult
            )
        );
    }
}

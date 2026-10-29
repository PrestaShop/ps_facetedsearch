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

namespace PrestaShop\Module\FacetedSearch\Tests\Hook;

use Context;
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\Hook\ProductSearch;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use Ps_Facetedsearch;

/**
 * A tag search puts its term in the tag, not in the search string. This module's query builder reads
 * only the search string, so taking such a query over would search for an empty term and report that
 * nothing matches. These pin that the query is handed back to the core provider instead.
 */
class ProductSearchTagTest extends MockeryTestCase
{
    private $module;
    private $hook;

    protected function setUp()
    {
        $contextMock = Mockery::mock(Context::class);
        $contextMock->shop = (object) ['id' => 1];

        // Filters ARE configured for the search controller: without this the query would be declined
        // by the pre-existing "no filters" condition and these tests could not tell the two apart.
        $dbMock = Mockery::mock(Db::class);
        $dbMock->shouldReceive('executeS')->andReturn([
            ['type' => 'price', 'id_value' => 0, 'filter_show_limit' => 0, 'filter_type' => 0],
        ]);

        $this->module = Mockery::mock(Ps_Facetedsearch::class);
        $this->module->shouldReceive('getContext')->andReturn($contextMock);
        $this->module->shouldReceive('getDatabase')->andReturn($dbMock);

        $this->hook = new ProductSearch($this->module);
    }

    private function query($queryType, $searchString, $searchTag)
    {
        $query = Mockery::mock(ProductSearchQuery::class);
        $query->shouldReceive('getQueryType')->andReturn($queryType);
        $query->shouldReceive('getSearchString')->andReturn($searchString);
        $query->shouldReceive('getSearchTag')->andReturn($searchTag);

        return $query;
    }

    public function testATagOnlySearchIsHandedBackToTheCoreProvider()
    {
        $this->module->shouldReceive('isControllerSupported')->andReturn(true);

        $provider = $this->hook->productSearchProvider(
            ['query' => $this->query('search', '', 'Foo')]
        );

        $this->assertNull(
            $provider,
            'The module took over a tag search, which its query builder cannot read, so the page would report no results.'
        );
    }

    /**
     * Control: an unsupported controller is declined for its own reason, before any of the above.
     * This passes with or without the tag handling, so it shows the fixture itself is sound.
     */
    public function testAnUnsupportedControllerIsStillDeclined()
    {
        $this->module->shouldReceive('isControllerSupported')->andReturn(false);

        $this->assertNull(
            $this->hook->productSearchProvider(
                ['query' => $this->query('manufacturer', '', 'Foo')]
            )
        );
    }
}

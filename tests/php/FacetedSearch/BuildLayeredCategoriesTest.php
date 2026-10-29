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

namespace PrestaShop\Module\FacetedSearch\Tests;

use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Ps_Facetedsearch;
use Tools;

/**
 * WHY this test exists: buildLayeredCategories() used to TRUNCATE the whole layered_category table
 * unconditionally, then loop through every filter template to repopulate it. On a multi-shop install, one
 * template with a `filters` blob that fails to unserialize left every OTHER shop's facets wiped with
 * nothing to replace them, until the next fully-successful rebuild. These tests pin the fixed behaviour:
 * a shop is only cleared once we've confirmed we can read a template that covers it.
 */
class BuildLayeredCategoriesTest extends MockeryTestCase
{
    private $module;
    private $dbMock;

    protected function setUp()
    {
        $this->dbMock = Mockery::mock(Db::class);
        $this->module = Mockery::mock(Ps_Facetedsearch::class)->makePartial();
        $this->module->shouldReceive('getDatabase')->andReturn($this->dbMock);
        $this->module->shouldReceive('invalidateLayeredFilterBlockCache')->andReturn(true);

        // WHY: Tools is proxied to a mock in this test suite (see MockProxy.php) rather than being the real
        // PrestaShop\Tools class, so Tools::unSerialize must be told what to return per test.
        $toolsMock = Mockery::mock();
        $toolsMock->shouldReceive('unSerialize')
            ->andReturnUsing(function ($value) {
                return @unserialize($value);
            });
        Tools::setStaticExpectations($toolsMock);
    }

    private function validTemplateRow(array $shopList, int $idCategory = 42)
    {
        return [
            'filters' => serialize([
                'shop_list' => $shopList,
                'controllers' => ['category'],
                'categories' => [$idCategory],
                'layered_selection_manufacturer' => [
                    'filter_type' => 0,
                    'filter_show_limit' => 0,
                ],
            ]),
        ];
    }

    public function testAllValidTemplatesClearOnlyTheShopsTheyCover()
    {
        $templates = [
            $this->validTemplateRow([1]),
            $this->validTemplateRow([13]),
        ];

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn($templates);

        // The only DELETE issued must be scoped to exactly the shops covered by valid templates (1 and 13),
        // and must NOT be an unscoped TRUNCATE.
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'DELETE FROM') !== false
                    && strpos($sql, 'TRUNCATE') === false
                    && strpos($sql, 'id_shop IN (1,13)') !== false;
            }))
            ->andReturn(true);

        // The INSERT batch (both templates are valid, so both get repopulated).
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'INSERT INTO') !== false;
            }))
            ->andReturn(true);

        $this->module->buildLayeredCategories();
    }

    public function testOneCorruptTemplateDoesNotTouchOtherShopsData()
    {
        $corruptRow = ['filters' => 'this is not valid serialized php'];
        $validRow = $this->validTemplateRow([13]);

        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn([$corruptRow, $validRow]);

        // Only shop 13 (the shop covered by the VALID template) may be cleared. Shop 1, or any other shop
        // that might have been named inside the corrupt blob had it parsed, must never appear here, and
        // there must be no unscoped TRUNCATE.
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'DELETE FROM') !== false
                    && strpos($sql, 'TRUNCATE') === false
                    && strpos($sql, 'id_shop IN (13)') !== false;
            }))
            ->andReturn(true);

        // Repopulation still happens for the one template that IS readable.
        $this->dbMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function ($sql) {
                return strpos($sql, 'INSERT INTO') !== false;
            }))
            ->andReturn(true);

        $this->module->buildLayeredCategories();
    }

    public function testEveryTemplateCorruptDeletesNothing()
    {
        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn([
                ['filters' => 'garbage'],
                ['filters' => 'also garbage'],
            ]);

        // No shop's template could be read, so nothing is deleted and nothing is inserted: whatever was
        // already in layered_category for every shop is left exactly as it was.
        $this->dbMock->shouldNotReceive('execute');

        $this->module->buildLayeredCategories();
    }

    public function testNoTemplatesAtAllDeletesNothing()
    {
        $this->dbMock->shouldReceive('executeS')
            ->once()
            ->andReturn([]);

        $this->dbMock->shouldNotReceive('execute');

        $this->assertTrue($this->module->buildLayeredCategories());
    }
}

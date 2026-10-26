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
use PrestaShop\Module\FacetedSearch\Hook\SpecificPrice;
use Ps_Facetedsearch;

class SpecificPriceTest extends MockeryTestCase
{
    private $module;
    private $hook;

    protected function setUp()
    {
        $this->module = Mockery::mock(Ps_Facetedsearch::class);
        $this->module->shouldReceive('getDatabase')
            ->andReturn(Mockery::mock(Db::class));
        $this->module->shouldReceive('getContext')
            ->andReturn(Mockery::mock(Context::class));

        $this->hook = new SpecificPrice($this->module);
    }

    public function testRuleUpdateIndexesEachAffectedProductOnce()
    {
        $this->hook->actionObjectSpecificPriceRuleUpdateBefore([
            'object' => $this->mockRule([
                ['id_product' => 1, 'id_product_attribute' => 10],
                ['id_product' => 1, 'id_product_attribute' => 11],
                ['id_product' => 2, 'id_product_attribute' => 20],
            ]),
        ]);

        $this->module->shouldReceive('indexProductPrices')->once()->with(1);
        $this->module->shouldReceive('indexProductPrices')->once()->with(2);
        $this->module->shouldReceive('indexProductPrices')->once()->with(3);
        $this->module->shouldReceive('invalidateLayeredFilterBlockCache')->once();

        $this->hook->actionAdminSpecificPriceRuleControllerSaveAfter([
            'return' => $this->mockRule([
                ['id_product' => 2, 'id_product_attribute' => 21],
                ['id_product' => 3, 'id_product_attribute' => 30],
            ]),
        ]);
    }

    public function testRuleCreationIndexesAffectedProducts()
    {
        $this->module->shouldReceive('indexProductPrices')->once()->with(4);
        $this->module->shouldReceive('invalidateLayeredFilterBlockCache')->once();

        $this->hook->actionAdminSpecificPriceRuleControllerSaveAfter([
            'return' => $this->mockRule([
                ['id_product' => 4, 'id_product_attribute' => null],
            ]),
        ]);
    }

    private function mockRule(array $affectedProducts)
    {
        $rule = Mockery::mock('SpecificPriceRule');
        $rule->id = 1;
        $rule->shouldReceive('getAffectedProducts')->andReturn($affectedProducts);

        return $rule;
    }
}

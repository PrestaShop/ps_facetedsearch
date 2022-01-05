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

use Context;
use Db;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PrestaShop\Module\FacetedSearch\HookDispatcher;
use Ps_Facetedsearch;

class HookDispatcherTest extends MockeryTestCase
{
    private $module;
    private $dispatcher;

    protected function setUp()
    {
        $this->module = Mockery::mock(Ps_Facetedsearch::class);
        $contextMock = Mockery::mock(Context::class);
        $dbMock = Mockery::mock(Db::class);
        $this->module->shouldReceive('getDatabase')
            ->andReturn($dbMock);
        $this->module->shouldReceive('getContext')
            ->andReturn($contextMock);

        $this->dispatcher = new HookDispatcher($this->module);
    }

    public function testGetAvailableHooks()
    {
        $this->assertCount(28, $this->dispatcher->getAvailableHooks());
        $this->assertEquals(
            [
                'actionAttributeGroupDelete',
                'actionAttributeSave',
                'displayAttributeForm',
                'actionAttributePostProcess',
                'actionAttributeGroupDelete',
                'actionAttributeGroupSave',
                'displayAttributeGroupForm',
                'displayAttributeGroupPostProcess',
                'actionCategoryAdd',
                'actionCategoryDelete',
                'actionCategoryUpdate',
                'actionProductPreferencesPageStockSave',
                'displayLeftColumn',
                'actionFeatureSave',
                'actionFeatureDelete',
                'displayFeatureForm',
                'displayFeaturePostProcess',
                'actionFeatureFormBuilderModifier',
                'actionAfterCreateFeatureFormHandler',
                'actionAfterUpdateFeatureFormHandler',
                'actionFeatureValueSave',
                'actionFeatureValueDelete',
                'displayFeatureValueForm',
                'displayFeatureValuePostProcess',
                'actionProductSave',
                'productSearchProvider',
                'actionObjectSpecificPriceRuleUpdateBefore',
                'actionAdminSpecificPriceRuleControllerSaveAfter',
            ],
            $this->dispatcher->getAvailableHooks()
        );
    }

    public function testDispatchUnfoundHook()
    {
        $this->module->shouldReceive('renderWidget')
            ->once()
            ->with('ThisHookDoesNotExists', [])
            ->andReturn('');
        $this->assertEquals('', $this->dispatcher->dispatch('ThisHookDoesNotExists'));
    }
}

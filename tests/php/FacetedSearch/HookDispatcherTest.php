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

namespace PrestaShop\Module\FacetedSearch\Tests;

use Db;
use Context;
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
        $this->assertCount(27, $this->dispatcher->getAvailableHooks());
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

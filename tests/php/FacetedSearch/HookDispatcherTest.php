<?php

namespace PrestaShop\Module\FacetedSearch\Tests;

use Context;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\FacetedSearch\HookDispatcher;
use Ps_FacetedSearch;

class HookDispatcherTest extends TestCase
{
    private $dispatcher;

    protected function setUp()
    {
        $contextMock = $this->getMockBuilder(Context::class)
                     ->setMethods(['getTranslator', 'getContext'])
                     ->disableOriginalConstructor()
                     ->getMock();

        $module = $this->getMockBuilder(Ps_FacetedSearch::class)
                ->setMethods(['getContext', 'getDatabase'])
                ->disableOriginalConstructor()
                ->getMock();

        $module->method('getContext')
            ->willReturn($contextMock);
        $this->dispatcher = new HookDispatcher($module);
    }

    public function testGetAvailableHooks()
    {
        $this->assertCount(22, $this->dispatcher->getAvailableHooks());
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
                'actionFeatureDelete',
                'displayFeatureForm',
                'displayFeaturePostProcess',
                'actionFeatureValueSave',
                'afterDeleteFeatureValue',
                'afterSaveFeatureValue',
                'displayFeatureValueForm',
                'postProcessFeatureValue',
                'actionProductSave',
                'productSearchProvider'
            ],
            $this->dispatcher->getAvailableHooks()
        );
    }

    public function testDispatchUnfoundHook()
    {
        $this->assertNull($this->dispatcher->dispatch('ThisHookDoesNotExists'));
    }
}

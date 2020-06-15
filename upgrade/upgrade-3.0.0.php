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
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_0_0(Ps_Facetedsearch $module)
{
    // Clear legacy hook names
    $oldHooks = [
        'categoryAddition',
        'categoryUpdate',
        'attributeGroupForm',
        'afterSaveAttributeGroup',
        'afterDeleteAttributeGroup',
        'featureForm',
        'afterDeleteFeature',
        'afterSaveFeature',
        'categoryDeletion',
        'afterSaveProduct',
        'postProcessAttributeGroup',
        'postProcessFeature',
        'featureValueForm',
        'postProcessFeatureValue',
        'afterDeleteFeatureValue',
        'afterSaveFeatureValue',
        'attributeForm',
        'postProcessAttribute',
        'afterDeleteAttribute',
        'afterSaveAttribute',
        'productSearchProvider',
        'displayLeftColumn',
    ];

    foreach ($oldHooks as $hookName) {
        $module->unregisterHook($hookName);
    }

    // These methods have no return value
    // If something failed an exception will be raised and
    // the upgrade will stop
    $module->rebuildLayeredStructure();
    $module->rebuildPriceIndexTable();
    $module->invalidateLayeredFilterBlockCache();

    return $module->registerHook($module->getHookDispatcher()->getAvailableHooks());
}

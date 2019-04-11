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

use PrestaShop\Module\FacetedSearch\HookDispatcher;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_0_0($object)
{
    // Clear legacy hook names
    $object->unregisterHook([
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
    ]);

    $hookDispatcher = new HookDispatcher($object);
    $object->registerHook($hookDispatcher->getAvailableHooks());

    return Db::getInstance()->execute(
        'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'layered_filter_block` (
        `hash` CHAR(32) NOT NULL DEFAULT "" PRIMARY KEY,
        `data` TEXT NULL
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
    );
}

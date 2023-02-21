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

function upgrade_module_3_12_0($module)
{
    // Add availabilility to allowed types
    Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'layered_category`
        CHANGE `type` `type` ENUM(\'category\',\'id_feature\',\'id_attribute_group\',\'quantity\',\'availability\',\'condition\',\'manufacturer\',\'weight\',\'price\') NOT NULL;');

    // Upgrade all generated filters
    Db::getInstance()->execute(
        'UPDATE `' . _DB_PREFIX_ . 'layered_category` SET type=\'availability\' WHERE type=\'quantity\';');

    // Remove the old enum from types
    Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'layered_category`
        CHANGE `type` `type` ENUM(\'category\',\'id_feature\',\'id_attribute_group\',\'availability\',\'condition\',\'manufacturer\',\'weight\',\'price\') NOT NULL;');

    // Flush block cache
    $module->invalidateLayeredFilterBlockCache();

    return true;
}

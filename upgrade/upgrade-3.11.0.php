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

function upgrade_module_3_11_0($module)
{
    // Get all filter templates
    $filterTemplates = Db::getInstance()->executeS(
        'SELECT * FROM ' . _DB_PREFIX_ . 'layered_filter'
    );

    // Add controller info to each of the configuration
    if (!empty($filterTemplates)) {
        foreach ($filterTemplates as $template) {
            $filters = Tools::unSerialize($template['filters']);
            $filters['controllers'] = ['category'];
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'layered_filter` 
                SET `filters` = "' . pSQL(serialize($filters)) . '"
                WHERE `id_layered_filter` = ' . (int) $template['id_layered_filter']
            );
        }
    }

    // Add new column to generated filters and fill it with a category controller
    Db::getInstance()->execute('ALTER TABLE `' . _DB_PREFIX_ . 'layered_category` ADD `controller` VARCHAR(64) NOT NULL AFTER `id_shop`;');
    Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . "layered_category` SET `controller`= 'category';");

    // Flush block cache - the cache key changed a bit with this version anyway
    $module->invalidateLayeredFilterBlockCache();

    return true;
}

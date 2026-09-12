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

function upgrade_module_5_1_0(Ps_Facetedsearch $module)
{
    // Extend the price index key while preserving existing rows as group-neutral prices.
    $schemaUpdated = Db::getInstance()->execute(
        'ALTER TABLE `' . _DB_PREFIX_ . 'layered_price_index`
        ADD `id_group` INT NOT NULL DEFAULT 0 AFTER `id_country`,
        DROP PRIMARY KEY,
        ADD PRIMARY KEY (`id_product`, `id_currency`, `id_shop`, `id_country`, `id_group`)'
    );
    if (!$schemaUpdated) {
        return false;
    }

    // Mark the preserved index as stale so a normal full rebuild can compact it with group-aware prices.
    return Configuration::updateGlobalValue('PS_LAYERED_INDEXED', 0);
}

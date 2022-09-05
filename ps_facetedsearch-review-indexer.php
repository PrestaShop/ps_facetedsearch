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
require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/ps_facetedsearch.php';

if (substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('ps_facetedsearch')) {
    exit('Bad token');
}

if (!Module::isEnabled('productcomments')){
    exit('Product Comment module is not installed or is disabled.');
}

Shop::setContext(Shop::CONTEXT_ALL);

$module = new Ps_Facetedsearch();

if (Tools::getValue('full')){
    $module->rebuildCommentIndexTable();
    echo $module->indexReviews(true);
}else{
    echo $module->indexReviews();
}

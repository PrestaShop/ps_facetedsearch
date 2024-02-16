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

/*
 * This standalone endpoint is deprecated, it should not be used anymore and should be removed along with the
 * htaccess file that still allows it to work despite the security policy from the core forbidding this kind
 * of file to be executed.
 */
@trigger_error('This endpoint has been deprecated and will be removed in the next major version for this module, you should rely on Ps_FacetedSearchCronModuleFrontController instead.', E_USER_DEPRECATED);

require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/ps_facetedsearch.php';

if (substr(Tools::hash('ps_facetedsearch/index'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('ps_facetedsearch')) {
    exit('Bad token');
}

Shop::setContext(Shop::CONTEXT_ALL);

$psFacetedsearch = new Ps_Facetedsearch();
$psFacetedsearch->indexAttributes();
$psFacetedsearch->indexFeatures();
$psFacetedsearch->indexAttributeGroup();

echo 1;

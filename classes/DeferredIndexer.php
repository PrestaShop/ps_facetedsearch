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

use PrestaShop\Module\FacetedSearch\Indexation\DeferredQueue;

class PsFacetedsearchDeferredIndexer
{
    /**
     * @return bool
     */
    public static function shouldDefer()
    {
        return (new DeferredQueue())->shouldDefer();
    }

    /**
     * @param int $idProduct
     * @param int $idShop
     *
     * @return bool
     */
    public static function enqueue($idProduct, $idShop = 0)
    {
        return (new DeferredQueue())->enqueue($idProduct, $idShop);
    }

    /**
     * @param Ps_Facetedsearch $module
     * @param int $limit
     *
     * @return int
     */
    public static function process(Ps_Facetedsearch $module, $limit = 50)
    {
        return (new DeferredQueue())->process($module, $limit);
    }
}

<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Ps_FacetedSearchCronModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->ajax = true;
    }

    public function postProcess()
    {
        if (substr(Tools::hash('ps_facetedsearch/index'), 0, 10) != Tools::getValue('token')) {
            header('HTTP/1.1 403 Forbidden');
            header('Status: 403 Forbidden');
            $this->ajaxRender('Bad token');

            return;
        }

        $action = Tools::getValue('action');
        switch ($action) {
            case 'indexAttributes':
                Shop::setContext(Shop::CONTEXT_ALL);

                $psFacetedsearch = new Ps_Facetedsearch();
                $psFacetedsearch->indexAttributes();
                $psFacetedsearch->indexFeatures();
                $psFacetedsearch->indexAttributeGroup();

                $this->ajaxRender('1');
                break;
            case 'clearCache':
                $psFacetedsearch = new Ps_Facetedsearch();
                $this->ajaxRender($psFacetedsearch->invalidateLayeredFilterBlockCache());
                break;
            case 'indexPrices':
                Shop::setContext(Shop::CONTEXT_ALL);

                $module = new Ps_Facetedsearch();
                if (Tools::getValue('full')) {
                    $this->ajaxRender($module->fullPricesIndexProcess((int) Tools::getValue('cursor'), (bool) Tools::getValue('ajax'), true));
                } else {
                    $this->ajaxRender($module->pricesIndexProcess((int) Tools::getValue('cursor'), (bool) Tools::getValue('ajax')));
                }

                break;
            default:
                header('HTTP/1.1 403 Forbidden');
                header('Status: 403 Forbidden');
                $this->ajaxRender('Unknown action');
        }
    }
}

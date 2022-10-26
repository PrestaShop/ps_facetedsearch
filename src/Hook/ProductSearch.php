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

namespace PrestaShop\Module\FacetedSearch\Hook;

use Configuration;
use PrestaShop\Module\FacetedSearch\Filters\Converter;
use PrestaShop\Module\FacetedSearch\Filters\DataAccessor;
use PrestaShop\Module\FacetedSearch\Product\SearchProvider;
use PrestaShop\Module\FacetedSearch\URLSerializer;

class ProductSearch extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'productSearchProvider',
    ];

    /**
     * This method returns the search provider to the controller who requested it.
     * Module currently only supports filtering in categories, so in other cases,
     * we don't return anything.
     *
     * @param array $params
     *
     * @return SearchProvider|null
     */
    public function productSearchProvider(array $params)
    {
        if (!$params['query']->getIdCategory()) {
            return null;
        }

        // Assign assets
        if ((bool) Configuration::get('PS_USE_JQUERY_UI_SLIDER')) {
            $this->context->controller->addJqueryUi('slider');
        }
        $this->context->controller->registerStylesheet(
            'facetedsearch_front',
            '/modules/ps_facetedsearch/views/dist/front.css'
        );
        $this->context->controller->registerJavascript(
            'facetedsearch_front',
            '/modules/ps_facetedsearch/views/dist/front.js',
            ['position' => 'bottom', 'priority' => 100]
        );

        $urlSerializer = new URLSerializer();
        $dataAccessor = new DataAccessor($this->module->getDatabase());

        // Return an instance of our searcher, ready to accept requests
        return new SearchProvider(
            $this->module,
            new Converter(
                $this->module->getContext(),
                $this->module->getDatabase(),
                $urlSerializer,
                $dataAccessor
            ),
            $urlSerializer,
            $dataAccessor
        );
    }
}

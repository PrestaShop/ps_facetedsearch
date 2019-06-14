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

namespace PrestaShop\Module\FacetedSearch\Hook;

use PrestaShop\Module\FacetedSearch\Product\SearchProvider;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\Module\FacetedSearch\Filters\Converter;

class ProductSearch extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'productSearchProvider',
    ];

    /**
     * Hook project search provider
     *
     * @param array $params
     *
     * @return SearchProvider|null
     */
    public function productSearchProvider(array $params)
    {
        $query = $params['query'];
        // do something with query,
        // e.g. use $query->getIdCategory()
        // to choose a template for filters.
        // Query is an instance of:
        // PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery
        if ($query->getIdCategory()) {
            $this->context->controller->addJqueryUi('slider');
            $this->context->controller->registerStylesheet(
                'facetedsearch_front',
                '/modules/ps_facetedsearch/views/dist/front.css'
            );
            $this->context->controller->registerJavascript(
                'facetedsearch_front',
                '/modules/ps_facetedsearch/views/dist/front.js',
                ['position' => 'bottom', 'priority' => 100]
            );

            return new SearchProvider(
                $this->module,
                new Converter(
                    $this->module->getContext(),
                    $this->module->getDatabase()
                ),
                new URLSerializer()
            );
        }

        return null;
    }
}

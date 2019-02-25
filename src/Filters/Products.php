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
namespace PrestaShop\Module\FacetedSearch\Filters;

use PrestaShop\Module\FacetedSearch\Product\Search;
use PrestaShop\Module\FacetedSearch\Adapter\AbstractAdapter;
use Product;
use Validate;
use Configuration;

class Products
{
    /** @var AbstractAdapter */
    private $facetedSearchAdapter;

    public function __construct(Search $productSearch)
    {
        $this->facetedSearchAdapter = $productSearch->getFacetedSearchAdapter();
    }

    /**
     * Remove products from the product list in case of price postFiltering
     *
     * @param array $matchingProductList
     * @param bool $psLayeredFilterPriceUsetax
     * @param bool $psLayeredFilterPriceRounding
     * @param array $priceFilter
     */
    private function filterPrice(
        &$matchingProductList,
        $psLayeredFilterPriceUsetax,
        $psLayeredFilterPriceRounding,
        $priceFilter
    ) {
        /* for this case, price could be out of range, so we need to compute the real price */
        foreach ($matchingProductList as $key => $product) {
            if (($product['price_min'] < (int) $priceFilter['min'] && $product['price_max'] > (int) $priceFilter['min'])
                || ($product['price_max'] > (int) $priceFilter['max'] && $product['price_min'] < (int) $priceFilter['max'])) {
                $price = Product::getPriceStatic($product['id_product'], $psLayeredFilterPriceUsetax);
                if ($psLayeredFilterPriceRounding) {
                    $price = (int) $price;
                }

                if ($price < $priceFilter['min'] || $price > $priceFilter['max']) {
                    // out of range price, exclude the product
                    unset($matchingProductList[$key]);
                }
            }
        }
    }

    /**
     * Get the products associated with the current filters
     *
     * @param int $productsPerPage
     * @param int $page
     * @param string $orderBy
     * @param string $orderWay
     * @param array $selectedFilters
     *
     * @return array
     */
    public function getProductByFilters(
        $productsPerPage,
        $page,
        $orderBy,
        $orderWay,
        $selectedFilters = []
    ) {
        $this->facetedSearchAdapter->setLimit((int) $productsPerPage, ((int) $page - 1) * $productsPerPage);
        $this->facetedSearchAdapter->setOrderField(
            Validate::isOrderBy($orderBy) ? $orderBy : 'position'
        );

        $this->facetedSearchAdapter->setOrderDirection(
            Validate::isOrderWay($orderWay) ? $orderWay : 'ASC'
        );

        $this->facetedSearchAdapter->addGroupBy('id_product');
        if (isset($selectedFilters['price'])) {
            $this->facetedSearchAdapter->addSelectField('id_product');
            $this->facetedSearchAdapter->addSelectField('price');
            $this->facetedSearchAdapter->addSelectField('price_min');
            $this->facetedSearchAdapter->addSelectField('price_max');
        }

        $matchingProductList = $this->facetedSearchAdapter->execute();

        // @TODO: still usefull ?
        // $this->pricePostFiltering($matchingProductList, $selectedFilters);

        $nbrProducts = $this->facetedSearchAdapter->count();

        if ($nbrProducts == 0) {
            $matchingProductList = [];
        }

        return [
            'products' => $matchingProductList,
            'count' => $nbrProducts,
        ];
    }

    /**
     * Post filter product depending on the price and a few extra config variables
     *
     * @param array $matchingProductList
     * @param array $selectedFilters
     */
    private function pricePostFiltering(&$matchingProductList, $selectedFilters)
    {
        if (isset($selectedFilters['price'])) {
            $priceFilter['min'] = (float) ($selectedFilters['price'][0]);
            $priceFilter['max'] = (float) ($selectedFilters['price'][1]);

            static $psLayeredFilterPriceUsetax = null;
            static $psLayeredFilterPriceRounding = null;

            if ($psLayeredFilterPriceUsetax === null) {
                $psLayeredFilterPriceUsetax = Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX');
            }

            if ($psLayeredFilterPriceRounding === null) {
                $psLayeredFilterPriceRounding = Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING');
            }

            if ($psLayeredFilterPriceUsetax || $psLayeredFilterPriceRounding) {
                $this->filterPrice(
                    $matchingProductList,
                    $psLayeredFilterPriceUsetax,
                    $psLayeredFilterPriceRounding,
                    $priceFilter
                );
            }
        }
    }
}

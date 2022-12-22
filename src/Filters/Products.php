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

namespace PrestaShop\Module\FacetedSearch\Filters;

use Configuration;
use PrestaShop\Module\FacetedSearch\Adapter\AbstractAdapter;
use PrestaShop\Module\FacetedSearch\Product\Search;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use Product;
use Validate;

class Products
{
    /**
     * Use price tax filter
     *
     * @var bool
     */
    private $psLayeredFilterPriceUsetax;

    /**
     * Use price rounding
     *
     * @var bool
     */
    private $psLayeredFilterPriceRounding;

    /**
     * @var AbstractAdapter
     */
    private $searchAdapter;

    public function __construct(Search $productSearch)
    {
        $this->searchAdapter = $productSearch->getSearchAdapter();
    }

    /**
     * Get the products associated with the current filters.
     *
     * @param ProductSearchQuery $query
     * @param array $selectedFilters
     *
     * @return array
     */
    public function getProductByFilters(
        ProductSearchQuery $query,
        array $selectedFilters = []
    ) {
        // Get pagination
        $productsPerPage = (int) $query->getResultsPerPage();
        $page = (int) $query->getPage();

        // Load sorting type and direction, validate it and apply fallback if needed
        $orderBy = $query->getSortOrder()->toLegacyOrderBy(false);
        $orderWay = $query->getSortOrder()->toLegacyOrderWay();
        $orderWay = Validate::isOrderWay($orderWay) ? $orderWay : 'ASC';
        $orderBy = Validate::isOrderBy($orderBy) ? $orderBy : 'position';

        $this->searchAdapter->setLimit($productsPerPage, ($page - 1) * $productsPerPage);
        $this->searchAdapter->setOrderField($orderBy);
        $this->searchAdapter->setOrderDirection($orderWay);

        $this->searchAdapter->addGroupBy('id_product');
        if (isset($selectedFilters['price']) || $orderBy === 'price') {
            $this->searchAdapter->addSelectField('id_product');
            $this->searchAdapter->addSelectField('price');
            $this->searchAdapter->addSelectField('price_min');
            $this->searchAdapter->addSelectField('price_max');
        }

        $matchingProductList = $this->searchAdapter->execute();

        $this->pricePostFiltering($matchingProductList, $selectedFilters);

        $nbrProducts = $this->searchAdapter->count();

        if (empty($nbrProducts)) {
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
        if (!isset($selectedFilters['price'])) {
            return;
        }

        $priceFilter['min'] = (float) ($selectedFilters['price'][0]);
        $priceFilter['max'] = (float) ($selectedFilters['price'][1]);

        if ($this->psLayeredFilterPriceUsetax === null) {
            $this->psLayeredFilterPriceUsetax = (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX');
        }

        if ($this->psLayeredFilterPriceRounding === null) {
            $this->psLayeredFilterPriceRounding = (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING');
        }

        if ($this->psLayeredFilterPriceUsetax || $this->psLayeredFilterPriceRounding) {
            $this->filterPrice(
                $matchingProductList,
                $this->psLayeredFilterPriceUsetax,
                $this->psLayeredFilterPriceRounding,
                $priceFilter
            );
        }
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
                || ($product['price_max'] > (int) $priceFilter['max'] && $product['price_min'] < (int) $priceFilter['max'])
            ) {
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
}

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
namespace PrestaShop\Module\FacetedSearch\Product;

use PrestaShop\Module\FacetedSearch\Adapter\MySQL as MySQLAdapter;
use PrestaShop\Module\FacetedSearch\Adapter\AbstractAdapter;
use Configuration;
use Tools;
use Category;
use Context;

class Search
{
    /**
     * @var boolean
     */
    private $psStockManagement;

    /**
     * @var boolean
     */
    private $psOrderOutOfStock;

    /**
     * @var AbstractAdapter
     */
    private $facetedSearchAdapter;

    /**
     * Search constructor.
     *
     * @param string $adapterType
     */
    public function __construct($adapterType = 'MySQL')
    {
        switch ($adapterType) {
            case 'MySQL':
            default:
                $this->facetedSearchAdapter = new MySQLAdapter();
        }

        if ($this->psStockManagement === null) {
            $this->psStockManagement = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
        }

        if ($this->psOrderOutOfStock === null) {
            $this->psOrderOutOfStock = (bool) Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }
    }

    /**
     * @return AbstractAdapter
     */
    public function getFacetedSearchAdapter()
    {
        return $this->facetedSearchAdapter;
    }

    /**
     * Init the initial population of the search filter
     *
     * @param array $facetedSearchFilters
     */
    public function initSearch($facetedSearchFilters)
    {
        $homeCategory = Configuration::get('PS_HOME_CATEGORY');
        /* If the current category isn't defined or if it's homepage, we have nothing to display */
        $idParent = (int) Tools::getValue(
            'id_category',
            Tools::getValue('id_category_layered', $homeCategory)
        );

        $parent = new Category((int) $idParent);

        $context = Context::getContext();
        $idCurrency = (int) $context->currency->id;
        $idShop = (int) $context->shop->id;

        $psLayeredFullTree = Configuration::get('PS_LAYERED_FULL_TREE');
        if (!$psLayeredFullTree) {
            $this->addFilter('id_category_default', $parent->id);
        }

        $this->addSearchFilters(
            $facetedSearchFilters,
            $psLayeredFullTree ? $parent : null,
            $idShop
        );
    }

    /**
     * @param array $selectedFilters
     * @param Category $parent
     * @param int $idShop
     */
    private function addSearchFilters($selectedFilters, $parent, $idShop)
    {
        $hasCategory = false;
        foreach ($selectedFilters as $key => $filterValues) {
            if (!count($filterValues)) {
                continue;
            }

            switch ($key) {
                case 'id_feature':
                    $this->addFilter('id_feature_value', $filterValues);
                    break;

                case 'id_attribute_group':
                    $this->addFilter('id_attribute', $filterValues);
                    break;

                case 'category':
                    $this->addFilter('id_category', $filterValues);
                    $this->facetedSearchAdapter->resetFilter('id_category_default');
                    $hasCategory = true;
                    break;

                case 'quantity':
                    if (count($selectedFilters['quantity']) == 2) {
                        break;
                    }

                    if (!$this->psStockManagement) {
                        $this->facetedSearchAdapter->addFilter('quantity', [0], (!$filterValues[0] ? '<=' : '>'));
                        break;
                    }

                    $this->facetedSearchAdapter->addFilter('quantity', [0], (!$filterValues[0] && !$this->psOrderOutOfStock ? '<=' : '>='));
                    if ($filterValues[0]) {
                        // Filter for available quantity, we must be able to request
                        // product with out_of_stock at 1 or 2
                        // which mean we can buy out of stock products
                        $value = $this->psOrderOutOfStock ? [1, 2] : [1];
                    } else {
                        $value = [0];
                    }

                    $this->facetedSearchAdapter->addFilter(
                        'out_of_stock',
                        $value
                    );
                    break;

                case 'manufacturer':
                    $this->addFilter('id_manufacturer', $filterValues);
                    break;

                case 'condition':
                    if (count($selectedFilters['condition']) == 3) {
                        break;
                    }
                    $this->addFilter('condition', $filterValues);
                    break;

                case 'weight':
                    if (!empty($selectedFilters['weight'][0]) || !empty($selectedFilters['weight'][1])) {
                        $this->facetedSearchAdapter->addFilter(
                            'weight',
                            [(float) $selectedFilters['weight'][0]],
                            '>='
                        );
                        $this->facetedSearchAdapter->addFilter(
                            'weight',
                            [(float) $selectedFilters['weight'][1]],
                            '<='
                        );
                    }
                    break;

                case 'price':
                    if (isset($selectedFilters['price'])
                        && (
                            $selectedFilters['price'][0] !== '' || $selectedFilters['price'][1] !== ''
                        )
                    ) {
                        $this->addPriceFilter(
                            (float) $selectedFilters['price'][0],
                            (float) $selectedFilters['price'][1]
                        );
                    }
                    break;
            }
        }

        if (!$hasCategory && $parent !== null) {
            $this->facetedSearchAdapter->addFilter('nleft', [$parent->nleft], '>=');
            $this->facetedSearchAdapter->addFilter('nright', [$parent->nright], '<=');
        }

        $this->facetedSearchAdapter->addFilter('id_shop', [$idShop]);
        $this->facetedSearchAdapter->addGroupBy('id_product');
        $this->facetedSearchAdapter->useFiltersAsInitialPopulation();
    }

    /**
     * Add a filter with the filterValues extracted from the selectedFilters
     *
     * @param string $filterName
     * @param array $filterValues
     */
    public function addFilter($filterName, $filterValues)
    {
        if (!is_array($filterValues)) {
            $filterValues = [$filterValues];
        }

        $values = [];
        foreach ($filterValues as $filterValue) {
            if (is_array($filterValue)) {
                foreach ($filterValue as $subFilterValue) {
                    $values[] = (int) $subFilterValue;
                }
            } else {
                $values[] = $filterValue;
            }
        }

        if (!empty($values)) {
            $this->facetedSearchAdapter->addFilter($filterName, $values);
        }
    }

    /**
     * Add a price filter
     *
     * @param float $minPrice
     * @param float $maxPrice
     */
    private function addPriceFilter($minPrice, $maxPrice)
    {
        $this->facetedSearchAdapter->addFilter('price_min', [$minPrice], '>=');
        $this->facetedSearchAdapter->addFilter('price_max', [$maxPrice], '<=');
    }
}

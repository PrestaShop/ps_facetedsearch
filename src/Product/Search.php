<?php

namespace PrestaShop\Module\FacetedSearch\Product;

use PrestaShop\Module\FacetedSearch\Product\Search;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL as MySQLAdapter;
use PrestaShop\Module\FacetedSearch\Adapter\AbstractAdapter;
use Configuration;
use Tools;
use Category;
use Context;

class Search
{
    /** @var AbstractAdapter */
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
        $idParent = (int)Tools::getValue('id_category', Tools::getValue('id_category_layered', $homeCategory));

        $parent = new Category((int)$idParent);

        $context = Context::getContext();
        $idCurrency = (int)$context->currency->id;
        $idShop = (int)$context->shop->id;

        $psLayeredFullTree = Configuration::get('PS_LAYERED_FULL_TREE');

        $this->addSearchFilters($facetedSearchFilters, $psLayeredFullTree ? $parent : null, $idShop);
    }

    /**
     * @param array    $selectedFilters
     * @param Category $parent
     * @param int      $idShop
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
                    $hasCategory = true;
                    break;

                case 'quantity':
                    if (count($selectedFilters['quantity']) == 2) {
                        break;
                    }

                    $this->facetedSearchAdapter->addFilter('quantity', 0, (!$filterValues[0] ? '<=' : '>'));
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
                    if ($selectedFilters['weight'][0] != 0 || $selectedFilters['weight'][1] != 0) {
                        $this->facetedSearchAdapter->addFilter('weight',
                            array((float)($selectedFilters['weight'][0] - 0.001)), '>=');
                        $this->facetedSearchAdapter->addFilter('weight',
                            array((float)($selectedFilters['weight'][1] + 0.001)), '<=');
                    }
                    break;

                case 'price':
                    if (isset($selectedFilters['price'])) {
                        if ($selectedFilters['price'][0] !== '' || $selectedFilters['price'][1] !== '') {
                            $this->addPriceFilter((float)$selectedFilters['price'][0],
                                (float)$selectedFilters['price'][1] + 0.001);
                        }
                    }
                    break;
            }
        }
        if (!$hasCategory && $parent) {
            $this->facetedSearchAdapter->addFilter('nleft', array($parent->nleft), '>=');
            $this->facetedSearchAdapter->addFilter('nright', array($parent->nright), '<=');
        }
        $this->facetedSearchAdapter->addFilter('id_shop', array($idShop));
        $this->facetedSearchAdapter->addGroupBy('id_product');
        $this->facetedSearchAdapter->useFiltersAsInitialPopulation();
    }

    /**
     * Add a filter with the filterValues extracted from the selectedFilters
     *
     * @param string $filterName
     * @param array  $filterValues
     */
    public function addFilter($filterName, $filterValues)
    {
        if (!is_array($filterValues)) {
            $filterValues = array($filterValues);
        }
        foreach ($filterValues as $filterValue) {
            $values = array();
            if (is_array($filterValue)) {
                foreach ($filterValue as $subFilterValue) {
                    $values[] = (int)$subFilterValue;
                }
            } else {
                $values[] = (int)$filterValue;
            }
            if ($values) {
                $this->facetedSearchAdapter->addFilter($filterName, $values);
            }
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
        $this->facetedSearchAdapter->addFilter('price_min', array($minPrice - 0.001), '>=');
        $this->facetedSearchAdapter->addFilter('price_max', array($maxPrice + 0.001), '<=');
    }
}

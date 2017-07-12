<?php

namespace NativeModuleFacetedSearchBundle;

use NativeModuleFacetedSearchBundle\Adapter\FacetedSearchMySQLAdapter;
use NativeModuleFacetedSearchBundle\Adapter\FacetedSearchAbstract;
use Configuration;
use Tools;
use Category;
use Context;

class Ps_FacetedsearchProductSearch
{
    /** @var FacetedSearchAbstract */
    private $facetedSearchAdapter;


    public function __construct($adapterType = 'MySQL')
    {
        switch ($adapterType) {
            case 'MySQL':
                $this->facetedSearchAdapter = new FacetedSearchMySQLAdapter();
                break;
            default:
                $this->facetedSearchAdapter = new FacetedSearchMySQLAdapter();
        }
    }

    /**
     * @return FacetedSearchAbstract
     */
    public function getFacetedSearchAdapter()
    {
        return $this->facetedSearchAdapter;
    }

    public function initSearch($facetedSearchFilters)
    {
        $homeCategory = Configuration::get('PS_HOME_CATEGORY');
        /* If the current category isn't defined or if it's homepage, we have nothing to display */
        $idParent = (int) Tools::getValue('id_category', Tools::getValue('id_category_layered', $homeCategory));

        $parent = new Category((int) $idParent);

        $context = Context::getContext();
        $idCurrency = (int) $context->currency->id;
        $idShop = (int) $context->shop->id;

        $psLayeredFullTree = Configuration::get('PS_LAYERED_FULL_TREE');

        $this->addSearchFilters($facetedSearchFilters, $idCurrency, $psLayeredFullTree?$parent:null, $idShop);
    }

    /**
     * @param array $selectedFilters
     * @param int $idCurrency
     * @param Category $parent
     */
    private function addSearchFilters($selectedFilters, $idCurrency, $parent, $idShop)
    {
        $hasCategory = false;
        foreach ($selectedFilters as $key => $filterValues) {
            if (!count($filterValues)) {
                continue;
            }

            preg_match('/^(.*[^_0-9])/', $filterValues, $res);
            $filterValues = $res[1];

            switch ($key) {
                case 'id_feature':
                    $this->addFilter('id_feature_value', $filterValues);
                    break;

                case 'id_attribute_group':
                    $this->addFilter('id_attribute', $filterValues);
                    break;

                case 'category':
                    $this->addFilter('id_category', $filterValues, null);
                    $hasCategory = true;
                    break;

                case 'quantity':
                    if (count($selectedFilters['quantity']) == 2) {
                        break;
                    }

                    $this->facetedSearchAdapter->addFilter('quantity', 0, (!$filterValues[0] ? '<=' : '>'));
                    break;

                case 'manufacturer':
                    $this->addFilter('id_manufacturer', $filterValues, null);
                    break;

                case 'condition':
                    if (count($selectedFilters['condition']) == 3) {
                        break;
                    }
                    $this->addFilter('condition', $filterValues, null);
                    break;

                case 'weight':
                    if ($selectedFilters['weight'][0] != 0 || $selectedFilters['weight'][1] != 0) {
                        $this->facetedSearchAdapter->addFilter('weight',
                            [(float) ($selectedFilters['weight'][0] - 0.001)], '>=');
                        $this->facetedSearchAdapter->addFilter('weight',
                            [(float) ($selectedFilters['weight'][1] + 0.001)], '<=');
                    }
                    break;

                case 'price':
                    if (isset($selectedFilters['price'])) {
                        if ($selectedFilters['price'][0] !== '' || $selectedFilters['price'][1] !== '') {
                            $this->addPriceFilter((float) $selectedFilters['price'][0],
                                (float) $selectedFilters['price'][1] + 0.001, $idCurrency);
                        }
                    }
                    break;
            }
        }
        if (!$hasCategory && $parent) {
            $this->facetedSearchAdapter->addFilter('nleft', [$parent->nleft], '>=');
            $this->facetedSearchAdapter->addFilter('nright', [$parent->nright], '<=');
        }
        $this->facetedSearchAdapter->addFilter('id_shop', [$idShop]);
        $this->facetedSearchAdapter->addGroupBy('id_product');
        $this->facetedSearchAdapter->useFiltersAsInitialPopulation();
    }

    public function addFilter($filterName, $filterValues, $delimiter = '_')
    {
        $values = [];
        if (!is_array($filterValues)) {
            $filterValues = [$filterValues];
        }
        foreach ($filterValues as $filterValue) {
            if ($delimiter && is_array($filterValue)) {
                $filterValue_array = explode($delimiter, $filterValue);
                $values[] = (int)$filterValue_array[1];
            } else {
                $values[] = (int)$filterValue;
            }
        }
        if ($values) {
            $this->facetedSearchAdapter->addFilter($filterName, $values);
        }
    }

    private function addPriceFilter($minPrice, $maxPrice, $idCurrency)
    {
        // @TODO: add price conversion from current currency to default currency
        $this->facetedSearchAdapter->addPriceFilter('price', [$minPrice - 0.001], '>=');
        $this->facetedSearchAdapter->addPriceFilter('price', [$maxPrice + 0.001], '<=');
    }
}

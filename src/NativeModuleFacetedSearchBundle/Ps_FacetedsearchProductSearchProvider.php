<?php

namespace NativeModuleFacetedSearchBundle;

use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use Ps_Facetedsearch;
use Configuration;
use Tools;

class Ps_FacetedsearchProductSearchProvider implements ProductSearchProviderInterface
{
    private $module;
    private $filtersConverter;
    private $facetsSerializer;

    public function __construct(Ps_Facetedsearch $module)
    {
        $this->module = $module;
        $this->filtersConverter = new Ps_FacetedsearchFiltersConverter();
        $this->facetsSerializer = new Ps_FacetedsearchFacetsURLSerializer();
    }

    /**
     * @return array
     */
    private function getAvailableSortOrders()
    {
        $sortPosAsc = new SortOrder('product', 'position', 'asc');
        $sortNameAsc = new SortOrder('product', 'name', 'asc');
        $sortNameDesc = new SortOrder('product', 'name', 'desc');
        $sortPriceAsc = new SortOrder('product', 'price', 'asc');
        $sortPriceDesc = new SortOrder('product', 'price', 'desc');
        return array(
            $sortPosAsc->setLabel(
                $this->module->getTranslator()->trans('Relevance', array(), 'Modules.Facetedsearch.Shop')
            ),
            $sortNameAsc->setLabel(
                $this->module->getTranslator()->trans('Name, A to Z', array(), 'Shop.Theme.Catalog')
            ),
            $sortNameDesc->setLabel(
                $this->module->getTranslator()->trans('Name, Z to A', array(), 'Shop.Theme.Catalog')
            ),
            $sortPriceAsc->setLabel(
                $this->module->getTranslator()->trans('Price, low to high', array(), 'Shop.Theme.Catalog')
            ),
            $sortPriceDesc->setLabel(
                $this->module->getTranslator()->trans('Price, high to low', array(), 'Shop.Theme.Catalog')
            ),
        );
    }

    /**
     * @param ProductSearchContext $context
     * @param ProductSearchQuery   $query
     *
     * @return ProductSearchResult
     */
    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    )
    {
        $result = new ProductSearchResult();
        // extract the filter array from the Search query
        $facetedSearchFilters = $this->filtersConverter->createFacetedSearchFiltersFromQuery($query);

        $facetedSearch = new Ps_FacetedsearchProductSearch();
        // init the search with the initial population associated with the current filters
        $facetedSearch->initSearch($facetedSearchFilters);

        $order_by = $query->getSortOrder()->toLegacyOrderBy(false);
        $order_way = $query->getSortOrder()->toLegacyOrderWay();

        $filterProductSearch = new Ps_FacetedsearchFilterProducts($facetedSearch);

        // get the product associated with the current filter
        $productsAndCount = $filterProductSearch->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $facetedSearchFilters
        );

        $result
            ->setProducts($productsAndCount['products'])
            ->setTotalProductsCount($productsAndCount['count'])
            ->setAvailableSortOrders($this->getAvailableSortOrders());

        // now get the filter blocks associated with the current search
        $filterBlockSearch = new Ps_FacetedsearchFilterBlock($facetedSearch);
        $filterHash = md5(serialize($facetedSearchFilters));

        $filterBlock = $filterBlockSearch->getFromCache($filterHash);
        if (empty($filterBlock)) {
            $filterBlock = $filterBlockSearch->getFilterBlock($productsAndCount['count'], $facetedSearchFilters);
            $filterBlockSearch->insertIntoCache($filterHash, $filterBlock);
        }

        $facets = $this->filtersConverter->getFacetsFromFilterBlocks(
            $filterBlock['filters']
        );

        $this->labelRangeFilters($facets);

        $this->addEncodedFacetsToFilters($facets);

        $this->hideZeroValues($facets);
        $this->hideUselessFacets($facets);

        $facetCollection = new FacetCollection();
        $nextMenu = $facetCollection->setFacets($facets);
        $result->setFacetCollection($nextMenu);
        $result->setEncodedFacets($this->facetsSerializer->serialize($facets));

        return $result;
    }

    /**
     * Add a label associated with the facets
     *
     * @param array $facets
     */
    private function labelRangeFilters(array $facets)
    {
        foreach ($facets as $facet) {
            if ($facet->getType() === 'weight') {
                $unit = Configuration::get('PS_WEIGHT_UNIT');
                foreach ($facet->getFilters() as $filter) {
                    $filterValue = $filter->getValue();
                    $filter->setLabel(
                        sprintf(
                            '%1$s%2$s - %3$s%4$s',
                            Tools::displayNumber($filterValue['from']),
                            $unit,
                            Tools::displayNumber($filterValue['to']),
                            $unit
                        )
                    );
                }
            } elseif ($facet->getType() === 'price') {
                foreach ($facet->getFilters() as $filter) {
                    $filterValue = $filter->getValue();
                    $filter->setLabel(
                        sprintf(
                            '%1$s - %2$s',
                            Tools::displayPrice($filterValue['from']),
                            Tools::displayPrice($filterValue['to'])
                        )
                    );
                }
            }
        }
    }

    /**
     * This method generates a URL stub for each filter inside the given facets
     * and assigns this stub to the filters.
     * The URL stub is called 'nextEncodedFacets' because it is used
     * to generate the URL of the search once a filter is activated.
     */
    private function addEncodedFacetsToFilters(array $facets)
    {
        // first get the currently active facetFilter in an array
        $activeFacetFilters = $this->facetsSerializer->getActiveFacetFiltersFromFacets($facets);
        $urlSerializer = new URLFragmentSerializer();

        foreach ($facets as $facet) {
            // If only one filter can be selected, we keep track of
            // the current active filter to disable it before generating the url stub
            // and not select two filters in a facet that can have only one active filter.
            if (!$facet->isMultipleSelectionAllowed()) {
                foreach ($facet->getFilters() as $filter) {
                    if ($filter->isActive()) {
                        // we have a currently active filter is the facet, remove it from the facetFilter array
                        $activeFacetFilters =
                            $this->facetsSerializer->removeFilterFromFacetFilters($activeFacetFilters, $filter, $facet);
                        break;
                    }
                }
            }

            foreach ($facet->getFilters() as $filter) {
                $facetFilters = $activeFacetFilters;

                // toggle the current filter
                if ($filter->isActive()) {
                    $facetFilters =
                        $this->facetsSerializer->removeFilterFromFacetFilters($facetFilters, $filter, $facet);
                } else {
                    $facetFilters = $this->facetsSerializer->addFilterToFacetFilters($facetFilters, $filter, $facet);
                }

                // We've toggled the filter, so the call to serialize
                // returns the "URL" for the search when user has toggled
                // the filter.
                $filter->setNextEncodedFacets(
                    $urlSerializer->serialize($facetFilters)
                );
            }
        }
    }

    /**
     * Hide entries with 0 results
     *
     * @param array $facets
     */
    private function hideZeroValues(array $facets)
    {
        foreach ($facets as $facet) {
            foreach ($facet->getFilters() as $filter) {
                if ($filter->getMagnitude() === 0) {
                    $filter->setDisplayed(false);
                }
            }
        }
    }

    /**
     * Remove the facet when there's only 1 result
     *
     * @param array $facets
     */
    private function hideUselessFacets(array $facets)
    {
        foreach ($facets as $facet) {
            $usefulFiltersCount = 0;
            foreach ($facet->getFilters() as $filter) {
                if ($filter->getMagnitude() > 0) {
                    ++$usefulFiltersCount;
                }
            }
            $facet->setDisplayed(
                $usefulFiltersCount > 1
            );
        }
    }
}

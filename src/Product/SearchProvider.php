<?php

namespace PrestaShop\Module\FacetedSearch\Product;

use Configuration;
use Context;
use PrestaShop\Module\FacetedSearch\Filters;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use Ps_Facetedsearch;
use Tools;

class SearchProvider implements ProductSearchProviderInterface
{
    private $module;
    private $filtersConverter;
    private $facetsSerializer;

    public function __construct(Ps_Facetedsearch $module)
    {
        $this->module = $module;
        $this->filtersConverter = new Filters\Converter();
        $this->facetsSerializer = new URLSerializer();
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
        $translator = $this->module->getTranslator();

        return [
            $sortPosAsc->setLabel(
                $translator->trans('Relevance', [], 'Modules.Facetedsearch.Shop')
            ),
            $sortNameAsc->setLabel(
                $translator->trans('Name, A to Z', [], 'Shop.Theme.Catalog')
            ),
            $sortNameDesc->setLabel(
                $translator->trans('Name, Z to A', [], 'Shop.Theme.Catalog')
            ),
            $sortPriceAsc->setLabel(
                $translator->trans('Price, low to high', [], 'Shop.Theme.Catalog')
            ),
            $sortPriceDesc->setLabel(
                $translator->trans('Price, high to low', [], 'Shop.Theme.Catalog')
            ),
        ];
    }

    /**
     * @param ProductSearchContext $context
     * @param ProductSearchQuery $query
     *
     * @return ProductSearchResult
     */
    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult();
        // extract the filter array from the Search query
        $facetedSearchFilters = $this->filtersConverter->createFacetedSearchFiltersFromQuery($query);

        $facetedSearch = new Search();
        // init the search with the initial population associated with the current filters
        $facetedSearch->initSearch($facetedSearchFilters);

        $orderBy = $query->getSortOrder()->toLegacyOrderBy(false);
        $orderWay = $query->getSortOrder()->toLegacyOrderWay();

        $filterProductSearch = new Filters\Products($facetedSearch);

        // get the product associated with the current filter
        $productsAndCount = $filterProductSearch->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $orderBy,
            $orderWay,
            $facetedSearchFilters
        );

        $result
            ->setProducts($productsAndCount['products'])
            ->setTotalProductsCount($productsAndCount['count'])
            ->setAvailableSortOrders($this->getAvailableSortOrders());

        // now get the filter blocks associated with the current search
        $filterBlockSearch = new Filters\Block($facetedSearch);

        $currentContext = Context::getContext();
        $idShop = (int) $currentContext->shop->id;
        $idLang = (int) $currentContext->language->id;
        $idCurrency = (int) $currentContext->currency->id;
        $idCountry = (int) $currentContext->country->id;
        $idCategory = (int) $query->getIdCategory();

        $filterHash = md5($idShop . '-' . $idCurrency . '-' . $idLang . '-' . $idCategory .
            '-' . $idCountry . '-' . serialize($facetedSearchFilters));

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

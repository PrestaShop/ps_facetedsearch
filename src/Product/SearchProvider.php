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

namespace PrestaShop\Module\FacetedSearch\Product;

use Configuration;
use PrestaShop\Module\FacetedSearch\Filters;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\FacetsRendererInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;
use Ps_Facetedsearch;
use Tools;

class SearchProvider implements FacetsRendererInterface, ProductSearchProviderInterface
{
    /**
     * @var Ps_Facetedsearch
     */
    private $module;

    /**
     * @var Filters\Converter
     */
    private $filtersConverter;

    /**
     * @var Filters\DataAccessor
     */
    private $dataAccessor;

    /**
     * @var URLSerializer
     */
    private $urlSerializer;

    /**
     * @var SearchFactory
     */
    private $searchFactory;

    public function __construct(
        Ps_Facetedsearch $module,
        Filters\Converter $converter,
        URLSerializer $serializer,
        Filters\DataAccessor $dataAccessor,
        SearchFactory $searchFactory = null
    ) {
        $this->module = $module;
        $this->filtersConverter = $converter;
        $this->urlSerializer = $serializer;
        $this->dataAccessor = $dataAccessor;
        $this->searchFactory = $searchFactory === null ? new SearchFactory() : $searchFactory;
    }

    /**
     * @return array
     */
    private function getAvailableSortOrders()
    {
        $sortSalesDesc = new SortOrder('product', 'sales', 'desc');
        $sortPosAsc = new SortOrder('product', 'position', 'asc');
        $sortNameAsc = new SortOrder('product', 'name', 'asc');
        $sortNameDesc = new SortOrder('product', 'name', 'desc');
        $sortPriceAsc = new SortOrder('product', 'price', 'asc');
        $sortPriceDesc = new SortOrder('product', 'price', 'desc');
        $translator = $this->module->getTranslator();

        return [
            $sortSalesDesc->setLabel(
                $translator->trans('Best sellers', [], 'Modules.Facetedsearch.Shop')
            ),
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

        $context = $this->module->getContext();
        $facetedSearch = $this->searchFactory->build($context);
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
        $filterBlockSearch = new Filters\Block(
            $facetedSearch->getSearchAdapter(),
            $context,
            $this->module->getDatabase(),
            $this->dataAccessor
        );

        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;
        $idCurrency = (int) $context->currency->id;
        $idCountry = (int) $context->country->id;
        $idCategory = (int) $query->getIdCategory();

        $filterHash = md5(
            sprintf(
                '%d-%d-%d-%d-%d-%s',
                $idShop,
                $idCurrency,
                $idLang,
                $idCategory,
                $idCountry,
                serialize($facetedSearchFilters)
            )
        );

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
        $this->hideUselessFacets($facets, (int) $result->getTotalProductsCount());

        $facetCollection = new FacetCollection();
        $nextMenu = $facetCollection->setFacets($facets);
        $result->setFacetCollection($nextMenu);

        $facetFilters = $this->urlSerializer->getActiveFacetFiltersFromFacets($facets);
        $result->setEncodedFacets($this->urlSerializer->serialize($facetFilters));

        return $result;
    }

    /**
     * Renders an product search result.
     *
     * @param ProductSearchContext $context
     * @param ProductSearchResult $result
     *
     * @return string the HTML of the facets
     */
    public function renderFacets(ProductSearchContext $context, ProductSearchResult $result)
    {
        list($activeFilters, $displayedFacets, $facetsVar) = $this->prepareActiveFiltersForRender($context, $result);

        // No need to render without facets
        if (empty($facetsVar)) {
            return '';
        }

        $this->module->getContext()->smarty->assign(
            [
                'show_quantities' => Configuration::get('PS_LAYERED_SHOW_QTIES'),
                'facets' => $facetsVar,
                'js_enabled' => $this->module->isAjax(),
                'displayedFacets' => $displayedFacets,
                'activeFilters' => $activeFilters,
                'sort_order' => $result->getCurrentSortOrder()->toString(),
                'clear_all_link' => $this->updateQueryString(
                    [
                        'q' => null,
                        'page' => null,
                    ]
                ),
            ]
        );

        return $this->module->fetch(
            'module:ps_facetedsearch/views/templates/front/catalog/facets.tpl'
        );
    }

    /**
     * Renders an product search result of active filters.
     *
     * @param ProductSearchContext $context
     * @param ProductSearchResult $result
     *
     * @return string the HTML of the facets
     */
    public function renderActiveFilters(ProductSearchContext $context, ProductSearchResult $result)
    {
        list($activeFilters) = $this->prepareActiveFiltersForRender($context, $result);

        $this->module->getContext()->smarty->assign(
            [
                'activeFilters' => $activeFilters,
                'clear_all_link' => $this->updateQueryString(
                    [
                        'q' => null,
                        'page' => null,
                    ]
                ),
            ]
        );

        return $this->module->fetch(
            'module:ps_facetedsearch/views/templates/front/catalog/active-filters.tpl'
        );
    }

    /**
     * Prepare active filters for renderer.
     *
     * @param ProductSearchContext $context
     * @param ProductSearchResult $result
     *
     * @return array|null
     */
    private function prepareActiveFiltersForRender(ProductSearchContext $context, ProductSearchResult $result)
    {
        $facetCollection = $result->getFacetCollection();

        // not all search providers generate menus
        if (empty($facetCollection)) {
            return null;
        }

        $facetsVar = array_map(
            [$this, 'prepareFacetForTemplate'],
            $facetCollection->getFacets()
        );

        $displayedFacets = [];
        $activeFilters = [];
        foreach ($facetsVar as $idx => $facet) {
            // Remove undisplayed facets
            if (!empty($facet['displayed'])) {
                $displayedFacets[] = $facet;
            }

            // Check if a filter is active
            foreach ($facet['filters'] as $filter) {
                if ($filter['active']) {
                    $activeFilters[] = $filter;
                }
            }
        }

        return [
            $activeFilters,
            $displayedFacets,
            $facetsVar,
        ];
    }

    /**
     * Converts a Facet to an array with all necessary
     * information for templating.
     *
     * @param Facet $facet
     *
     * @return array ready for templating
     */
    protected function prepareFacetForTemplate(Facet $facet)
    {
        $facetsArray = $facet->toArray();
        foreach ($facetsArray['filters'] as &$filter) {
            $filter['facetLabel'] = $facet->getLabel();
            if ($filter['nextEncodedFacets'] || $facet->getWidgetType() === 'slider') {
                $filter['nextEncodedFacetsURL'] = $this->updateQueryString([
                    'q' => $filter['nextEncodedFacets'],
                    'page' => null,
                ]);
            } else {
                $filter['nextEncodedFacetsURL'] = $this->updateQueryString([
                    'q' => null,
                ]);
            }
        }
        unset($filter);

        return $facetsArray;
    }

    /**
     * Add a label associated with the facets
     *
     * @param array $facets
     */
    private function labelRangeFilters(array $facets)
    {
        $context = $this->module->getContext();

        foreach ($facets as $facet) {
            if (!in_array($facet->getType(), Filters\Converter::RANGE_FILTERS)) {
                continue;
            }

            foreach ($facet->getFilters() as $filter) {
                $filterValue = $filter->getValue();
                $min = empty($filterValue[0]) ? $facet->getProperty('min') : $filterValue[0];
                $max = empty($filterValue[1]) ? $facet->getProperty('max') : $filterValue[1];
                if ($facet->getType() === 'weight') {
                    $unit = Configuration::get('PS_WEIGHT_UNIT');
                    $filter->setLabel(
                        sprintf(
                            '%1$s%2$s - %3$s%4$s',
                            Tools::displayNumber($min),
                            $unit,
                            Tools::displayNumber($max),
                            $unit
                        )
                    );
                } elseif ($facet->getType() === 'price') {
                    $filter->setLabel(
                        sprintf(
                            '%1$s - %2$s',
                            $context->getCurrentLocale()->formatPrice($min, $context->currency->iso_code),
                            $context->getCurrentLocale()->formatPrice($max, $context->currency->iso_code)
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
        $originalFacetFilters = $this->urlSerializer->getActiveFacetFiltersFromFacets($facets);

        foreach ($facets as $facet) {
            $activeFacetFilters = $originalFacetFilters;
            // If only one filter can be selected, we keep track of
            // the current active filter to disable it before generating the url stub
            // and not select two filters in a facet that can have only one active filter.
            if (!$facet->isMultipleSelectionAllowed() && !$facet->getProperty('range')) {
                foreach ($facet->getFilters() as $filter) {
                    if ($filter->isActive()) {
                        // we have a currently active filter is the facet, remove it from the facetFilter array
                        $activeFacetFilters = $this->urlSerializer->removeFilterFromFacetFilters(
                            $originalFacetFilters,
                            $filter,
                            $facet
                        );
                        break;
                    }
                }
            }

            foreach ($facet->getFilters() as $filter) {
                // toggle the current filter
                if ($filter->isActive() || $facet->getProperty('range')) {
                    $facetFilters = $this->urlSerializer->removeFilterFromFacetFilters(
                        $activeFacetFilters,
                        $filter,
                        $facet
                    );
                } else {
                    $facetFilters = $this->urlSerializer->addFilterToFacetFilters(
                        $activeFacetFilters,
                        $filter,
                        $facet
                    );
                }

                // We've toggled the filter, so the call to serialize
                // returns the "URL" for the search when user has toggled
                // the filter.
                $filter->setNextEncodedFacets(
                    $this->urlSerializer->serialize($facetFilters)
                );
            }
        }
    }

    /**
     * Remove the facet when there's only 1 result.
     * Keep facet status when it's a slider
     *
     * @param array $facets
     * @param int $totalProducts
     */
    private function hideUselessFacets(array $facets, $totalProducts)
    {
        foreach ($facets as $facet) {
            if ($facet->getWidgetType() === 'slider') {
                $facet->setDisplayed(
                    $facet->getProperty('min') != $facet->getProperty('max')
                );
                continue;
            }

            $totalFacetProducts = 0;
            $usefulFiltersCount = 0;
            foreach ($facet->getFilters() as $filter) {
                if ($filter->getMagnitude() > 0 && $filter->isDisplayed()) {
                    $totalFacetProducts += $filter->getMagnitude();
                    ++$usefulFiltersCount;
                }
            }

            $facet->setDisplayed(
                // There are two filters displayed
                $usefulFiltersCount > 1
                ||
                /*
                 * There is only one fitler and the
                 * magnitude is different than the
                 * total products
                 */
                (
                    count($facet->getFilters()) === 1
                    && $totalFacetProducts < $totalProducts
                    && $usefulFiltersCount > 0
                )
            );
        }
    }

    /**
     * Generate a URL corresponding to the current page but
     * with the query string altered.
     *
     * Params from $extraParams that have a null value are stripped,
     * and other params are added. Params not in $extraParams are unchanged.
     */
    private function updateQueryString(array $extraParams = [])
    {
        $uriWithoutParams = explode('?', $_SERVER['REQUEST_URI'])[0];
        $url = Tools::getCurrentUrlProtocolPrefix() . $_SERVER['HTTP_HOST'] . $uriWithoutParams;
        $params = [];
        $paramsFromUri = '';
        if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
            $paramsFromUri = explode('?', $_SERVER['REQUEST_URI'])[1];
        }
        parse_str($paramsFromUri, $params);

        foreach ($extraParams as $key => $value) {
            if (null === $value) {
                // Force clear param if null value is passed
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        foreach ($params as $key => $param) {
            if (null === $param || '' === $param) {
                unset($params[$key]);
            }
        }

        $queryString = str_replace('%2F', '/', http_build_query($params, '', '&'));

        return $url . ($queryString ? "?$queryString" : '');
    }
}

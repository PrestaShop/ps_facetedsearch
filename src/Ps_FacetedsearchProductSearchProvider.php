<?php

require_once __DIR__.DIRECTORY_SEPARATOR.'Ps_FacetedsearchFiltersConverter.php';
require_once __DIR__.DIRECTORY_SEPARATOR.'Ps_FacetedsearchFacetsURLSerializer.php';

use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\FacetCollection;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\SortOrder;

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

    public function getFacetCollectionFromEncodedFacets(
        ProductSearchQuery $query
    ) {
        // do not compute range filters, all info we need is encoded in $encodedFacets
        $compute_range_filters = false;
        $filterBlock = $this->module->getFilterBlock(
            [],
            $compute_range_filters
        );

        $queryTemplate = $this->filtersConverter->getFacetsFromFacetedSearchFilters(
            $filterBlock['filters']
        );

        $facets = $this->facetsSerializer->setFiltersFromEncodedFacets(
            $queryTemplate,
            $query->getEncodedFacets()
        );

        return (new FacetCollection())->setFacets($facets);
    }

    private function copyFiltersActiveState(
        array $sourceFacets,
        array $targetFacets
    ) {
        $copyByLabel = function (Facet $source, Facet $target) {
            foreach ($target->getFilters() as $targetFilter) {
                foreach ($source->getFilters() as $sourceFilter) {
                    if ($sourceFilter->getLabel() === $targetFilter->getLabel()) {
                        $targetFilter->setActive($sourceFilter->isActive());
                        break;
                    }
                }
            }
        };

        $copyByRangeValue = function (Facet $source, Facet $target) {
            foreach ($source->getFilters() as $sourceFilter) {
                if ($sourceFilter->isActive()) {
                    $foundRange = false;
                    foreach ($target->getFilters() as $targetFilter) {
                        $tFrom = $targetFilter->getValue()['from'];
                        $tTo = $targetFilter->getValue()['to'];
                        $sFrom = $sourceFilter->getValue()['from'];
                        $sTo = $sourceFilter->getValue()['to'];
                        if ($tFrom <= $sFrom && $sTo <= $tTo) {
                            $foundRange = true;
                            $targetFilter->setActive(true);
                            break;
                        }
                    }
                    if (!$foundRange) {
                        $filter = clone $sourceFilter;
                        $filter->setDisplayed(false);
                        $target->addFilter($filter);
                    }
                    break;
                }
            }
        };

        $copy = function (
            Facet $source,
            Facet $target
        ) use (
            $copyByLabel,
            $copyByRangeValue
        ) {
            if ($target->getProperty('range')) {
                $strategy = $copyByRangeValue;
            } else {
                $strategy = $copyByLabel;
            }

            $strategy($source, $target);
        };

        foreach ($targetFacets as $targetFacet) {
            foreach ($sourceFacets as $sourceFacet) {
                if ($sourceFacet->getLabel() === $targetFacet->getLabel()) {
                    $copy($sourceFacet, $targetFacet);
                    break;
                }
            }
        }
    }

    private function getAvailableSortOrders()
    {
        return [
            (new SortOrder('product', 'position', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Relevance', array(), 'Modules.Facetedsearch.Shop')
            ),
            (new SortOrder('product', 'name', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Name, A to Z', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'name', 'desc'))->setLabel(
                $this->module->getTranslator()->trans('Name, Z to A', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'price', 'asc'))->setLabel(
                $this->module->getTranslator()->trans('Price, low to high', array(), 'Shop.Theme.Catalog')
            ),
            (new SortOrder('product', 'price', 'desc'))->setLabel(
                $this->module->getTranslator()->trans('Price, high to low', array(), 'Shop.Theme.Catalog')
            ),
        ];
    }

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult();
        $menu = $this->getFacetCollectionFromEncodedFacets($query);

        $order_by = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way = $query->getSortOrder()->toLegacyOrderWay();

        $facetedSearchFilters = $this->filtersConverter->getFacetedSearchFiltersFromFacets(
            $menu->getFacets()
        );

        $productsAndCount = $this->module->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $context->getIdLang(),
            $facetedSearchFilters
        );

        $result
            ->setProducts($productsAndCount['products'])
            ->setTotalProductsCount($productsAndCount['count'])
            ->setAvailableSortOrders($this->getAvailableSortOrders())
        ;

        $filterBlock = $this->module->getFilterBlock($facetedSearchFilters);
        $facets = $this->filtersConverter->getFacetsFromFacetedSearchFilters(
            $filterBlock['filters']
        );

        $this->copyFiltersActiveState(
            $menu->getFacets(),
            $facets
        );

        $this->labelRangeFilters($facets);

        $this->addEncodedFacetsToFilters($facets);

        $this->hideZeroValues($facets);
        $this->hideUselessFacets($facets);

        $nextMenu = (new FacetCollection())->setFacets($facets);
        $result->setFacetCollection($nextMenu);
        $result->setEncodedFacets($this->facetsSerializer->serialize($facets));

        return $result;
    }

    private function labelRangeFilters(array $facets)
    {
        foreach ($facets as $facet) {
            if ($facet->getType() === 'weight') {
                $unit = Configuration::get('PS_WEIGHT_UNIT');
                foreach ($facet->getFilters() as $filter) {
                    $filter->setLabel(
                        sprintf(
                            '%1$s%2$s - %3$s%4$s',
                            Tools::displayNumber($filter->getValue()['from']),
                            $unit,
                            Tools::displayNumber($filter->getValue()['to']),
                            $unit
                        )
                    );
                }
            } elseif ($facet->getType() === 'price') {
                foreach ($facet->getFilters() as $filter) {
                    $filter->setLabel(
                        sprintf(
                            '%1$s - %2$s',
                            Tools::displayPrice($filter->getValue()['from']),
                            Tools::displayPrice($filter->getValue()['to'])
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
                        $activeFacetFilters = $this->facetsSerializer->removeFilterFromFacetFilters($activeFacetFilters, $filter, $facet);
                        break;
                    }
                }
            }

            foreach ($facet->getFilters() as $filter) {
                $facetFilters = $activeFacetFilters;

                // toggle the current filter
                if ($filter->isActive()) {
                    $facetFilters = $this->facetsSerializer->removeFilterFromFacetFilters($facetFilters, $filter, $facet);
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

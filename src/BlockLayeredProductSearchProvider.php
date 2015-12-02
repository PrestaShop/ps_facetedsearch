<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'BlockLayeredFiltersConverter.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'BlockLayeredFacetsURLSerializer.php';

use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Business\Product\Search\FacetsMenu;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Business\Product\Search\SortOrder;
use PrestaShop\PrestaShop\Core\Business\Product\Search\PaginationResult;

class BlockLayeredProductSearchProvider implements ProductSearchProviderInterface
{
    private $module;
    private $filtersConverter;
    private $facetsSerializer;

    public function __construct(BlockLayered $module)
    {
        $this->module = $module;
        $this->filtersConverter = new BlockLayeredFiltersConverter;
        $this->facetsSerializer = new BlockLayeredFacetsURLSerializer;
    }

    public function getFacetsMenuFromEncodedFacets(
        ProductSearchQuery $query
    ) {
        // do not compute range filters, all info we need is encoded in $encodedFacets
        $compute_range_filters = false;
        $filterBlock    = $this->module->getFilterBlock(
            [],
            $compute_range_filters
        );

        $queryTemplate  = $this->filtersConverter->getFacetsFromBlockLayeredFilters(
            $filterBlock['filters']
        );

        $facets = $this->facetsSerializer->setFiltersFromEncodedFacets(
            $queryTemplate,
            $query->getEncodedFacets()
        );

        return (new FacetsMenu)->setFacets($facets);
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
                        $tTo   = $targetFilter->getValue()['to'];
                        $sFrom = $sourceFilter->getValue()['from'];
                        $sTo   = $sourceFilter->getValue()['to'];
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
                $this->module->l('Relevance')
            ),
            (new SortOrder('product', 'name', 'asc'))->setLabel(
                $this->module->l('Name, A to Z')
            ),
            (new SortOrder('product', 'name', 'desc'))->setLabel(
                $this->module->l('Name, Z to A')
            ),
            (new SortOrder('product', 'price', 'asc'))->setLabel(
                $this->module->l('Price, low to high')
            ),
            (new SortOrder('product', 'price', 'desc'))->setLabel(
                $this->module->l('Price, high to low')
            )
        ];
    }

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;
        $menu   = $this->getFacetsMenuFromEncodedFacets($query);

        $order_by     = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way    = $query->getSortOrder()->toLegacyOrderWay();

        $blockLayeredFilters = $this->filtersConverter->getBlockLayeredFiltersFromFacets(
            $menu->getFacets()
        );

        $productsAndCount = $this->module->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $context->getIdLang(),
            $blockLayeredFilters
        );

        $result->setProducts($productsAndCount['products']);
        $result->setAvailableSortOrders($this->getAvailableSortOrders());

        $pagination = new PaginationResult;
        $pagination
            ->setTotalResultsCount($productsAndCount['count'])
            ->setResultsCount(count($productsAndCount['products']))
            ->setPagesCount(ceil($productsAndCount['count'] / $query->getResultsPerPage()))
            ->setPage($query->getPage())
        ;
        $result->setPaginationResult($pagination);

        $filterBlock = $this->module->getFilterBlock($blockLayeredFilters);
        $facets      = $this->filtersConverter->getFacetsFromBlockLayeredFilters(
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

        $nextMenu = (new FacetsMenu)->setFacets($facets);
        $result->setFacetsMenu($nextMenu);
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
            } else if ($facet->getType() === 'price') {
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
        foreach ($facets as $facet) {

            // If only one filter can be selected, we keep track of
            // the current active filter to disable it before generating the url stub
            // and not select two filters in a facet that can have only one active filter.
            $currentActiveFilter = null;
            if (!$facet->isMultipleSelectionAllowed()) {
                foreach ($facet->getFilters() as $filter) {
                    if ($filter->isActive()) {
                        $currentActiveFilter = $filter;
                    }
                }
            }

            foreach ($facet->getFilters() as $filter) {
                $active = $filter->isActive();
                $filter->setActive(!$active);

                if ($currentActiveFilter) {
                    $currentActiveFilter->setActive(false);
                }

                // We've toggled the filter, so the call to serialize
                // returns the "URL" for the search when user has toggled
                // the filter.
                $filter->setNextEncodedFacets(
                    $this->facetsSerializer->serialize($facets)
                );

                // But we don't want to change the current query,
                // so we toggle the filter back to its original state.
                $filter->setActive($active);

                if ($currentActiveFilter) {
                    $currentActiveFilter->setActive(true);
                }
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

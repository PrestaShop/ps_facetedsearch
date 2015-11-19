<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'BlockLayeredFiltersConverter.php';

use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Business\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Business\Product\Search\PaginationResult;
use PrestaShop\PrestaShop\Core\Business\Product\Search\FacetsURLSerializer;

class BlockLayeredProductSearchProvider implements ProductSearchProviderInterface
{
    private $module;
    private $filtersConverter;

    public function __construct(BlockLayered $module)
    {
        $this->module = $module;
        $this->filtersConverter = new BlockLayeredFiltersConverter;
    }

    public function addFacetsToQuery(
        ProductSearchContext $context,
        $encodedFacets,
        ProductSearchQuery $query
    ) {
        $urlSerializer = new URLFragmentSerializer;
        $facetAndFiltersLabels = $urlSerializer->unserialize($encodedFacets);

        $filterBlock    = $this->module->getFilterBlock();
        $queryTemplate  = $this->filtersConverter->getFacetsFromBlockLayeredFilters(
            $filterBlock['filters']
        );

        // DIRTY, to be refactored later
        foreach ($facetAndFiltersLabels as $facetLabel => $filterLabels) {
            foreach ($queryTemplate as $facet) {
                if ($facet->getLabel() === $facetLabel) {
                    foreach ($filterLabels as $filterLabel) {
                        foreach ($facet->getFilters() as $filter) {
                            if ($filter->getLabel() === $filterLabel) {
                                $filter->setActive(true);
                            }
                        }
                    }
                }
            }
        }

        $query->setFacets($queryTemplate);
    }

    private function copyFiltersActiveState(
        array $sourceFacets,
        array $targetFacets
    ) {
        foreach ($targetFacets as $targetFacet) {
            foreach ($sourceFacets as $sourceFacet) {
                if ($sourceFacet->getLabel() === $targetFacet->getLabel()) {
                    foreach ($targetFacet->getFilters() as $targetFilter) {
                        foreach ($sourceFacet->getFilters() as $sourceFilter) {
                            if ($sourceFilter->getLabel() === $targetFilter->getLabel()) {
                                $targetFilter->setActive($sourceFilter->isActive());
                            }
                        }
                    }
                }
            }
        }
    }

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;
        $facetsSerializer = new FacetsURLSerializer;

        $order_by     = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way    = $query->getSortOrder()->toLegacyOrderWay();

        $blockLayeredFilters = $this->filtersConverter->getBlockLayeredFiltersFromFacets(
            $query->getFacets()
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

        $pagination = new PaginationResult;
        $pagination
            ->setTotalResultsCount($productsAndCount['count'])
            ->setResultsCount(count($productsAndCount['products']))
            ->setPagesCount(ceil($productsAndCount['count'] / $query->getResultsPerPage()))
            ->setPage($query->getPage())
        ;
        $result->setPaginationResult($pagination);

        $filterBlock = $this->module->getFilterBlock();
        $facets      = $this->filtersConverter->getFacetsFromBlockLayeredFilters(
            $filterBlock['filters']
        );

        $this->copyFiltersActiveState(
            $query->getFacets(),
            $facets
        );

        foreach ($facets as $facet) {
            foreach ($facet->getFilters() as $filter) {
                $active = $filter->isActive();
                $filter->setActive(!$active);
                $filter->setNextEncodedFacets($facetsSerializer->serialize($facets));
                $filter->setActive($active);
            }
        }

        $nextQuery   = clone $query;
        $nextQuery->setFacets($facets);
        $result->setNextQuery($nextQuery);

        $result->setEncodedFacets($facetsSerializer->serialize($nextQuery->getFacets()));

        return $result;
    }
}

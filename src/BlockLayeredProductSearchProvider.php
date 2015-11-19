<?php

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

    public function __construct(BlockLayered $module)
    {
        $this->module = $module;
    }

    public function getFacetsFromFilterBlock(array $filterBlock)
    {
        $facets = [];
        foreach ($filterBlock['filters'] as $facetArray) {
            $facet = new Facet;
            $facet
                ->setLabel($facetArray['name'])
                ->setMultipleSelectionAllowed(true)
            ;
            switch ($facetArray['type']) {
                case 'category':
                case 'quantity':
                case 'condition':
                case 'manufacturer':
                case 'id_attribute_group':
                case 'id_feature':
                    $type = $facetArray['type'];
                    if ($facetArray['type'] == 'quantity') {
                        $type = 'availability';
                    } elseif ($facetArray['type'] == 'id_attribute_group') {
                        $type = 'attribute_group';
                    } elseif ($facetArray['type'] == 'id_feature') {
                        $type = 'feature';
                    }
                    $facet->setType($type);
                    foreach ($facetArray['values'] as $id => $filterArray) {
                        $filter = new Filter;
                        $filter
                            ->setType($type)
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue($id)
                        ;
                        $facet->addFilter($filter);
                    }
                    break;
                case 'weight':
                case 'price':
                    $facet->setType($facetArray['type']);
                    $facet->setProperty('min', $facetArray['min']);
                    $facet->setProperty('max', $facetArray['max']);
                    $filter = new Filter;
                    $filter
                        ->setType($facetArray['type'])
                        ->setValue([
                            'from' => $facetArray['values'][0],
                            'to' => $facetArray['values'][1],
                        ])
                    ;
                    break;
            }
            $facets[] = $facet;
        }
        return $facets;
    }

    public function addFacetsToQuery(
        ProductSearchContext $context,
        $encodedFacets,
        ProductSearchQuery $query
    ) {
        // TODO
        $urlSerializer = new URLFragmentSerializer;
        $facetAndFiltersLabels = $urlSerializer->unserialize($encodedFacets);

        $filterBlock    = $this->module->getFilterBlock();
        $queryTemplate  = $this->getFacetsFromFilterBlock($filterBlock);

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

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;

        $order_by     = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way    = $query->getSortOrder()->toLegacyOrderWay();

        $productsAndCount = $this->module->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $context->getIdLang()
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
        $facets      = $this->getFacetsFromFilterBlock($filterBlock);

        $nextQuery   = clone $query;
        $nextQuery->setFacets($facets);
        $result->setNextQuery($nextQuery);

        $facetsSerializer = new FacetsURLSerializer;
        $result->setEncodedFacets($facetsSerializer->serialize($nextQuery->getFacets()));

        return $result;
    }
}

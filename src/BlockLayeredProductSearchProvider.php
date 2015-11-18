<?php

use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchResult;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Filter;

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
            $facet->setLabel($facetArray['name']);
            switch ($facetArray['type']) {
                case 'category':
                case 'availability':
                case 'condition':
                case 'manufacturer':
                    $facet->setType($facetArray['type']);
                    foreach ($facetArray['values'] as $id => $filterArray) {
                        $filter = new Filter;
                        $filter
                            ->setType($facetArray['type'])
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue($id)
                        ;
                        $facet->addFilter($filter);
                    }
                    break;
            }
            $facets[] = $facet;
        }
        return $facets;
    }

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;

        $order_by     = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way    = $query->getSortOrder()->toLegacyOrderWay();

        $products = $this->module->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $context->getIdLang()
        );

        $result->setProducts($products);

        $filterBlock = $this->module->getFilterBlock();
        $facets      = $this->getFacetsFromFilterBlock($filterBlock);

        $nextQuery   = clone $query;
        $nextQuery->setFacets($facets);
        $result->setNextQuery($nextQuery);

        return $result;
    }
}

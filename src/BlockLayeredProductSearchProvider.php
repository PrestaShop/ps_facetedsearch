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
            switch ($facetArray['type']) {
                case 'category':
                    $facet = new Facet;
                    $facet
                        ->setType('category')
                        ->setLabel($facetArray['name'])
                    ;
                    foreach ($facetArray['values'] as $id_category => $filterArray) {
                        $filter = new Filter;
                        $filter
                            ->setType('category')
                            ->setProperty('id_category', (int)$id_category)
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                        ;
                        $facet->addFilter($filter);
                        $facets[] = $facet;
                    }
                break;
            }
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

        $filterBlock = $this->module->getFilterBlock();
        $facets      = $this->getFacetsFromFilterBlock($filterBlock);
        // do something with facets...

        $products = $this->module->getProductByFilters(
            $query->getResultsPerPage(),
            $query->getPage(),
            $order_by,
            $order_way,
            $context->getIdLang()
        );

        $result->setProducts($products);

        return $result;
    }
}

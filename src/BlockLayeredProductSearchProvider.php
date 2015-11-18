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
            $facet->setLabel($filterArray['name']);

            switch ($facetArray['type']) {
                case 'category':
                    $facet->setType('category');
                    foreach ($facetArray['values'] as $id_category => $filterArray) {
                        $filter = new Filter;
                        $filter
                            ->setType('category')
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue((int)$id_category)
                        ;
                        $facet->addFilter($filter);
                    }
                    break;
                case 'availability':
                    $facet->setType('availability')
                    foreach ($facetArray['values'] as $available => $filterArray) {
                        $filter = new Filter;
                        $filter
                            ->setType('availability')
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue((int)$available)
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

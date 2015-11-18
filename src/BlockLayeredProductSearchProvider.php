<?php

use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchProviderInterface;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchContext;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Business\Product\Search\ProductSearchResult;

class BlockLayeredProductSearchProvider implements ProductSearchProviderInterface
{
    private $module;

    public function __construct(BlockLayered $module)
    {
        $this->module = $module;
    }

    public function getFacetsFromFilterBlock(array $filterBlock)
    {
        return '???';
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

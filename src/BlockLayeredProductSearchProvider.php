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

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;

        $products = $this->module->getProductByFilters();

        $result->setProducts($products);

        return $result;
    }
}

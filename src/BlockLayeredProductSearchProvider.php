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

    public function getFacetsFromBlockLayeredFilters(array $blockLayeredFilters)
    {
        $facets = [];
        foreach ($blockLayeredFilters as $facetArray) {
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
                        $facet->setProperty('id_attribute_group', $facetArray['id_key']);
                    } elseif ($facetArray['type'] == 'id_feature') {
                        $type = 'feature';
                        $facet->setProperty('id_feature', $facetArray['id_key']);
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

    public function getBlockLayeredFiltersFromFacets(array $facets)
    {
        $blockLayeredFilters = [];

        foreach ($facets as $facet) {
            switch ($facet->getType()) {
                case 'category':
                case 'availability':
                case 'condition':
                case 'manufacturer':
                case 'attribute_group':
                case 'feature':
                    $type = $facet->getType();
                    if ($type === 'availability') {
                        $type = 'quantity';
                    } elseif ($type === 'attribute_group') {
                        $type = 'id_attribute_group';
                    } elseif ($type === 'feature') {
                        $type = 'id_feature';
                    }
                    $blockLayeredFilters[$type] = [];
                    foreach ($facet->getFilters() as $filter) {
                        if (!$filter->isActive()) {
                            continue;
                        }
                        $key    = count($blockLayeredFilters[$type]);
                        $value  = $filter->getValue();
                        if ($type === 'id_attribute_group') {
                            $key = $value;
                            $value = $facet->getProperty('id_attribute_group').'_'.$filter->getValue();
                        }
                        if ($type === 'id_feature') {
                            $key = $value;
                            $value = $facet->getProperty('id_feature').'_'.$filter->getValue();
                        }
                        $blockLayeredFilters[$type][$key] = $value;
                    }
                    break;
                case 'weight':
                case 'price':
                    $filters = $facet->getFilters();
                    if (!empty($filters)) {
                        $blockLayeredFilters[$facet->getType()] = [
                            $filters[0]->getValue()['from'],
                            $filters[0]->getValue()['to']
                        ];
                    }
                    break;
            }
        }
        return $blockLayeredFilters;
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
        $queryTemplate  = $this->getFacetsFromBlockLayeredFilters(
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

    public function runQuery(
        ProductSearchContext $context,
        ProductSearchQuery $query
    ) {
        $result = new ProductSearchResult;

        $order_by     = $query->getSortOrder()->toLegacyOrderBy(true);
        $order_way    = $query->getSortOrder()->toLegacyOrderWay();

        $blockLayeredFilters = $this->getBlockLayeredFiltersFromFacets(
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
        $facets      = $this->getFacetsFromBlockLayeredFilters(
            $filterBlock['filters']
        );

        $nextQuery   = clone $query;
        $nextQuery->setFacets($facets);
        $result->setNextQuery($nextQuery);

        $facetsSerializer = new FacetsURLSerializer;
        $result->setEncodedFacets($facetsSerializer->serialize($nextQuery->getFacets()));

        return $result;
    }
}

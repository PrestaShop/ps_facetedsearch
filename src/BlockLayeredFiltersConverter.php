<?php

use PrestaShop\PrestaShop\Core\Business\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Filter;

class BlockLayeredFiltersConverter
{
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

    /**
     * WARNING, this is not the inverse function of
     * getFacetsFromBlockLayeredFilters
     * because blocklayered doesn't use the same representation
     * of filters in input as in output.
     * It is close to the inverse function for our use though, hence the name.
     */
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
}

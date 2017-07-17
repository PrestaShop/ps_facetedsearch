<?php
namespace NativeModuleFacetedSearchBundle;

use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;

class Ps_FacetedsearchFacetsURLSerializer
{
    public function addFilterToFacetFilters(array $facetFilters, Filter $facetFilter, $facet)
    {
        if ($facet->getProperty('range')) {
            $facetFilters[$facet->getLabel()] = [
                $facetFilter->getProperty('symbol'),
                $facetFilter->getValue()['from'],
                $facetFilter->getValue()['to'],
            ];
        } else {
            $facetFilters[$facet->getLabel()][$facetFilter->getLabel()] = $facetFilter->getLabel();
        }
        return $facetFilters;
    }

    public function removeFilterFromFacetFilters(array $facetFilters, Filter $facetFilter, $facet)
    {
        if ($facet->getProperty('range')) {
            unset($facetFilters[$facet->getLabel()]);
        } else {
            unset($facetFilters[$facet->getLabel()][$facetFilter->getLabel()]);
            if (empty($facetFilters[$facet->getLabel()])) {
                unset($facetFilters[$facet->getLabel()]);
            }
        }
        return $facetFilters;
    }

    public function getActiveFacetFiltersFromFacets(array $facets)
    {
        $facetFilters = [];
        foreach ($facets as $facet) {
            if ($facet->getProperty('range')) {
                foreach ($facet->getFilters() as $facetFilter) {
                    if ($facetFilter->isActive()) {
                        $facetFilters[$facet->getLabel()] = [
                            $facetFilter->getProperty('symbol'),
                            $facetFilter->getValue()['from'],
                            $facetFilter->getValue()['to'],
                        ];
                    }
                }
            } else {
                foreach ($facet->getFilters() as $facetFilter) {
                    if ($facetFilter->isActive()) {
                        $facetFilters[$facet->getLabel()][$facetFilter->getLabel()] = $facetFilter->getLabel();
                    }
                }
            }
        }

        return $facetFilters;
    }

    public function serialize(array $facets)
    {
        $facetFilters = $this->getActiveFacetFiltersFromFacets($facets);
        $urlSerializer = new URLFragmentSerializer();
        return $urlSerializer->serialize($facetFilters);
    }
}

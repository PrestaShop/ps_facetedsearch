<?php

use PrestaShop\PrestaShop\Core\Business\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Business\Product\Search\Filter;

class BlockLayeredFacetsURLSerializer
{
    public function serialize(array $facets)
    {
        $facetFilters = [];

        $urlSerializer = new URLFragmentSerializer;

        foreach ($facets as $facet) {
            if ($facet->getProperty('range')) {
                foreach ($facet->getFilters() as $facetFilter) {
                    if ($facetFilter->isActive()) {
                        $facetFilters[$facet->getLabel()] = [
                            $facetFilter->getProperty('symbol'),
                            $facetFilter->getValue()['from'],
                            $facetFilter->getValue()['to']
                        ];
                    }
                }
            } else {
                foreach ($facet->getFilters() as $facetFilter) {
                    if ($facetFilter->isActive()) {
                        $facetFilters[$facet->getLabel()][] = $facetFilter->getLabel();
                    }
                }
            }
        }

        return $urlSerializer->serialize($facetFilters);
    }

    public function setFiltersFromEncodedFacets(array $facets, $encodedFacets)
    {
        $urlSerializer = new URLFragmentSerializer;
        $facetAndFiltersLabels = $urlSerializer->unserialize($encodedFacets);

        foreach ($facetAndFiltersLabels as $facetLabel => $filters) {
            foreach ($facets as $facet) {
                if ($facet->getLabel() === $facetLabel) {
                    if (true === $facet->getProperty('range')) {
                        $from   = $filters[1];
                        $to     = $filters[2];
                        $found  = false;

                        foreach ($facet->getFilters() as $filter) {
                            if ($from >= $filter->getValue()['from'] && $to <= $filter->getValue()['to']) {
                                $filter->setActive(true);
                                $found = true;
                            }
                        }

                        if (!$found) {
                            $filter = new Filter;
                            $filter->setValue([
                                'from' => $from,
                                'to'   => $to
                            ]);
                            $filter->setActive(true);
                            $facet->addFilter($filter);
                        }
                      } else {
                        foreach ($filters as $filterLabel) {
                            foreach ($facet->getFilters() as $filter) {
                                if ($filter->getLabel() === $filterLabel) {
                                    $filter->setActive(true);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $facets;
    }
}

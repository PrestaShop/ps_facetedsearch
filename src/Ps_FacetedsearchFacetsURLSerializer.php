<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */


use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;

class Ps_FacetedsearchFacetsURLSerializer
{
    public function addFilterToFacetFilters(array $facetFilters, Filter $facetFilter, $facet) {
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

    public function removeFilterFromFacetFilters(array $facetFilters, Filter $facetFilter, $facet) {
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

    public function getActiveFacetFiltersFromFacets(array $facets) {
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

    public function setFiltersFromEncodedFacets(array $facets, $encodedFacets)
    {
        $urlSerializer = new URLFragmentSerializer();
        $facetAndFiltersLabels = $urlSerializer->unserialize($encodedFacets);

        foreach ($facetAndFiltersLabels as $facetLabel => $filters) {
            foreach ($facets as $facet) {
                if ($facet->getLabel() === $facetLabel) {
                    if (true === $facet->getProperty('range')) {
                        $symbol = $filters[0];
                        $from = $filters[1];
                        $to = $filters[2];
                        $found = false;

                        foreach ($facet->getFilters() as $filter) {
                            if ($from >= $filter->getValue()['from'] && $to <= $filter->getValue()['to']) {
                                $filter->setActive(true);
                                $found = true;
                            }
                        }

                        if (!$found) {
                            $filter = new Filter();
                            $filter->setValue([
                                'from' => $from,
                                'to' => $to,
                            ])->setProperty('symbol', $symbol);
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

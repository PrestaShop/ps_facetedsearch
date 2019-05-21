<?php
/**
 * 2007-2019 PrestaShop.
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
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\FacetedSearch;

use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;

class URLSerializer
{
    /**
     * Add filter
     *
     * @param array $facetFilters
     * @param Filter $facetFilter
     * @param Facet $facet
     *
     * @return array
     */
    public function addFilterToFacetFilters(array $facetFilters, Filter $facetFilter, Facet $facet)
    {
        if ($facet->getProperty('range')) {
            $facetValue = $facet->getProperty('values');
            $facetFilters[$facet->getLabel()] = [
                $facetFilter->getProperty('symbol'),
                isset($facetValue[0]) ? $facetValue[0] : $facet->getProperty('min'),
                isset($facetValue[1]) ? $facetValue[1] : $facet->getProperty('max'),
            ];
        } else {
            $facetFilters[$facet->getLabel()][$facetFilter->getLabel()] = $facetFilter->getLabel();
        }

        return $facetFilters;
    }

    /**
     * Remove filter
     *
     * @param array $facetFilters
     * @param Filter $facetFilter
     * @param Facet $facet
     *
     * @return array
     */
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

    /**
     * Get active facet filters
     *
     * @return array
     */
    public function getActiveFacetFiltersFromFacets(array $facets)
    {
        $facetFilters = [];
        foreach ($facets as $facet) {
            foreach ($facet->getFilters() as $facetFilter) {
                if (!$facetFilter->isActive()) {
                    // Filter is not active
                    continue;
                }

                if (!$facet->getProperty('range')) {
                    $facetFilters[$facet->getLabel()][$facetFilter->getLabel()] = $facetFilter->getLabel();
                } else {
                    $facetValue = $facetFilter->getValue();

                    $facetFilters[$facet->getLabel()] = [
                        $facetFilter->getProperty('symbol'),
                        $facetValue[0],
                        $facetValue[1],
                    ];
                }
            }
        }

        return $facetFilters;
    }

    /**
     * Serialize facets
     *
     * @param array $facets
     *
     * @return string
     */
    public function serialize(array $facets)
    {
        $facetFilters = $this->getActiveFacetFiltersFromFacets($facets);
        $urlSerializer = new URLFragmentSerializer();

        return $urlSerializer->serialize($facetFilters);
    }
}

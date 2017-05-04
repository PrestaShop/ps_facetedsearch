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


use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;

class Ps_FacetedsearchFiltersConverter
{
    public function getFacetsFromFacetedSearchFilters(array $facetedSearchFilters)
    {
        $facets = [];
        foreach ($facetedSearchFilters as $facetArray) {
            $facet = new Facet();
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
                        $filter = new Filter();
                        $filter
                            ->setType($type)
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue($id)
                        ;
                        if (isset($filterArray['color']) && $filterArray['color'] != '') {
                            $filter->setProperty('color', $filterArray['color']);
                        }

                        if (isset($filterArray['url_name']) && $filterArray['url_name'] != '') {
                            $filter->setProperty('texture', _THEME_COL_DIR_.$id.'.jpg');
                        }
                        $facet->addFilter($filter);
                    }
                    break;
                case 'weight':
                case 'price':
                    $facet
                        ->setType($facetArray['type'])
                        ->setProperty('min', $facetArray['min'])
                        ->setProperty('max', $facetArray['max'])
                        ->setMultipleSelectionAllowed(false)
                        ->setProperty('range', true)
                    ;

                    foreach ($facetArray['list_of_values'] as $value) {
                        $filter = new Filter();
                        $filter
                            ->setType($facetArray['type'])
                            ->setMagnitude($value['nbr'])
                            ->setProperty('symbol', $facetArray['unit'])
                            ->setValue([
                                'from' => $value[0],
                                'to' => $value[1],
                            ])
                        ;
                        $facet->addFilter($filter);
                    }

                    break;
            }

            switch ((int) $facetArray['filter_type']) {
                case 0: // checkbox
                    $facet->setMultipleSelectionAllowed(true);
                    $facet->setWidgetType('checkboxes');
                    break;
                case 1: // radio
                    $facet->setMultipleSelectionAllowed(false);
                    $facet->setWidgetType('radio-buttons');
                    break;
                case 2: // drop down
                    $facet->setMultipleSelectionAllowed(false);
                    $facet->setWidgetType('dropdown');
                    break;
            }

            $facets[] = $facet;
        }

        return $facets;
    }

    /**
     * WARNING, this is not the inverse function of
     * getFacetsFromFacetedSearchFilters
     * because facetedsearch doesn't use the same representation
     * of filters in input as in output.
     * It is close to the inverse function for our use though, hence the name.
     */
    public function getFacetedSearchFiltersFromFacets(array $facets)
    {
        $facetedSearchFilters = [];

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
                    if (!isset($facetedSearchFilters[$type])) {
                        $facetedSearchFilters[$type] = [];
                    }
                    foreach ($facet->getFilters() as $filter) {
                        if (!$filter->isActive()) {
                            continue;
                        }
                        $key = count($facetedSearchFilters[$type]);
                        $value = $filter->getValue();
                        if ($type === 'id_attribute_group') {
                            $key = $value;
                            $value = $facet->getProperty('id_attribute_group').'_'.$filter->getValue();
                        }
                        if ($type === 'id_feature') {
                            $key = $value;
                            $value = $facet->getProperty('id_feature').'_'.$filter->getValue();
                        }
                        $facetedSearchFilters[$type][$key] = $value;
                    }
                    break;
                case 'weight':
                case 'price':
                    foreach ($facet->getFilters() as $filter) {
                        if (!$filter->isActive()) {
                            continue;
                        }
                        $facetedSearchFilters[$facet->getType()] = [
                            $filter->getValue()['from'],
                            $filter->getValue()['to'],
                        ];
                        break;
                    }
                    break;
            }
        }

        return $facetedSearchFilters;
    }
}

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
namespace PrestaShop\Module\FacetedSearch\Filters;

use AttributeGroup;
use Category;
use Configuration;
use Context;
use Db;
use Feature;
use FeatureValue;
use Manufacturer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use Tools;

class Converter
{
    const WIDGET_TYPE_CHECKBOX = 0;
    const WIDGET_TYPE_RADIO = 1;
    const WIDGET_TYPE_DROPDOWN = 2;
    const WIDGET_TYPE_SLIDER = 3;

    public function getFacetsFromFilterBlocks(array $filterBlocks)
    {
        $facets = [];

        foreach ($filterBlocks as $filterBlock) {
            if (empty($filterBlock)) {
                // Empty filter, let's continue
                continue;
            }

            $facet = new Facet();
            $facet
                ->setLabel($filterBlock['name'])
                ->setMultipleSelectionAllowed(true);

            switch ($filterBlock['type']) {
                case 'category':
                case 'quantity':
                case 'condition':
                case 'manufacturer':
                case 'id_attribute_group':
                case 'id_feature':
                    $type = $filterBlock['type'];
                    if ($filterBlock['type'] == 'quantity') {
                        $type = 'availability';
                    } elseif ($filterBlock['type'] == 'id_attribute_group') {
                        $type = 'attribute_group';
                        $facet->setProperty('id_attribute_group', $filterBlock['id_key']);
                    } elseif ($filterBlock['type'] == 'id_feature') {
                        $type = 'feature';
                        $facet->setProperty('id_feature', $filterBlock['id_key']);
                    }
                    $facet->setType($type);
                    foreach ($filterBlock['values'] as $id => $filterArray) {
                        $filter = new Filter();
                        $filter
                            ->setType($type)
                            ->setLabel($filterArray['name'])
                            ->setMagnitude($filterArray['nbr'])
                            ->setValue($id);
                        if (array_key_exists('checked', $filterArray)) {
                            $filter->setActive($filterArray['checked']);
                        }
                        if (isset($filterArray['color']) && $filterArray['color'] != '') {
                            $filter->setProperty('color', $filterArray['color']);
                        }

                        if (isset($filterArray['url_name']) && $filterArray['url_name'] != '') {
                            $filter->setProperty('texture', _THEME_COL_DIR_ . $id . '.jpg');
                        }
                        $facet->addFilter($filter);
                    }
                    break;
                case 'weight':
                case 'price':
                    $facet
                        ->setType($filterBlock['type'])
                        ->setProperty('min', $filterBlock['min'])
                        ->setProperty('max', $filterBlock['max'])
                        ->setProperty('unit', $filterBlock['unit'])
                        ->setProperty('format', $filterBlock['format'])
                        ->setProperty('values', $filterBlock['values'])
                        ->setMultipleSelectionAllowed(false)
                        ->setProperty('range', true);

                    foreach ($filterBlock['list_of_values'] as $value) {
                        $filter = new Filter();
                        $filter
                            ->setType($filterBlock['type'])
                            ->setMagnitude($value['nbr'])
                            ->setProperty('symbol', $filterBlock['unit'])
                            ->setValue([
                                'from' => $value['range_start'],
                                'to' => $value['range_end'],
                            ]);

                        $facet->addFilter($filter);
                    }

                    break;
            }

            switch ((int) $filterBlock['filter_type']) {
                case self::WIDGET_TYPE_CHECKBOX:
                    $facet->setMultipleSelectionAllowed(true);
                    $facet->setWidgetType('checkbox');
                    break;
                case self::WIDGET_TYPE_RADIO:
                    $facet->setMultipleSelectionAllowed(false);
                    $facet->setWidgetType('radio');
                    break;
                case self::WIDGET_TYPE_DROPDOWN:
                    $facet->setMultipleSelectionAllowed(false);
                    $facet->setWidgetType('dropdown');
                    break;
                case self::WIDGET_TYPE_SLIDER:
                    $facet->setMultipleSelectionAllowed(false);
                    $facet->setWidgetType('slider');
                    break;
            }

            $facets[] = $facet;
        }

        return $facets;
    }

    /**
     * @param ProductSearchQuery $query
     *
     * @return array
     */
    public function createFacetedSearchFiltersFromQuery(ProductSearchQuery $query)
    {
        $context = Context::getContext();
        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;

        $idParent = $query->getIdCategory();
        if (empty($idParent)) {
            $idParent = (int) Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY'));
        }

        $facetedSearchFilters = [];

        /* Get the filters for the current category */
        $filters = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT type, id_value, filter_show_limit, filter_type FROM ' . _DB_PREFIX_ . 'layered_category
            WHERE id_category = ' . (int) $idParent . '
            AND id_shop = ' . $idShop . '
            GROUP BY `type`, id_value ORDER BY position ASC'
        );

        $urlSerializer = new URLFragmentSerializer();
        $facetAndFiltersLabels = $urlSerializer->unserialize($query->getEncodedFacets());
        foreach ($filters as $filter) {
            $filterLabel = $this->convertFilterTypeToLabel($filter['type']);

            switch ($filter['type']) {
                case 'manufacturer':
                    if (!isset($facetAndFiltersLabels[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $manufacturers = Manufacturer::getManufacturers(false, $idLang);
                    $facetedSearchFilters[$filter['type']] = [];
                    foreach ($manufacturers as $manufacturer) {
                        if (in_array($manufacturer['name'], $facetAndFiltersLabels[$filterLabel])) {
                            $facetedSearchFilters[$filter['type']][$manufacturer['name']] = $manufacturer['id_manufacturer'];
                        }
                    }
                    break;
                case 'quantity':
                    if (!isset($facetAndFiltersLabels[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $quantityArray = [
                        Context::getContext()->getTranslator()->trans(
                            'Not available',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 0,
                        Context::getContext()->getTranslator()->trans(
                            'In stock',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 1,
                    ];

                    $facetedSearchFilters[$filter['type']] = [];
                    foreach ($quantityArray as $quantityName => $quantityId) {
                        if (isset($facetAndFiltersLabels[$filterLabel]) && in_array($quantityName, $facetAndFiltersLabels[$filterLabel])) {
                            $facetedSearchFilters[$filter['type']][] = $quantityId;
                        }
                    }
                    break;
                case 'id_feature':
                    $features = Feature::getFeatures($idLang);
                    foreach ($features as $feature) {
                        if ($filter['id_value'] == $feature['id_feature']
                            && isset($facetAndFiltersLabels[$feature['name']])
                        ) {
                            $featureValueLabels = $facetAndFiltersLabels[$feature['name']];
                            $featureValues = FeatureValue::getFeatureValuesWithLang($idLang, $feature['id_feature']);
                            foreach ($featureValues as $featureValue) {
                                if (in_array($featureValue['value'], $featureValueLabels)) {
                                    $facetedSearchFilters['id_feature'][$feature['id_feature']][] =
                                        $featureValue['id_feature_value'];
                                }
                            }
                        }
                    }
                    break;
                case 'id_attribute_group':
                    $attributesGroup = AttributeGroup::getAttributesGroups($idLang);
                    foreach ($attributesGroup as $attributeGroup) {
                        if ($filter['id_value'] == $attributeGroup['id_attribute_group']
                            && isset($facetAndFiltersLabels[$attributeGroup['name']])
                        ) {
                            $attributeLabels = $facetAndFiltersLabels[$attributeGroup['name']];
                            $attributes = AttributeGroup::getAttributes($idLang, $attributeGroup['id_attribute_group']);
                            foreach ($attributes as $attribute) {
                                if (in_array($attribute['name'], $attributeLabels)) {
                                    $facetedSearchFilters['id_attribute_group'][$attributeGroup['id_attribute_group']][] = $attribute['id_attribute'];
                                }
                            }
                        }
                    }
                    break;
                case 'price':
                case 'weight':
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        $filters = $facetAndFiltersLabels[$filterLabel];
                        $from = $filters[1];
                        $to = $filters[2];
                        $facetedSearchFilters[$filter['type']][0] = $from;
                        $facetedSearchFilters[$filter['type']][1] = $to;
                    }
                    break;
                case 'category':
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        foreach ($facetAndFiltersLabels[$filterLabel] as $queryFilter) {
                            $categories = Category::searchByNameAndParentCategoryId($idLang, $queryFilter, $idParent);
                            if ($categories) {
                                $facetedSearchFilters[$filter['type']][] = $categories['id_category'];
                            }
                        }
                    }
                    break;
                default:
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        foreach ($facetAndFiltersLabels[$filterLabel] as $queryFilter) {
                            $facetedSearchFilters[$filter['type']][] = $queryFilter;
                        }
                    }
            }
        }

        // Remove all empty selected filters
        foreach ($facetedSearchFilters as $key => $value) {
            switch ($key) {
                case 'price':
                case 'weight':
                    if ($value[0] === '' && $value[1] === '') {
                        unset($facetedSearchFilters[$key]);
                    }
                    break;
                default:
                    if ($value == '' || $value == []) {
                        unset($facetedSearchFilters[$key]);
                    }
                    break;
            }
        }

        return $facetedSearchFilters;
    }

    private function convertFilterTypeToLabel($filterType)
    {
        switch ($filterType) {
            case 'price':
                return Context::getContext()->getTranslator()->trans('Price', [], 'Modules.Facetedsearch.Shop');
            case 'weight':
                return Context::getContext()->getTranslator()->trans('Weight', [], 'Modules.Facetedsearch.Shop');
            case 'condition':
                return Context::getContext()->getTranslator()->trans('Condition', [], 'Modules.Facetedsearch.Shop');
            case 'quantity':
                return Context::getContext()->getTranslator()->trans('Availability', [], 'Modules.Facetedsearch.Shop');
            case 'manufacturer':
                return Context::getContext()->getTranslator()->trans('Brand', [], 'Modules.Facetedsearch.Shop');
            case 'category':
                return Context::getContext()->getTranslator()->trans('Categories', [], 'Modules.Facetedsearch.Shop');
            case 'id_feature':
            case 'id_attribute_group':
            default:
                return null;
        }
    }
}

<?php

namespace NativeModuleFacetedSearchBundle;

use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShop\PrestaShop\Core\Product\Search\URLFragmentSerializer;
use Context;
use Tools;
use Feature;
use FeatureValue;
use Configuration;
use AttributeGroup;
use Db;

class Ps_FacetedsearchFiltersConverter
{
    public function getFacetsFromFilterBlocks(array $filterBlocks)
    {
        $facets = [];

        foreach ($filterBlocks as $filterBlock) {
            $facet = new Facet();
            $facet
                ->setLabel($filterBlock['name'])
                ->setMultipleSelectionAllowed(true)
            ;
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
                        ->setType($filterBlock['type'])
                        ->setProperty('min', $filterBlock['min'])
                        ->setProperty('max', $filterBlock['max'])
                        ->setMultipleSelectionAllowed(false)
                        ->setProperty('range', true)
                    ;

                    foreach ($filterBlock['list_of_values'] as $value) {
                        $filter = new Filter();
                        $filter
                            ->setType($filterBlock['type'])
                            ->setMagnitude($value['nbr'])
                            ->setProperty('symbol', $filterBlock['unit'])
                            ->setValue([
                                'from' => $value['range_start'],
                                'to' => $value['range_end'],
                            ])
                        ;
                        $facet->addFilter($filter);
                    }

                    break;
            }

            switch ((int) $filterBlock['filter_type']) {
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
     * @param ProductSearchQuery $query
     * @return array
     */
    public function createFacetedSearchFiltersFromQuery(ProductSearchQuery $query) {
        $context = Context::getContext();
        $idShop = (int) $context->shop->id;
        $idLang = (int) $context->language->id;

        $idParent = $query->getIdCategory();
        if (empty($idParent)) {
            $idParent = (int)Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY'));
        }

        $facetedSearchFilters = [];

        /* Get the filters for the current category */
        $filters = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT type, id_value, filter_show_limit, filter_type FROM '._DB_PREFIX_.'layered_category
			WHERE id_category = '.(int) $idParent.'
				AND id_shop = '.$idShop.'
			GROUP BY `type`, id_value ORDER BY position ASC'
        );

        $urlSerializer = new URLFragmentSerializer();
        $facetAndFiltersLabels = $urlSerializer->unserialize($query->getEncodedFacets());

        foreach($filters as $filter) {
            $filterLabel = $this->convertFilterTypeToLabel($filter['filter_type']);
            switch($filter['filter_type']) {
                case 'id_feature':
                    $features = Feature::getFeatures($idLang);
                    foreach($features as $feature) {
                        if (isset($facetAndFiltersLabels[$feature['name']])) {
                            $featureValueLabels = $facetAndFiltersLabels[$feature['name']];
                            $featureValues =FeatureValue::getFeatureValues($feature['id_feature']);
                            foreach($featureValues as $featureValue) {
                                if (in_array($featureValue['name'], $featureValueLabels)) {
                                    $facetedSearchFilters['id_feature'][$feature['id_feature']] = $featureValue['id_feature_value'];
                                }
                            }
                        }
                    }
                    break;
                case 'id_attribute_group':
                    $attributesGroup = AttributeGroup::getAttributesGroups($idLang);
                    foreach($attributesGroup as $attributeGroup) {
                        if (isset($facetAndFiltersLabels[$attributeGroup['name']])) {
                            $attributeLabels = $facetAndFiltersLabels[$attributeGroup['name']];
                            $attributes = AttributeGroup::getAttributes($idLang, $attributeGroup['id_attribute_group']);
                            foreach($attributes as $attribute) {
                                if (in_array($attribute['name'], $attributeLabels)) {
                                    $facetedSearchFilters['id_attribute_group'][$attributeGroup['id_attribute_group']] = $attribute['id_attribute'];
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
                        $selectedFilters[$filter['filter_type']][0] = $from;
                        $selectedFilters[$filter['filter_type']][1] = $to;
                    }
                    break;
                default:
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        foreach($facetAndFiltersLabels[$filterLabel] as $queryFilter) {
                            $facetedSearchFilters[$filter['filter_type']][] = $queryFilter;
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
                    if ($value == '' || $value == array()) {
                        unset($facetedSearchFilters[$key]);
                    }
                    break;
            }
        }

        return $facetedSearchFilters;
    }

    private function convertFilterTypeToLabel($filterType)
    {
        switch($filterType) {
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

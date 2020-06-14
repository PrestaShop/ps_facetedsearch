<?php
/**
 * 2007-2020 PrestaShop and Contributors
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
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

    const TYPE_ATTRIBUTE_GROUP = 'id_attribute_group';
    const TYPE_AVAILABILITY = 'availability';
    const TYPE_CATEGORY = 'category';
    const TYPE_CONDITION = 'condition';
    const TYPE_FEATURE = 'id_feature';
    const TYPE_QUANTITY = 'quantity';
    const TYPE_MANUFACTURER = 'manufacturer';
    const TYPE_PRICE = 'price';
    const TYPE_WEIGHT = 'weight';

    /**
     * @var array
     */
    const RANGE_FILTERS = [self::TYPE_PRICE, self::TYPE_WEIGHT];

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Db
     */
    protected $database;

    public function __construct(Context $context, Db $database)
    {
        $this->context = $context;
        $this->database = $database;
    }

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
                ->setProperty('filter_show_limit', $filterBlock['filter_show_limit'])
                ->setMultipleSelectionAllowed(true);

            switch ($filterBlock['type']) {
                case self::TYPE_CATEGORY:
                case self::TYPE_CONDITION:
                case self::TYPE_MANUFACTURER:
                case self::TYPE_QUANTITY:
                case self::TYPE_ATTRIBUTE_GROUP:
                case self::TYPE_FEATURE:
                    $type = $filterBlock['type'];
                    if ($filterBlock['type'] === self::TYPE_QUANTITY) {
                        $type = 'availability';
                    } elseif ($filterBlock['type'] == self::TYPE_ATTRIBUTE_GROUP) {
                        $type = 'attribute_group';
                        $facet->setProperty(self::TYPE_ATTRIBUTE_GROUP, $filterBlock['id_key']);
                    } elseif ($filterBlock['type'] == self::TYPE_FEATURE) {
                        $type = 'feature';
                        $facet->setProperty(self::TYPE_FEATURE, $filterBlock['id_key']);
                    }

                    $facet->setType($type);
                    $filters = [];
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

                        if (isset($filterArray['color'])) {
                            if ($filterArray['color'] != '') {
                                $filter->setProperty('color', $filterArray['color']);
                            } elseif (file_exists(_PS_COL_IMG_DIR_ . $id . '.jpg')) {
                                $filter->setProperty('texture', _THEME_COL_DIR_ . $id . '.jpg');
                            }
                        }

                        $filters[] = $filter;
                    }

                    if ((int) $filterBlock['filter_show_limit'] !== 0) {
                        usort($filters, [$this, 'sortFiltersByMagnitude']);
                    }

                    $this->hideZeroValuesAndShowLimit($filters, (int) $filterBlock['filter_show_limit']);

                    if ((int) $filterBlock['filter_show_limit'] !== 0 || $filterBlock['type'] !== self::TYPE_ATTRIBUTE_GROUP) {
                        usort($filters, [$this, 'sortFiltersByLabel']);
                    }

                    // No method available to add all filters
                    foreach ($filters as $filter) {
                        $facet->addFilter($filter);
                    }
                    break;
                case self::TYPE_WEIGHT:
                case self::TYPE_PRICE:
                    $facet
                        ->setType($filterBlock['type'])
                        ->setProperty('min', $filterBlock['min'])
                        ->setProperty('max', $filterBlock['max'])
                        ->setProperty('unit', $filterBlock['unit'])
                        ->setProperty('specifications', $filterBlock['specifications'])
                        ->setMultipleSelectionAllowed(false)
                        ->setProperty('range', true);

                    $filter = new Filter();
                    $filter
                        ->setActive($filterBlock['value'] !== null)
                        ->setType($filterBlock['type'])
                        ->setMagnitude($filterBlock['nbr'])
                        ->setProperty('symbol', $filterBlock['unit'])
                        ->setValue($filterBlock['value']);

                    $facet->addFilter($filter);

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
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $idParent = $query->getIdCategory();
        if (empty($idParent)) {
            $idParent = (int) Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY'));
        }

        $searchFilters = [];

        /* Get the filters for the current category */
        $filters = $this->database->executeS(
            'SELECT type, id_value, filter_show_limit, filter_type FROM ' . _DB_PREFIX_ . 'layered_category
            WHERE id_category = ' . (int) $idParent . '
            AND id_shop = ' . (int) $idShop . '
            GROUP BY `type`, id_value ORDER BY position ASC'
        );

        $urlSerializer = new URLFragmentSerializer();
        $facetAndFiltersLabels = $urlSerializer->unserialize($query->getEncodedFacets());
        foreach ($filters as $filter) {
            $filterLabel = $this->convertFilterTypeToLabel($filter['type']);

            switch ($filter['type']) {
                case self::TYPE_MANUFACTURER:
                    if (!isset($facetAndFiltersLabels[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $manufacturers = Manufacturer::getManufacturers(false, $idLang);
                    $searchFilters[$filter['type']] = [];
                    foreach ($manufacturers as $manufacturer) {
                        if (in_array($manufacturer['name'], $facetAndFiltersLabels[$filterLabel])) {
                            $searchFilters[$filter['type']][$manufacturer['name']] = $manufacturer['id_manufacturer'];
                        }
                    }
                    break;
                case self::TYPE_QUANTITY:
                    if (!isset($facetAndFiltersLabels[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $quantityArray = [
                        $this->context->getTranslator()->trans(
                            'Not available',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 0,
                        $this->context->getTranslator()->trans(
                            'In stock',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 1,
                    ];

                    $searchFilters[$filter['type']] = [];
                    foreach ($quantityArray as $quantityName => $quantityId) {
                        if (isset($facetAndFiltersLabels[$filterLabel]) && in_array($quantityName, $facetAndFiltersLabels[$filterLabel])) {
                            $searchFilters[$filter['type']][] = $quantityId;
                        }
                    }
                    break;
                case self::TYPE_CONDITION:
                    if (!isset($facetAndFiltersLabels[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $conditionArray = [
                        $this->context->getTranslator()->trans(
                            'New',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 'new',
                        $this->context->getTranslator()->trans(
                            'Used',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 'used',
                        $this->context->getTranslator()->trans(
                            'Refurbished',
                            [],
                            'Modules.Facetedsearch.Shop'
                        ) => 'refurbished',
                    ];

                    $searchFilters[$filter['type']] = [];
                    foreach ($conditionArray as $conditionName => $conditionId) {
                        if (isset($facetAndFiltersLabels[$filterLabel]) && in_array($conditionName, $facetAndFiltersLabels[$filterLabel])) {
                            $searchFilters[$filter['type']][] = $conditionId;
                        }
                    }
                    break;
                case self::TYPE_FEATURE:
                    $features = Feature::getFeatures($idLang);
                    foreach ($features as $feature) {
                        if ($filter['id_value'] == $feature['id_feature']
                            && isset($facetAndFiltersLabels[$feature['name']])
                        ) {
                            $featureValueLabels = $facetAndFiltersLabels[$feature['name']];
                            $featureValues = FeatureValue::getFeatureValuesWithLang($idLang, $feature['id_feature']);
                            foreach ($featureValues as $featureValue) {
                                if (in_array($featureValue['value'], $featureValueLabels)) {
                                    $searchFilters['id_feature'][$feature['id_feature']][] =
                                        $featureValue['id_feature_value'];
                                }
                            }
                        }
                    }
                    break;
                case self::TYPE_ATTRIBUTE_GROUP:
                    $attributesGroup = AttributeGroup::getAttributesGroups($idLang);
                    foreach ($attributesGroup as $attributeGroup) {
                        if ($filter['id_value'] == $attributeGroup['id_attribute_group']
                            && isset($facetAndFiltersLabels[$attributeGroup['public_name']])
                        ) {
                            $attributeLabels = $facetAndFiltersLabels[$attributeGroup['public_name']];
                            $attributes = AttributeGroup::getAttributes($idLang, $attributeGroup['id_attribute_group']);
                            foreach ($attributes as $attribute) {
                                if (in_array($attribute['name'], $attributeLabels)) {
                                    $searchFilters['id_attribute_group'][$attributeGroup['id_attribute_group']][] = $attribute['id_attribute'];
                                }
                            }
                        }
                    }
                    break;
                case self::TYPE_PRICE:
                case self::TYPE_WEIGHT:
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        $filters = $facetAndFiltersLabels[$filterLabel];
                        if (isset($filters[1]) && isset($filters[2])) {
                            $from = $filters[1];
                            $to = $filters[2];
                            $searchFilters[$filter['type']][0] = $from;
                            $searchFilters[$filter['type']][1] = $to;
                        }
                    }
                    break;
                case self::TYPE_CATEGORY:
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        foreach ($facetAndFiltersLabels[$filterLabel] as $queryFilter) {
                            $categories = Category::searchByNameAndParentCategoryId($idLang, $queryFilter, $idParent);
                            if ($categories) {
                                $searchFilters[$filter['type']][] = $categories['id_category'];
                            }
                        }
                    }
                    break;
                default:
                    if (isset($facetAndFiltersLabels[$filterLabel])) {
                        foreach ($facetAndFiltersLabels[$filterLabel] as $queryFilter) {
                            $searchFilters[$filter['type']][] = $queryFilter;
                        }
                    }
            }
        }

        // Remove all empty selected filters
        foreach ($searchFilters as $key => $value) {
            switch ($key) {
                case self::TYPE_PRICE:
                case self::TYPE_WEIGHT:
                    if ($value[0] === '' && $value[1] === '') {
                        unset($searchFilters[$key]);
                    }
                    break;
                default:
                    if ($value == '' || $value == []) {
                        unset($searchFilters[$key]);
                    }
                    break;
            }
        }

        return $searchFilters;
    }

    /**
     * Convert filter type to label
     *
     * @param string $filterType
     */
    private function convertFilterTypeToLabel($filterType)
    {
        switch ($filterType) {
            case self::TYPE_PRICE:
                return $this->context->getTranslator()->trans('Price', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_WEIGHT:
                return $this->context->getTranslator()->trans('Weight', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_CONDITION:
                return $this->context->getTranslator()->trans('Condition', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_QUANTITY:
                return $this->context->getTranslator()->trans('Availability', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_MANUFACTURER:
                return $this->context->getTranslator()->trans('Brand', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_CATEGORY:
                return $this->context->getTranslator()->trans('Categories', [], 'Modules.Facetedsearch.Shop');
            case self::TYPE_FEATURE:
            case self::TYPE_ATTRIBUTE_GROUP:
            default:
                return null;
        }
    }

    /**
     * Hide entries with 0 results
     * Hide depending of show limit parameter
     *
     * @param array $filters
     *
     * @return array
     */
    private function hideZeroValuesAndShowLimit(array $filters, $showLimit)
    {
        $count = 0;
        foreach ($filters as $filter) {
            if ($filter->getMagnitude() === 0
                || ($showLimit > 0 && $count >= $showLimit)
            ) {
                $filter->setDisplayed(false);
                continue;
            }

            ++$count;
        }

        return $filters;
    }

    /**
     * Sort filters by magnitude
     *
     * @param Filter $a
     * @param Filter $b
     *
     * @return int
     */
    private function sortFiltersByMagnitude(Filter $a, Filter $b)
    {
        $aMagnitude = $a->getMagnitude();
        $bMagnitude = $b->getMagnitude();
        if ($aMagnitude == $bMagnitude) {
            // Same magnitude, sort by label
            return $this->sortFiltersByLabel($a, $b);
        }

        return $aMagnitude > $bMagnitude ? -1 : +1;
    }

    /**
     * Sort filters by label
     *
     * @param Filter $a
     * @param Filter $b
     *
     * @return int
     */
    private function sortFiltersByLabel(Filter $a, Filter $b)
    {
        return strnatcmp($a->getLabel(), $b->getLabel());
    }
}

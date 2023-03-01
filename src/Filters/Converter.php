<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\FacetedSearch\Filters;

use Category;
use Context;
use Db;
use Manufacturer;
use PrestaShop\Module\FacetedSearch\Definition\AvailabilityType;
use PrestaShop\Module\FacetedSearch\Filters;
use PrestaShop\Module\FacetedSearch\URLSerializer;
use PrestaShop\PrestaShop\Core\Product\Search\Facet;
use PrestaShop\PrestaShop\Core\Product\Search\Filter;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use Validate;

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
    const TYPE_MANUFACTURER = 'manufacturer';
    const TYPE_PRICE = 'price';
    const TYPE_WEIGHT = 'weight';

    const PROPERTY_URL_NAME = 'url_name';
    const PROPERTY_COLOR = 'color';
    const PROPERTY_TEXTURE = 'texture';

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

    /**
     * @var URLSerializer
     */
    protected $urlSerializer;

    /**
     * @var Filters\DataAccessor
     */
    private $dataAccessor;

    /**
     * @var Filters\Provider
     */
    private $provider;

    public function __construct(
        Context $context,
        Db $database,
        URLSerializer $urlSerializer,
        Filters\DataAccessor $dataAccessor,
        Filters\Provider $provider
    ) {
        $this->context = $context;
        $this->database = $database;
        $this->urlSerializer = $urlSerializer;
        $this->dataAccessor = $dataAccessor;
        $this->provider = $provider;
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
                case self::TYPE_AVAILABILITY:
                case self::TYPE_ATTRIBUTE_GROUP:
                case self::TYPE_FEATURE:
                    $type = $filterBlock['type'];
                    if ($filterBlock['type'] == self::TYPE_ATTRIBUTE_GROUP) {
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
                                $filter->setProperty(self::PROPERTY_COLOR, $filterArray['color']);
                            } elseif (file_exists(_PS_COL_IMG_DIR_ . $id . '.jpg')) {
                                $filter->setProperty(self::PROPERTY_TEXTURE, _THEME_COL_DIR_ . $id . '.jpg');
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
     * This method is responsible of parsing the search filters sent in the query.
     * These filters come from the URL in 99 % of cases.
     *
     * It will unserialize it and convert it to actual unique and valid values that
     * we will later use to construct the database query. All invalid filters in the
     * query (unknown value, deleted in shop etc.) are ignored.
     *
     * Filters that are found (if any) will be later used in initSearch method, along
     * with some predefined ones related the the controller we are on.
     *
     * @param ProductSearchQuery $query
     *
     * @return array
     */
    public function createFacetedSearchFiltersFromQuery(ProductSearchQuery $query)
    {
        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $searchFilters = [];

        // Get filters configured in module settings for the current query
        $configuredFilters = $this->provider->getFiltersForQuery($query, $idShop);

        /*
         * Parses submitted encoded facets from (URL) string into a nice array.
         *
         * Facets are set to the URL with a textual representation. This unfortunately does not
         * work very well, because there could be duplicate values for both facet and filter.
         * For example, if there are two features, feature values or categories with the same name.
         */
        $receivedFilters = $this->urlSerializer->unserialize($query->getEncodedFacets());

        // Go through filters that are configured and find out which should be activated,
        // depending on what was provided in the encodedFacets.
        foreach ($configuredFilters as $filter) {
            $filterLabel = $this->getExpectedIdentifier($filter);

            switch ($filter['type']) {
                case self::TYPE_MANUFACTURER:
                    if (!isset($receivedFilters[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $manufacturers = Manufacturer::getManufacturers(false, $idLang);
                    $searchFilters[$filter['type']] = [];
                    foreach ($manufacturers as $manufacturer) {
                        if (in_array($manufacturer['id_manufacturer'], $receivedFilters[$filterLabel])) {
                            $searchFilters[$filter['type']][] = $manufacturer['id_manufacturer'];
                        }
                    }
                    break;
                case self::TYPE_AVAILABILITY:
                    if (!isset($receivedFilters[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $quantityArray = [
                        AvailabilityType::AVAILABILITY_NOT_AVAILABLE,
                        AvailabilityType::AVAILABILITY_AVAILABLE,
                        AvailabilityType::AVAILABILITY_IN_STOCK,
                    ];
                    $searchFilters[$filter['type']] = [];
                    foreach ($quantityArray as $quantityId) {
                        if (isset($receivedFilters[$filterLabel]) && in_array($quantityId, $receivedFilters[$filterLabel])) {
                            $searchFilters[$filter['type']][] = $quantityId;
                        }
                    }
                    break;
                case self::TYPE_CONDITION:
                    if (!isset($receivedFilters[$filterLabel])) {
                        // No need to filter if no information
                        continue 2;
                    }

                    $conditionArray = ['new', 'used', 'refurbished'];
                    $searchFilters[$filter['type']] = [];
                    foreach ($conditionArray as $conditionId) {
                        if (isset($receivedFilters[$filterLabel]) && in_array($conditionId, $receivedFilters[$filterLabel])) {
                            $searchFilters[$filter['type']][] = $conditionId;
                        }
                    }
                    break;
                case self::TYPE_FEATURE:
                    // Load all features on the shop
                    $features = $this->dataAccessor->getFeatures($idLang);
                    foreach ($features as $feature) {

                        // Check if this filter is the one from the filter
                        if ($filter['id_value'] != $feature['id_feature']) {
                            continue;
                        }

                        // If this feature is in received filters
                        if (isset($receivedFilters[$filterLabel])) {
                            $receivedFilterValues = $receivedFilters[$filterLabel];
                        } else {
                            continue;
                        }

                        // Get all feature values from the shop and check if they are in the received filter values
                        $featureValues = $this->dataAccessor->getFeatureValues($feature['id_feature'], $idLang);
                        foreach ($featureValues as $featureValue) {
                            if (in_array($featureValue['id_feature_value'], $receivedFilterValues)) {
                                $searchFilters['id_feature'][$feature['id_feature']][] = $featureValue['id_feature_value'];
                            }
                        }
                    }
                    break;
                case self::TYPE_ATTRIBUTE_GROUP:
                    // Load all atrributes on the shop
                    $attributesGroup = $this->dataAccessor->getAttributesGroups($idLang);
                    foreach ($attributesGroup as $attributeGroup) {

                        // Check if this attribute is the one from the filter
                        if ($filter['id_value'] != $attributeGroup['id_attribute_group']) {
                            continue;
                        }

                        // If this attribute is in received filters
                        if (isset($receivedFilters[$filterLabel])) {
                            $receivedFilterValues = $receivedFilters[$filterLabel];
                        } else {
                            continue;
                        }

                        // Get all attribute values from the shop and check if they are in the received filter values
                        $attributes = $this->dataAccessor->getAttributes($idLang, $attributeGroup['id_attribute_group']);
                        foreach ($attributes as $attribute) {
                            if (in_array($attribute['id_attribute'], $receivedFilterValues)) {
                                $searchFilters['id_attribute_group'][$attributeGroup['id_attribute_group']][] = $attribute['id_attribute'];
                            }
                        }
                    }
                    break;
                case self::TYPE_PRICE:
                case self::TYPE_WEIGHT:
                    if (isset($receivedFilters[$filterLabel])) {
                        if (isset($receivedFilters[$filterLabel][1]) && isset($receivedFilters[$filterLabel][2])) {
                            $searchFilters[$filter['type']][0] = $receivedFilters[$filterLabel][1];
                            $searchFilters[$filter['type']][1] = $receivedFilters[$filterLabel][2];
                        }
                    }
                    break;
                case self::TYPE_CATEGORY:
                    if (isset($receivedFilters[$filterLabel])) {
                        foreach ($receivedFilters[$filterLabel] as $queryFilter) {
                            $category = new Category($queryFilter, $idLang);
                            if (Validate::isLoadedObject($category)) {
                                $searchFilters[$filter['type']][] = $category->id;
                            }
                        }
                    }
                    break;
                default:
                    if (isset($receivedFilters[$filterLabel])) {
                        foreach ($receivedFilters[$filterLabel] as $queryFilter) {
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
     * @param array $filter
     */
    private function getExpectedIdentifier($filter)
    {
        $filterType = $filter['type'];
        switch ($filterType) {
            case self::TYPE_PRICE:
                return 'price';
            case self::TYPE_WEIGHT:
                return 'weight';
            case self::TYPE_CONDITION:
                return 'condition';
            case self::TYPE_AVAILABILITY:
                return 'availability';
            case self::TYPE_MANUFACTURER:
                return 'manufacturer';
            case self::TYPE_CATEGORY:
                return 'category';
            case self::TYPE_FEATURE:
                return 'feature_' . $filter['id_value'];
            case self::TYPE_ATTRIBUTE_GROUP:
                return 'attribute_group_' . $filter['id_value'];
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
        return strnatcasecmp($a->getLabel(), $b->getLabel());
    }
}

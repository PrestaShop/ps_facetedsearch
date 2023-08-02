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
use Configuration;
use Context;
use Db;
use Feature;
use Group;
use Manufacturer;
use PrestaShop\Module\FacetedSearch\Adapter\InterfaceAdapter;
use PrestaShop\Module\FacetedSearch\Definition\Availability;
use PrestaShop\Module\FacetedSearch\Product\Search;
use PrestaShop\PrestaShop\Core\Localization\Locale;
use PrestaShop\PrestaShop\Core\Localization\Specification\NumberSymbolList;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use PrestaShopDatabaseException;

/**
 * Display filters block on navigation
 */
class Block
{
    /**
     * @var InterfaceAdapter
     */
    private $searchAdapter;

    /**
     * @var bool
     */
    private $psStockManagement;

    /**
     * @var bool
     */
    private $psOrderOutOfStock;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Db
     */
    private $database;

    /**
     * @var array
     */
    private $attributesGroup;

    /**
     * @var DataAccessor
     */
    private $dataAccessor;

    /**
     * @var Provider
     */
    private $provider;

    /**
     * @var ProductSearchQuery
     */
    private $query;

    public function __construct(
        InterfaceAdapter $searchAdapter,
        Context $context,
        Db $database,
        DataAccessor $dataAccessor,
        ProductSearchQuery $query,
        Provider $provider
        ) {
        $this->searchAdapter = $searchAdapter;
        $this->context = $context;
        $this->database = $database;
        $this->dataAccessor = $dataAccessor;
        $this->query = $query;
        $this->provider = $provider;
    }

    /**
     * @param int $nbProducts
     * @param array $selectedFilters
     *
     * @return array
     */
    public function getFilterBlock(
        $nbProducts,
        $selectedFilters
    ) {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;

        // Get category ID from the query or home category as a fallback
        $idCategory = (int) $this->query->getIdCategory();
        if (empty($idCategory)) {
            $idCategory = (int) Configuration::get('PS_HOME_CATEGORY');
        }

        // Get filters configured for the current query
        $filters = $this->provider->getFiltersForQuery($this->query, $idShop);

        $filterBlocks = [];
        // iterate through each filter, and the get corresponding filter block
        foreach ($filters as $filter) {
            switch ($filter['type']) {
                case 'price':
                    $filterBlocks[] = $this->getPriceRangeBlock($filter, $selectedFilters, $nbProducts);
                    break;
                case 'weight':
                    $filterBlocks[] = $this->getWeightRangeBlock($filter, $selectedFilters, $nbProducts);
                    break;
                case 'condition':
                    $filterBlocks[] = $this->getConditionsBlock($filter, $selectedFilters);
                    break;
                case 'availability':
                    $filterBlocks[] = $this->getAvailabilitiesBlock($filter, $selectedFilters);
                    break;
                case 'manufacturer':
                    $filterBlocks[] = $this->getManufacturersBlock($filter, $selectedFilters, $idLang);
                    break;
                case 'id_attribute_group':
                    $filterBlocks =
                        array_merge($filterBlocks, $this->getAttributesBlock($filter, $selectedFilters, $idLang));
                    break;
                case 'id_feature':
                    $filterBlocks =
                        array_merge($filterBlocks, $this->getFeaturesBlock($filter, $selectedFilters, $idLang));
                    break;
                case 'category':
                    $parent = new Category($idCategory, $idLang);
                    $filterBlocks[] = $this->getCategoriesBlock($filter, $selectedFilters, $idLang, $parent);
            }
        }

        return [
            'filters' => $filterBlocks,
        ];
    }

    protected function showPriceFilter()
    {
        return Group::getCurrent()->show_prices;
    }

    /**
     * Get the filter block from the cache table
     *
     * @param string $filterHash
     *
     * @return array|null
     */
    public function getFromCache($filterHash)
    {
        if (!Configuration::get('PS_LAYERED_CACHE_ENABLED')) {
            return null;
        }

        $row = $this->database->getRow(
            'SELECT data FROM ' . _DB_PREFIX_ . 'layered_filter_block WHERE hash="' . pSQL($filterHash) . '"'
        );

        if (!empty($row)) {
            return unserialize(current($row));
        }

        return null;
    }

    /**
     * Insert the filter block into the cache table
     *
     * @param string $filterHash
     * @param array $data
     */
    public function insertIntoCache($filterHash, $data)
    {
        if (!Configuration::get('PS_LAYERED_CACHE_ENABLED')) {
            return;
        }

        try {
            $this->database->execute(
                'REPLACE INTO ' . _DB_PREFIX_ . 'layered_filter_block (hash, data) ' .
                'VALUES ("' . $filterHash . '", "' . pSQL(serialize($data)) . '")'
            );
        } catch (PrestaShopDatabaseException $e) {
            // Don't worry if the cache have invalid or duplicate hash
        }
    }

    /**
     * @param array $filter
     * @param array $selectedFilters
     * @param int $nbProducts
     *
     * @return array
     */
    private function getPriceRangeBlock($filter, $selectedFilters, $nbProducts)
    {
        if (!$this->showPriceFilter()) {
            return [];
        }

        $priceSpecifications = $this->preparePriceSpecifications();
        $priceBlock = [
            'type_lite' => 'price',
            'type' => 'price',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Price', [], 'Modules.Facetedsearch.Shop'),
            'max' => '0',
            'min' => null,
            'unit' => $this->context->currency->sign,
            'specifications' => $priceSpecifications,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => Converter::WIDGET_TYPE_SLIDER,
            'nbr' => $nbProducts,
        ];

        list($priceMinFilter, $priceMaxFilter, $weightFilter) = $this->ignorePriceAndWeightFilters(
            $this->searchAdapter->getInitialPopulation()
        );

        list($priceBlock['min'], $priceBlock['max']) = $this->searchAdapter->getInitialPopulation()->getMinMaxPriceValue();
        $priceBlock['value'] = !empty($selectedFilters['price']) ? $selectedFilters['price'] : null;

        $this->restorePriceAndWeightFilters(
            $this->searchAdapter->getInitialPopulation(),
            $priceMinFilter,
            $priceMaxFilter,
            $weightFilter
        );

        return $priceBlock;
    }

    /**
     * Price / weight filter block should not apply their own filters
     * otherwise they will always disappear if we filter on price / weight
     * because only one choice will remain
     *
     * @param InterfaceAdapter $filteredSearchAdapter
     *
     * @return array
     */
    private function ignorePriceAndWeightFilters(InterfaceAdapter $filteredSearchAdapter)
    {
        // disable the current price and weight filters to compute ranges
        $priceMinFilter = $filteredSearchAdapter->getFilter('price_min');
        $priceMaxFilter = $filteredSearchAdapter->getFilter('price_max');
        $weightFilter = $filteredSearchAdapter->getFilter('weight');
        $filteredSearchAdapter->resetFilter('price_min');
        $filteredSearchAdapter->resetFilter('price_max');
        $filteredSearchAdapter->resetFilter('weight');

        return [
            $priceMinFilter,
            $priceMaxFilter,
            $weightFilter,
        ];
    }

    /**
     * Restore price and weight filters
     *
     * @param InterfaceAdapter $filteredSearchAdapter
     * @param int $priceMinFilter
     * @param int $priceMaxFilter
     * @param int $weightFilter
     */
    private function restorePriceAndWeightFilters(
        $filteredSearchAdapter,
        $priceMinFilter,
        $priceMaxFilter,
        $weightFilter
    ) {
        // put back the price and weight filters
        $filteredSearchAdapter->setFilter('price_min', $priceMinFilter);
        $filteredSearchAdapter->setFilter('price_max', $priceMaxFilter);
        $filteredSearchAdapter->setFilter('weight', $weightFilter);
    }

    /**
     * Get the weight filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     * @param int $nbProducts
     *
     * @return array
     */
    private function getWeightRangeBlock($filter, $selectedFilters, $nbProducts)
    {
        $weightBlock = [
            'type_lite' => 'weight',
            'type' => 'weight',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Weight', [], 'Modules.Facetedsearch.Shop'),
            'max' => '0',
            'min' => null,
            'unit' => Configuration::get('PS_WEIGHT_UNIT'),
            'specifications' => null,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => Converter::WIDGET_TYPE_SLIDER,
            'value' => null,
            'nbr' => $nbProducts,
        ];

        list($priceMinFilter, $priceMaxFilter, $weightFilter) = $this->ignorePriceAndWeightFilters(
            $this->searchAdapter->getInitialPopulation()
        );

        list($weightBlock['min'], $weightBlock['max']) = $this->searchAdapter->getInitialPopulation()->getMinMaxValue('p.weight');
        if (empty($weightBlock['min']) && empty($weightBlock['max'])) {
            // We don't need to continue, no filter available
            return [];
        }

        $weightBlock['value'] = !empty($selectedFilters['weight']) ? $selectedFilters['weight'] : null;

        $this->restorePriceAndWeightFilters(
            $this->searchAdapter->getInitialPopulation(),
            $priceMinFilter,
            $priceMaxFilter,
            $weightFilter
        );

        return $weightBlock;
    }

    /**
     * Get the condition filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     *
     * @return array
     */
    private function getConditionsBlock($filter, $selectedFilters)
    {
        $conditionArray = [
            'new' => [
                'name' => $this->context->getTranslator()->trans(
                    'New',
                    [],
                    'Modules.Facetedsearch.Shop'
                ),
                'nbr' => 0,
            ],
            'used' => [
                'name' => $this->context->getTranslator()->trans(
                    'Used',
                    [],
                    'Modules.Facetedsearch.Shop'
                ),
                'nbr' => 0,
            ],
            'refurbished' => [
                'name' => $this->context->getTranslator()->trans(
                    'Refurbished',
                    [],
                    'Modules.Facetedsearch.Shop'
                ),
                'nbr' => 0,
            ],
        ];
        $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter('condition');
        $results = $filteredSearchAdapter->valueCount('condition');
        foreach ($results as $key => $values) {
            $condition = $values['condition'];
            $count = $values['c'];

            $conditionArray[$condition]['nbr'] = $count;
            if (isset($selectedFilters['condition'])
                && in_array($condition, $selectedFilters['condition'])
            ) {
                $conditionArray[$condition]['checked'] = true;
            }
        }

        $conditionBlock = [
            'type_lite' => 'condition',
            'type' => 'condition',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Condition', [], 'Modules.Facetedsearch.Shop'),
            'values' => $conditionArray,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        ];

        return $conditionBlock;
    }

    /**
     * Get the quantities filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     *
     * @return array
     */
    private function getAvailabilitiesBlock($filter, $selectedFilters)
    {
        if ($this->psStockManagement === null) {
            $this->psStockManagement = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
        }

        if ($this->psOrderOutOfStock === null) {
            $this->psOrderOutOfStock = (bool) Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }

        // We only initialize the options if stock management is activated
        $availabilityOptions = [];
        if ($this->psStockManagement) {
            $availabilityOptions = [
                Availability::IN_STOCK => [
                    'name' => $this->context->getTranslator()->trans(
                        'In stock',
                        [],
                        'Modules.Facetedsearch.Shop'
                    ),
                    'nbr' => 0,
                ],
                Availability::AVAILABLE => [
                    'name' => $this->context->getTranslator()->trans(
                        'Available',
                        [],
                        'Modules.Facetedsearch.Shop'
                    ),
                    'nbr' => 0,
                ],
                Availability::NOT_AVAILABLE => [
                    'name' => $this->context->getTranslator()->trans(
                        'Not available',
                        [],
                        'Modules.Facetedsearch.Shop'
                    ),
                    'nbr' => 0,
                ],
            ];

            $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter(Search::STOCK_MANAGEMENT_FILTER);

            // Products without quantity in stock, with out-of-stock ordering disabled
            $filteredSearchAdapter->addOperationsFilter(
                Search::STOCK_MANAGEMENT_FILTER,
                [
                    [
                        ['quantity', [0], '<='],
                        ['out_of_stock', !$this->psOrderOutOfStock ? [0, 2] : [0], '='],
                    ],
                ]
            );
            $availabilityOptions[Availability::NOT_AVAILABLE]['nbr'] = $filteredSearchAdapter->count();

            // Products in stock, or with out-of-stock ordering enabled
            $filteredSearchAdapter->addOperationsFilter(
                Search::STOCK_MANAGEMENT_FILTER,
                [
                    [
                        ['out_of_stock', $this->psOrderOutOfStock ? [1, 2] : [1], '='],
                    ],
                    [
                        ['quantity', [0], '>'],
                    ],
                ]
            );
            $availabilityOptions[Availability::AVAILABLE]['nbr'] = $filteredSearchAdapter->count();

            // Products in stock
            $filteredSearchAdapter->addOperationsFilter(
                Search::STOCK_MANAGEMENT_FILTER,
                [
                    [
                        ['quantity', [0], '>'],
                    ],
                ]
            );
            $availabilityOptions[Availability::IN_STOCK]['nbr'] = $filteredSearchAdapter->count();

            // If some filter was selected, we want to show only this single filter, it does not make sense to show others
            if (isset($selectedFilters['availability'])) {
                // We loop through selected filters and assign it to our options and remove the rest
                foreach ($availabilityOptions as $key => $values) {
                    if (in_array($key, $selectedFilters['availability'], true)) {
                        $availabilityOptions[$key]['checked'] = true;
                    }
                }
            }

            // Hide Available option if the count is the same as In stock, it doesn't make no sense
            // Product count is a reliable indicator here, because there can never be product IN STOCK that is not AVAILABLE
            // So if the counts match, it MUST BE the same products
            if ($availabilityOptions[Availability::AVAILABLE]['nbr'] == $availabilityOptions[Availability::IN_STOCK]['nbr']) {
                unset($availabilityOptions[Availability::AVAILABLE]);
            }
        }

        $quantityBlock = [
            'type_lite' => 'availability',
            'type' => 'availability',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Availability', [], 'Modules.Facetedsearch.Shop'),
            'values' => $availabilityOptions,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        ];

        return $quantityBlock;
    }

    /**
     * Get the manufacturers filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     * @param int $idLang
     *
     * @return array
     */
    private function getManufacturersBlock($filter, $selectedFilters, $idLang)
    {
        $manufacturersArray = $manufacturers = [];

        // TODO - Needed to make manufacturer filter work (=disappear) on manufacturer page, not sure how it works.
        // (Manufacturer's page is the only page having id_manufacturer as the initial filter, that's why.)
        if ($this->query->getQueryType() == 'manufacturer') {
            $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter();
        } else {
            $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter('id_manufacturer');
        }

        $tempManufacturers = Manufacturer::getManufacturers(false, $idLang);
        if (empty($tempManufacturers)) {
            return $manufacturersArray;
        }

        foreach ($tempManufacturers as $key => $manufacturer) {
            $manufacturers[$manufacturer['id_manufacturer']] = $manufacturer;
        }

        $results = $filteredSearchAdapter->valueCount('id_manufacturer');
        foreach ($results as $key => $values) {
            if (!isset($values['id_manufacturer'])) {
                continue;
            }

            $id_manufacturer = $values['id_manufacturer'];
            if (empty($manufacturers[$id_manufacturer]['name'])) {
                continue;
            }

            $count = $values['c'];
            $manufacturersArray[$id_manufacturer] = [
                'name' => $manufacturers[$id_manufacturer]['name'],
                'nbr' => $count,
            ];

            if (isset($selectedFilters['manufacturer'])
                && in_array($id_manufacturer, $selectedFilters['manufacturer'])
            ) {
                $manufacturersArray[$id_manufacturer]['checked'] = true;
            }
        }

        $manufacturerBlock = [
            'type_lite' => 'manufacturer',
            'type' => 'manufacturer',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Brand', [], 'Modules.Facetedsearch.Shop'),
            'values' => $manufacturersArray,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        ];

        return $manufacturerBlock;
    }

    /**
     * Get the attributes filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     * @param int $idLang
     *
     * @return array
     */
    private function getAttributesBlock($filter, $selectedFilters, $idLang)
    {
        $attributesBlock = [];
        $filteredSearchAdapter = null;
        $idAttributeGroup = $filter['id_value'];

        if (!empty($selectedFilters['id_attribute_group'])) {
            foreach ($selectedFilters['id_attribute_group'] as $key => $selectedFilter) {
                if ($key == $idAttributeGroup) {
                    $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter('with_attributes_' . $idAttributeGroup);
                    break;
                }
            }
        }

        if (!$filteredSearchAdapter) {
            $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter();
        }

        $attributesGroup = $this->dataAccessor->getAttributesGroups($idLang);
        if ($attributesGroup === []) {
            return $attributesBlock;
        }

        $attributes = $this->dataAccessor->getAttributes($idLang, $idAttributeGroup);

        $filteredSearchAdapter->addOperationsFilter(
            'id_attribute_group_' . $idAttributeGroup,
            [[['id_attribute_group', [(int) $idAttributeGroup]]]]
        );
        $results = $filteredSearchAdapter->valueCount('id_attribute');
        foreach ($results as $key => $values) {
            $idAttribute = $values['id_attribute'];
            if (!isset($attributes[$idAttribute])) {
                continue;
            }

            $count = $values['c'];
            $attribute = $attributes[$idAttribute];
            $idAttributeGroup = $attribute['id_attribute_group'];
            if (!isset($attributesBlock[$idAttributeGroup])) {
                $attributeGroup = $attributesGroup[$idAttributeGroup];

                $attributesBlock[$idAttributeGroup] = [
                    'type_lite' => 'id_attribute_group',
                    'type' => 'id_attribute_group',
                    'id_key' => $idAttributeGroup,
                    'name' => $attributeGroup['attribute_group_name'],
                    'is_color_group' => (bool) $attributeGroup['is_color_group'],
                    'values' => [],
                    'url_name' => $attributeGroup['url_name'],
                    'meta_title' => $attributeGroup['meta_title'],
                    'filter_show_limit' => (int) $filter['filter_show_limit'],
                    'filter_type' => $filter['filter_type'],
                ];
            }

            $attributesBlock[$idAttributeGroup]['values'][$idAttribute] = [
                'name' => $attribute['name'],
                'nbr' => $count,
                'url_name' => $attribute['url_name'],
                'meta_title' => $attribute['meta_title'],
            ];

            if ($attributesBlock[$idAttributeGroup]['is_color_group'] !== false) {
                $attributesBlock[$idAttributeGroup]['values'][$idAttribute]['color'] = $attribute['color'];
            }

            if (array_key_exists('id_attribute_group', $selectedFilters)) {
                foreach ($selectedFilters['id_attribute_group'] as $selectedAttribute) {
                    if (in_array($idAttribute, $selectedAttribute)) {
                        $attributesBlock[$idAttributeGroup]['values'][$idAttribute]['checked'] = true;
                    }
                }
            }
        }

        foreach ($attributesBlock as $idAttributeGroup => $value) {
            $attributesBlock[$idAttributeGroup]['values'] = $this->sortByKey($attributes, $value['values']);
        }

        $attributesBlock = $this->sortByKey($attributesGroup, $attributesBlock);

        return $attributesBlock;
    }

    /**
     * Sort an array using the same key order than the sortedReferenceArray
     *
     * @param array $sortedReferenceArray
     * @param array $array
     *
     * @return array
     */
    private function sortByKey(array $sortedReferenceArray, $array)
    {
        $sortedArray = [];

        // iterate in the original order
        foreach ($sortedReferenceArray as $key => $value) {
            if (array_key_exists($key, $array)) {
                $sortedArray[$key] = $array[$key];
            }
        }

        return $sortedArray;
    }

    /**
     * Get the features filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     * @param int $idLang
     *
     * @return array
     */
    private function getFeaturesBlock($filter, $selectedFilters, $idLang)
    {
        $featureBlock = [];
        $idFeature = $filter['id_value'];
        $filteredSearchAdapter = null;

        if (!empty($selectedFilters['id_feature'])) {
            foreach ($selectedFilters['id_feature'] as $key => $selectedFilter) {
                if ($key == $idFeature) {
                    $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter('with_features_' . $idFeature);

                    break;
                }
            }
        }

        if (!$filteredSearchAdapter) {
            $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter();
        }

        $features = $this->dataAccessor->getFeatures($idLang);
        if (empty($features)) {
            return [];
        }

        $filteredSearchAdapter->addOperationsFilter(
            'id_feature_' . $idFeature,
            [[['id_feature', [(int) $idFeature]]]]
        );

        $filteredSearchAdapter->addSelectField('id_feature');
        $results = $filteredSearchAdapter->valueCount('id_feature_value');
        foreach ($results as $key => $values) {
            $idFeatureValue = $values['id_feature_value'];
            $idFeature = $values['id_feature'];
            $count = $values['c'];

            $feature = $features[$idFeature];

            if (!isset($featureBlock[$idFeature])) {
                $features[$idFeature]['featureValues'] = $this->dataAccessor->getFeatureValues($idFeature, $idLang);

                $featureBlock[$idFeature] = [
                    'type_lite' => 'id_feature',
                    'type' => 'id_feature',
                    'id_key' => $idFeature,
                    'values' => [],
                    'name' => $feature['name'],
                    'url_name' => $feature['url_name'],
                    'meta_title' => $feature['meta_title'],
                    'filter_show_limit' => (int) $filter['filter_show_limit'],
                    'filter_type' => $filter['filter_type'],
                ];
            }

            $featureValues = $features[$idFeature]['featureValues'];
            if (!isset($featureValues[$idFeatureValue]['value'])) {
                continue;
            }

            $featureBlock[$idFeature]['values'][$idFeatureValue] = [
                'nbr' => $count,
                'name' => $featureValues[$idFeatureValue]['value'],
                'url_name' => $featureValues[$idFeatureValue]['url_name'],
                'meta_title' => $featureValues[$idFeatureValue]['meta_title'],
            ];

            if (array_key_exists('id_feature', $selectedFilters)) {
                foreach ($selectedFilters['id_feature'] as $selectedFeature) {
                    if (in_array($idFeatureValue, $selectedFeature)) {
                        $featureBlock[$feature['id_feature']]['values'][$idFeatureValue]['checked'] = true;
                    }
                }
            }
        }

        $featureBlock = $this->sortFeatureBlock($featureBlock);

        return $featureBlock;
    }

    /**
     * Natural sort multi-dimensional feature array
     *
     * @param array $featureBlock
     *
     * @return array
     */
    private function sortFeatureBlock($featureBlock)
    {
        //Natural sort
        foreach ($featureBlock as $key => $value) {
            $temp = [];
            foreach ($featureBlock[$key]['values'] as $idFeatureValue => $featureValueInfos) {
                $temp[$idFeatureValue] = $featureValueInfos['name'];
            }

            natcasesort($temp);
            $temp2 = [];

            foreach ($temp as $keytemp => $valuetemp) {
                $temp2[$keytemp] = $featureBlock[$key]['values'][$keytemp];
            }

            $featureBlock[$key]['values'] = $temp2;
        }

        return $featureBlock;
    }

    /**
     * Add the categories filter condition based on the parent and config variables
     *
     * @param InterfaceAdapter $filteredSearchAdapter
     * @param Category $parent
     */
    private function addCategoriesBlockFilters(InterfaceAdapter $filteredSearchAdapter, $parent)
    {
        if (Group::isFeatureActive()) {
            $userGroups = ($this->context->customer->isLogged() ? $this->context->customer->getGroups() : [
                Configuration::get(
                    'PS_UNIDENTIFIED_GROUP'
                ),
            ]);

            $filteredSearchAdapter->addFilter('id_group', $userGroups);
        }

        $depth = (int) Configuration::get('PS_LAYERED_FILTER_CATEGORY_DEPTH', null, null, null, 1);

        if ($depth) {
            $levelDepth = $parent->level_depth;
            $filteredSearchAdapter->addFilter('level_depth', [$depth + $levelDepth], '<=');
        }

        $filteredSearchAdapter->addFilter('nleft', [$parent->nleft], '>');
        $filteredSearchAdapter->addFilter('nright', [$parent->nright], '<');
    }

    /**
     * Get the categories filter block
     *
     * @param array $filter
     * @param array $selectedFilters
     * @param int $idLang
     * @param Category $parent
     *
     * @return array
     */
    private function getCategoriesBlock($filter, $selectedFilters, $idLang, $parent)
    {
        $filteredSearchAdapter = $this->searchAdapter->getFilteredSearchAdapter('id_category');
        $this->addCategoriesBlockFilters($filteredSearchAdapter, $parent);

        $categoryArray = [];
        $categories = Category::getAllCategoriesName(
            null,
            $idLang,
            true,
            null,
            true,
            '',
            'ORDER BY c.nleft, c.position'
        );
        foreach ($categories as $key => $value) {
            $categories[$value['id_category']] = $value;
        }

        $results = $filteredSearchAdapter->valueCount('id_category');

        foreach ($results as $key => $values) {
            $idCategory = $values['id_category'];
            if (!isset($categories[$idCategory])) {
                // Category can sometimes not be found in case of multistore
                // plus waiting for indexation
                continue;
            }

            $count = $values['c'];
            $categoryArray[$idCategory] = [
                'name' => $categories[$idCategory]['name'],
                'nbr' => $count,
            ];

            if (isset($selectedFilters['category']) && in_array($idCategory, $selectedFilters['category'])) {
                $categoryArray[$idCategory]['checked'] = true;
            }
        }

        $categoryBlock = [
            'type_lite' => 'category',
            'type' => 'category',
            'id_key' => 0,
            'name' => $this->context->getTranslator()->trans('Categories', [], 'Modules.Facetedsearch.Shop'),
            'values' => $categoryArray,
            'filter_show_limit' => (int) $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        ];

        return $categoryBlock;
    }

    /**
     * Prepare price specifications to display cldr prices.
     *
     * @return array
     */
    private function preparePriceSpecifications()
    {
        /* @var Currency */
        $currency = $this->context->currency;
        // New method since PS 1.7.6
        if (isset($this->context->currentLocale) && method_exists($this->context->currentLocale, 'getPriceSpecification')) {
            /* @var PriceSpecification */
            $priceSpecification = $this->context->currentLocale->getPriceSpecification($currency->iso_code);
            /* @var NumberSymbolList */
            $symbolList = $priceSpecification->getSymbolsByNumberingSystem(Locale::NUMBERING_SYSTEM_LATIN);

            $symbol = [
                $symbolList->getDecimal(),
                $symbolList->getGroup(),
                $symbolList->getList(),
                $symbolList->getPercentSign(),
                $symbolList->getMinusSign(),
                $symbolList->getPlusSign(),
                $symbolList->getExponential(),
                $symbolList->getSuperscriptingExponent(),
                $symbolList->getPerMille(),
                $symbolList->getInfinity(),
                $symbolList->getNaN(),
            ];

            return array_merge(
                ['symbol' => $symbol],
                $priceSpecification->toArray()
            );
        }

        // Default symbol configuration
        $symbol = [
            '.',
            ',',
            ';',
            '%',
            '-',
            '+',
            'E',
            '×',
            '‰',
            '∞',
            'NaN',
        ];
        // The property `$precision` exists only from PS 1.7.6. On previous versions, all prices have 2 decimals
        $precision = isset($currency->precision) ? $currency->precision : 2;
        $formats = explode(';', $currency->format);
        if (count($formats) > 1) {
            $positivePattern = $formats[0];
            $negativePattern = $formats[1];
        } else {
            $positivePattern = $currency->format;
            $negativePattern = $currency->format;
        }

        return [
            'positivePattern' => $positivePattern,
            'negativePattern' => $negativePattern,
            'symbol' => $symbol,
            'maxFractionDigits' => $precision,
            'minFractionDigits' => $precision,
            'groupingUsed' => true,
            'primaryGroupSize' => 3,
            'secondaryGroupSize' => 3,
            'currencyCode' => $currency->iso_code,
            'currencySymbol' => $currency->sign,
        ];
    }
}

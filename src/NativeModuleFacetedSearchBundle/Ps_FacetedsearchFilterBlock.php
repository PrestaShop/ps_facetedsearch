<?php
namespace NativeModuleFacetedSearchBundle;

use NativeModuleFacetedSearchBundle\Adapter\FacetedSearchAbstract;
use Context;
use NativeModuleFacetedSearchBundle\Adapter\FacetedSearchInterface;
use Tools;
use Configuration;
use Category;
use Db;
use Group;
use Shop;

class Ps_FacetedsearchFilterBlock
{
    /** @var FacetedSearchAbstract */
    private $facetedSearchAdapter;

    public function __construct(Ps_FacetedsearchProductSearch $productSearch)
    {
        $this->facetedSearchAdapter = $productSearch->getFacetedSearchAdapter();
    }

    public function getFilterBlock(
        $nbProducts,
        $selectedFilters
    ) {
        $context = Context::getContext();
        $idLang = $context->language->id;
        $currency = $context->currency;
        $idShop = (int) $context->shop->id;
        $idParent = (int) Tools::getValue('id_category',
            Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY')));
        $parent = new Category((int) $idParent, $idLang);

        /* Get the filters for the current category */
        $filters = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT type, id_value, filter_show_limit, filter_type FROM '._DB_PREFIX_.'layered_category
			WHERE id_category = '.(int) $idParent.'
				AND id_shop = '.$idShop.'
			GROUP BY `type`, id_value ORDER BY position ASC'
        );

        $filterBlocks = [];
        foreach ($filters as $filter) {
            switch ($filter['type']) {
                case 'price':
                    $filterBlocks[] = $this->getPriceRangeBlock($currency, $filter);
                    break;
                case 'weight':
                    $filterBlocks[] = $this->getWeightRangeBlock($filter, $selectedFilters, $nbProducts);
                    break;
                case 'condition':
                    $filterBlocks[] = $this->getConditionsBlock($filter, $selectedFilters);
                    break;
                case 'quantity':
                    $filterBlocks[] = $this->getQuantitiesBlock($filter, $selectedFilters);
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
                    $filterBlocks[] = $this->getCategoriesBlock($filter, $selectedFilters, $idLang, $parent);
            }
        }

        return array(
            'filters' => $filterBlocks,
        );
    }

    protected function showPriceFilter()
    {
        return Group::getCurrent()->show_prices;
    }


    private function getPriceRangeBlock($currency, $filter)
    {
        if (!$this->showPriceFilter()) {
            return [];
        }

        $priceBlock = array(
            'type_lite' => 'price',
            'type' => 'price',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Price', [], 'Modules.Facetedsearch.Shop'),
            'slider' => true,
            'max' => '0',
            'min' => null,
            'unit' => $currency->sign,
            'format' => $currency->format,
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
            'list_of_values' => [],
        );

        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();
        $categoryFilter = $this->facetedSearchAdapter->getFilter('id_category');
        // only apply id_category filter, if it exists, to compute price range block
        if ($categoryFilter !== null) {
            $filteredSearchAdapter->addFilter('id_category', $categoryFilter);
        }

        list($priceBlock['min'], $priceBlock['max']) = $filteredSearchAdapter->getMinMaxPriceValue();
        $priceBlock['list_of_values'] = $filteredSearchAdapter->getFieldRanges('price', 10);

        return $priceBlock;
    }

    private function getWeightRangeBlock($filter, $selectedFilters, $nbProducts)
    {
        $weightBlock = array(
            'type_lite' => 'weight',
            'type' => 'weight',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Weight', [], 'Modules.Facetedsearch.Shop'),
            'slider' => true,
            'max' => '0',
            'min' => null,
            'unit' => Configuration::get('PS_WEIGHT_UNIT'),
            'format' => 5, // Ex: xxxxx kg
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
            'list_of_values' => [],
        );

        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();
        $categoryFilter = $this->facetedSearchAdapter->getFilter('id_category');
        // only apply id_category filter, if it exists, to compute weight range block
        if ($categoryFilter !== null) {
            $filteredSearchAdapter->addFilter('id_category', $categoryFilter);
        }

        list($weightBlock['min'], $weightBlock['max']) = $filteredSearchAdapter->getMinMaxValue('weight');
        $weightBlock['list_of_values'] = $filteredSearchAdapter->getFieldRanges('weight', 10);

        if (empty($weightBlock['list_of_values']) && isset($selected_filters['weight'])) {
            // in case we don't have a list of values,
            // add the original one.
            // This may happen when e.g. all products
            // weigh 0.
            $weightBlock['list_of_values'] = array(
                array(
                    0 => $selectedFilters['weight'][0],
                    1 => $selectedFilters['weight'][1],
                    'nbr' => $nbProducts,
                ),
            );
        }

        $weightBlock['values'] = array($weightBlock['min'], $weightBlock['max']);

        return $weightBlock;
    }

    private function getConditionsBlock($filter, $selectedFilters)
    {
        $conditionArray = array(
            'new' => array('name' => Context::getContext()->getTranslator()->trans('New', [],
                'Modules.Facetedsearch.Shop'), 'nbr' => 0),
            'used' => array('name' => Context::getContext()->getTranslator()->trans('Used', [],
                'Modules.Facetedsearch.Shop'), 'nbr' => 0),
            'refurbished' => array('name' => Context::getContext()->getTranslator()->trans('Refurbished', [],
                'Modules.Facetedsearch.Shop'),
                'nbr' => 0),
        );
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();
        $results = $filteredSearchAdapter->valueCount('condition');
        foreach ($results as $key => $values) {
            $condition = $values['condition'];
            $count = $values['c'];

            $conditionArray[$condition]['nbr'] = $count;
            if (isset($selectedFilters['condition']) && in_array($condition, $selectedFilters['condition'])) {
                $conditionArray[$condition]['checked'] = true;
            }
        }

        $conditionBlock = array(
            'type_lite' => 'condition',
            'type' => 'condition',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Condition', [], 'Modules.Facetedsearch.Shop'),
            'values' => $conditionArray,
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        );

        return $conditionBlock;
    }

    private function getQuantitiesBlock($filter, $selectedFilters)
    {
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();
        $quantityArray = array(
            0 => array('name' => Context::getContext()->getTranslator()->trans('Not available', [],
                'Modules.Facetedsearch.Shop'), 'nbr' => 0),
            1 => array('name' => Context::getContext()->getTranslator()->trans('In stock', [],
                'Modules.Facetedsearch.Shop'), 'nbr' => 0),
        );

        static $ps_stock_management = null;
        static $ps_order_out_of_stock = null;
        if ($ps_stock_management === null) {
            $ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
        }

        if ($ps_order_out_of_stock === null) {
            $ps_order_out_of_stock = Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }

        $allResults = $filteredSearchAdapter->count();
        $filteredSearchAdapter->addFilter('quantity', [0]);
        $noMoreQuantityResults = $filteredSearchAdapter->valueCount('quantity');

        if (!empty($noMoreQuantityResults)) {
            $results[0]['c'] = $noMoreQuantityResults[0]['c'];
        } else {
            $results[0]['c'] = 0;
        }
        $results[1]['c'] = $allResults - $results[0]['c'];
        if (!$ps_stock_management) {
            if (isset($selectedFilters['quantity']) && in_array(1, $selectedFilters['quantity'])) {
                $quantityArray[1]['checked'] = true;
            }

            $count = $results[0]['c'] + $results[1]['c'];
            $quantityArray[1]['nbr'] = $count;
        } else {
            $resultsOutOfStock = $this->facetedSearchAdapter->valueCount('out_of_stock');
            // search count of products always available when out of stock (out_of_stock == 1)
            if (array_key_exists(1, $resultsOutOfStock)) {
                $results[1]['c'] += $resultsOutOfStock[1]['c'];
                $results[0]['c'] -= $resultsOutOfStock[1]['c'];
            }

            // if $ps_order_out_of_stock == 1, product with out_of_stock == 2 are available
            if ($ps_order_out_of_stock == 1) {
                if (array_key_exists(2, $resultsOutOfStock)) {
                    $results[1]['c'] += $resultsOutOfStock[2]['c'];
                    $results[0]['c'] -= $resultsOutOfStock[2]['c'];
                }
            }
            foreach ($results as $key => $values) {
                $count = $values['c'];

                $quantityArray[$key]['nbr'] = $count;
                if (isset($selectedFilters['quantity']) && in_array($key, $selectedFilters['quantity'])) {
                    $quantityArray[$key]['checked'] = true;
                }
            }
        }

        $quantityBlock = array(
            'type_lite' => 'quantity',
            'type' => 'quantity',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Availability', [], 'Modules.Facetedsearch.Shop'),
            'values' => $quantityArray,
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        );

        return $quantityBlock;
    }

    private function getManufacturersBlock($filter, $selectedFilters, $idLang)
    {
        $manufacturersArray = [];
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();

        $manufacturers = \Manufacturer::getManufacturers(false, $idLang);
        if ($manufacturers === []) {
            return $manufacturersArray;
        }
        foreach ($manufacturers as $key => $manufacturer) {
            $manufacturers[$manufacturer['id_manufacturer']] = $manufacturer;
        }

        $results = $filteredSearchAdapter->valueCount('id_manufacturer');
        foreach ($results as $key => $values) {
            $id_manufacturer = $values['id_manufacturer'];
            $count = $values['c'];

            $manufacturersArray[$id_manufacturer] = array('name' => $manufacturers[$id_manufacturer]['name'],
                'nbr' => $count);
            if (isset($selectedFilters['manufacturer'])
                && in_array($id_manufacturer, $selectedFilters['manufacturer'])) {
                $manufacturersArray[$id_manufacturer]['checked'] = true;
            }
        }

        $this->sortByKey($manufacturers, $manufacturersArray);

        $manufacturerBlock = array(
            'type_lite' => 'manufacturer',
            'type' => 'manufacturer',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Brand', [], 'Modules.Facetedsearch.Shop'),
            'values' => $manufacturersArray,
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        );

        return $manufacturerBlock;
    }

    private function getAttributeGroupLayeredInfos($idAttributeGroup, $idLang)
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT url_name, meta_title FROM '.
            _DB_PREFIX_.'layered_indexable_attribute_group_lang_value WHERE id_attribute_group='.
            (int)$idAttributeGroup.' AND id_lang='.(int)$idLang);
    }

    private function getAttributeLayeredInfos($idAttribute, $idLang)
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT url_name, meta_title FROM '.
            _DB_PREFIX_.'layered_indexable_attribute_lang_value WHERE id_attribute='.
            (int)$idAttribute.' AND id_lang='.(int)$idLang);
    }

    private function getFeatureLayeredInfos($idFeatureValue, $idLang)
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT url_name, meta_title FROM '.
            _DB_PREFIX_.'layered_indexable_feature_value_lang_value WHERE id_feature_value='.
            (int)$idFeatureValue.' AND id_lang='.(int)$idLang);
    }

    private function getFeatureValueLayeredInfos($idFeature, $idLang)
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT url_name, meta_title FROM '.
            _DB_PREFIX_.'layered_indexable_feature_lang_value WHERE id_feature='.
            (int)$idFeature.' AND id_lang='.(int)$idLang);
    }

    /**
     * @param int  $idLang
     * @param bool $notNull
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     */
    public static function getAttributes($idLang, $notNull = true)
    {
        if (!\Combination::isFeatureActive()) {
            return array();
        }

        return Db::getInstance()->executeS('
			SELECT DISTINCT a.`id_attribute`, a.`color`, al.`name`, agl.`id_attribute_group`
			FROM `'._DB_PREFIX_.'attribute_group` ag
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
				ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = '.(int)$idLang.')
			LEFT JOIN `'._DB_PREFIX_.'attribute` a
				ON a.`id_attribute_group` = ag.`id_attribute_group`
			LEFT JOIN `'._DB_PREFIX_.'attribute_lang` al
				ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = '.(int)$idLang.')
			'.Shop::addSqlAssociation('attribute_group', 'ag').'
			'.Shop::addSqlAssociation('attribute', 'a').'
			'.($notNull ?
                'WHERE a.`id_attribute` IS NOT NULL AND al.`name` IS NOT NULL '.
                'AND agl.`id_attribute_group` IS NOT NULL' : '').'
			ORDER BY agl.`name` ASC, a.`position` ASC
		');
    }

    /**
     * Get all attributes groups for a given language
     *
     * @param int $idLang Language id
     *
     * @return array Attributes groups
     */
    public static function getAttributesGroups($idLang)
    {
        if (!\Combination::isFeatureActive()) {
            return [];
        }

        return Db::getInstance()->executeS('
			SELECT ag.id_attribute_group, agl.name as attribute_group_name, is_color_group
			FROM `'._DB_PREFIX_.'attribute_group` ag
			'.Shop::addSqlAssociation('attribute_group', 'ag').'
			LEFT JOIN `'._DB_PREFIX_.'attribute_group_lang` agl
				ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND `id_lang` = '.(int) $idLang.')
			GROUP BY ag.id_attribute_group ORDER BY ag.`position` ASC
		');
    }


    private function getAttributesBlock($filter, $selectedFilters, $idLang)
    {
        $attributesBlock = [];
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();

        $idAttributeGroup = $filter['id_value'];

        $attributesGroup = self::getAttributesGroups($idLang);
        if ($attributesGroup === []) {
            return $attributesBlock;
        }

        foreach ($attributesGroup as $key => $attributeGroup) {
            $attributesGroup[$attributeGroup['id_attribute_group']] = $attributeGroup;
        }

        $attributes = self::getAttributes($idLang, true);
        foreach ($attributes as $key => $attribute) {
            $attributes[$attribute['id_attribute']] = $attribute;
        }

        $filteredSearchAdapter->addFilter('id_attribute_group', [(int)$idAttributeGroup]);
        $results = $filteredSearchAdapter->valueCount('id_attribute');

        foreach ($results as $key => $values) {
            $idAttribute = $values['id_attribute'];
            $count = $values['c'];

            $attribute = $attributes[$idAttribute];
            $idAttributeGroup = $attribute['id_attribute_group'];
            if (!isset($attributesBlock[$idAttributeGroup])) {
                $attributeGroup = $attributesGroup[$idAttributeGroup];

                list($urlName, $metaTitle) = $this->getAttributeGroupLayeredInfos($idAttributeGroup, $idLang);

                $attributesBlock[$idAttributeGroup] = array(
                    'type_lite' => 'id_attribute_group',
                    'type' => 'id_attribute_group',
                    'id_key' => $idAttributeGroup,
                    'name' => $attributeGroup['attribute_group_name'],
                    'is_color_group' => (bool)$attributeGroup['is_color_group'],
                    'values' => [],
                    'url_name' => $urlName,
                    'meta_title' => $metaTitle,
                    'filter_show_limit' => $filter['filter_show_limit'],
                    'filter_type' => $filter['filter_type'],
                );
            }

            list($urlName, $metaTitle) = $this->getAttributeLayeredInfos($idAttribute, $idLang);
            $attributesBlock[$idAttributeGroup]['values'][$idAttribute] = array(
                'color' => $attribute['color'],
                'name' => $attribute['name'],
                'nbr' => $count,
                'url_name' => $urlName,
                'meta_title' => $metaTitle,
            );

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

    private function sortByKey($sortedReferenceArray, $array)
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

    private function getFeaturesBlock($filter, $selectedFilters, $idLang)
    {
        $features = $featureBlock = [];
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();

        $idFeature = $filter['id_value'];

        $tempFeatures = \Feature::getFeatures($idLang);
        foreach ($tempFeatures as $key => $feature) {
            $features[$feature['id_feature']] = $feature;
        }

        $filteredSearchAdapter->addFilter('id_feature', [(int)$idFeature]);
        $filteredSearchAdapter->addSelectField('id_feature');
        $results = $filteredSearchAdapter->valueCount('id_feature_value');
        foreach ($results as $key => $values) {
            $idFeatureValue = $values['id_feature_value'];
            $idFeature = $values['id_feature'];
            $count = $values['c'];

            $feature = $features[$idFeature];

            if (!isset($featureBlock[$idFeature])) {
                $tempFeatureValues = \FeatureValue::getFeatureValuesWithLang($idLang, $idFeature);

                foreach ($tempFeatureValues as $featureValueKey => $featureValue) {
                    $features[$idFeature]['featureValues'][$featureValue['id_feature_value']]= $featureValue;
                }

                list($urlName, $metaTitle) = $this->getFeatureLayeredInfos($idFeature, $idLang);

                $featureBlock[$idFeature] = array(
                    'type_lite' => 'id_feature',
                    'type' => 'id_feature',
                    'id_key' => $idFeature,
                    'values' => [],
                    'name' => $feature['name'],
                    'url_name' => $urlName,
                    'meta_title' => $metaTitle,
                    'filter_show_limit' => $filter['filter_show_limit'],
                    'filter_type' => $filter['filter_type'],
                );
            }

            $featureValues = $features[$idFeature]['featureValues'];

            list($urlName, $metaTitle) = $this->getFeatureValueLayeredInfos($idFeatureValue, $idLang);
            $featureBlock[$idFeature]['values'][$idFeatureValue] = array(
                'nbr' => $count,
                'name' => $featureValues[$idFeatureValue]['value'],
                'url_name' => $urlName,
                'meta_title' => $metaTitle,
            );

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

    private function addCategoriesBlockFilters(FacetedSearchInterface $filteredSearchAdapter, $parent)
    {
        if (Group::isFeatureActive()) {
            $userGroups = (Context::getContext()->customer->isLogged(
            ) ? Context::getContext()->customer->getGroups() : array(
                Configuration::get(
                    'PS_UNIDENTIFIED_GROUP'
                )
            ));

            $filteredSearchAdapter->addFilter('id_group', $userGroups);
        }

        $depth = (int)Configuration::get('PS_LAYERED_FILTER_CATEGORY_DEPTH', null, null, null, 1);

        if ($depth) {
            $levelDepth = $parent->level_depth;
            $filteredSearchAdapter->addFilter('level_depth', [$depth+$levelDepth], '<=');
        }

        $filteredSearchAdapter->addFilter('nleft', [$parent->nleft], '>');
        $filteredSearchAdapter->addFilter('nright', [$parent->nright], '<');
    }
    
    private function getCategoriesBlock($filter, $selectedFilters, $idLang, $parent)
    {
        $filteredSearchAdapter = $this->facetedSearchAdapter->getFilteredSearchAdapter();
        $this->addCategoriesBlockFilters($filteredSearchAdapter, $parent);

        $categoryArray = [];
        $categories = Category::getAllCategoriesName(null, $idLang, true, null,
            true, '', 'ORDER BY c.nleft, c.position');
        foreach ($categories as $key => $value) {
            $categories[$value['id_category']] = $value;
        }
        $results = $filteredSearchAdapter->valueCount('id_category');

        foreach ($results as $key => $values) {
            $idCategory = $values['id_category'];
            $count = $values['c'];

            $categoryArray[$idCategory] = [
                'name' => $categories[$idCategory]['name'],
                'nbr' => $count
            ];

            if (isset($selectedFilters['category']) && in_array($idCategory, $selectedFilters['category'])) {
                $categoryArray[$idCategory]['checked'] = true;
            }
        }

        $categoryArray = $this->sortByKey($categories, $categoryArray);

        $categoryBlock = array(
            'type_lite' => 'category',
            'type' => 'category',
            'id_key' => 0,
            'name' => Context::getContext()->getTranslator()->trans('Categories', [], 'Modules.Facetedsearch.Shop'),
            'values' => $categoryArray,
            'filter_show_limit' => $filter['filter_show_limit'],
            'filter_type' => $filter['filter_type'],
        );

        return $categoryBlock;
    }
}

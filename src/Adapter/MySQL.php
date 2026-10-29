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

namespace PrestaShop\Module\FacetedSearch\Adapter;

use Configuration;
use Context;
use Db;
use Doctrine\Common\Collections\ArrayCollection;
use PrestaShop\Module\FacetedSearch\CombinationFeature;
use Product;
use StockAvailable;

class MySQL extends AbstractAdapter
{
    /**
     * @var string
     */
    const TYPE = 'MySQL';

    /**
     * @var string
     */
    const LEFT_JOIN = 'LEFT JOIN';

    /**
     * @var string
     */
    const INNER_JOIN = 'INNER JOIN';

    /**
     * Level the counted features are read from, forced by getMixedFeatureCountQuery().
     *
     * @var string|null
     */
    private $countedFeatureLevel;

    /**
     * {@inheritdoc}
     */
    public function getMinMaxPriceValue()
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['price_min', 'MIN(price_min) as min, MAX(price_max) as max']);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return [floor((float) $result[0]['min']), ceil((float) $result[0]['max'])];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilteredSearchAdapter($resetFilter = null, $skipInitialPopulation = false)
    {
        $mysqlAdapter = new self();
        if ($this->getInitialPopulation() !== null && !$skipInitialPopulation) {
            $mysqlAdapter->initialPopulation = clone $this->getInitialPopulation();
            if ($resetFilter) {
                // Try to reset filter & operations filter
                $mysqlAdapter->initialPopulation->resetFilter($resetFilter);
                $mysqlAdapter->initialPopulation->resetOperationsFilter($resetFilter);
            }
        }

        return $mysqlAdapter;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        return $this->getDatabase()->executeS($this->getQuery());
    }

    /**
     * Construct the final sql query
     *
     * @return string
     */
    public function getQuery()
    {
        if ($this->isMixedFeatureCount()) {
            return $this->getMixedFeatureCountQuery();
        }

        // Prepare mapping for joined tables
        $filterToTableMapping = $this->getFieldMapping();

        // Expose only base product fields required by the outer query from the initial population.
        $this->addRequiredInitialPopulationFields($filterToTableMapping);

        // Process and generate all fields for the SQL query below
        $orderField = $this->computeOrderByField($filterToTableMapping);
        $selectFields = $this->computeSelectFields($filterToTableMapping);
        $whereConditions = $this->computeWhereConditions($filterToTableMapping);
        $joinConditions = $this->computeJoinConditions($filterToTableMapping);
        $groupFields = $this->computeGroupByFields($filterToTableMapping);

        // Now, let's build the query...
        // If this query IS the initial population (the base table), we are selecting from product table
        if ($this->getInitialPopulation() === null) {
            $referenceTable = _DB_PREFIX_ . 'product';
        // If not, we will call this function again but for the initial population
        } else {
            $referenceTable = '(' . $this->getInitialPopulation()->getQuery() . ')';
        }

        // Construct the base query
        $query = 'SELECT ' . implode(', ', $selectFields) . ' FROM ' . $referenceTable . ' p';

        // Add join conditions if any
        foreach ($joinConditions as $joinAliasInfos) {
            foreach ($joinAliasInfos as $tableAlias => $joinInfos) {
                // A "raw" table is already a full table expression (e.g. a derived table) and must not
                // be prefixed, otherwise it is a regular table name living behind the database prefix.
                $tableName = !empty($joinInfos['rawTable'])
                    ? $joinInfos['tableName']
                    : _DB_PREFIX_ . $joinInfos['tableName'];
                $query .= ' ' . $joinInfos['joinType'] . ' ' . $tableName . ' ' .
                       $tableAlias . ' ON ' . $joinInfos['joinCondition'];
            }
        }

        // Add where conditions if any
        if (!empty($whereConditions)) {
            $query .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        // Add groupping
        if (!empty($groupFields)) {
            $query .= ' GROUP BY ' . implode(', ', $groupFields);
        }

        // Add ordering
        if (!empty($orderField)) {
            $query .= ' ORDER BY ' . $orderField;

            /*
             * If the result is not ordered by id_product, we add it as a fallback order,
             * to avoid SQL returning it in random order.
             */
            if (strpos($orderField, 'p.id_product') === false) {
                $query .= ', p.id_product DESC';
            }
        }

        return $query;
    }

    /**
     * Define the mapping between fields and tables
     *
     * @return array
     */
    protected function getFieldMapping()
    {
        $stockCondition = StockAvailable::addSqlShopRestriction(
            null,
            null,
            'sa'
        );

        // Feature filters are resolved against the feature_product table by default. When combination
        // feature values are enabled (PrestaShop >= 9.3 + feature flag), we swap that table for a
        // derived table that also exposes the feature values defined at combination level, so a product
        // becomes filterable by a feature value carried by any of its combinations.
        $featureProductTable = 'feature_product';
        $featureProductRawTable = false;
        $featureJoinCondition = '(p.id_product = fp.id_product)';
        $featureJoinExtra = [];
        if ($this->isCombinationFeatureFilteringEnabled()) {
            // Derived table (id_product, id_product_attribute, id_feature, id_feature_value) merging
            // product-level feature values with the ones defined at combination level
            // (feature_product_attribute, resolved to their product through product_attribute). The
            // id_product_attribute column is NULL for product-level values, so a feature filter can be
            // correlated with a specific combination. The UNION removes duplicates so a value defined
            // at both levels is not counted twice.
            $featureProductTable = '(SELECT id_product, NULL AS id_product_attribute, id_feature, id_feature_value'
                . ' FROM ' . _DB_PREFIX_ . 'feature_product'
                . ' UNION'
                . ' SELECT pa.id_product, pa.id_product_attribute, fpa.id_feature, fpa.id_feature_value'
                . ' FROM ' . _DB_PREFIX_ . 'feature_product_attribute fpa'
                . ' INNER JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON pa.id_product_attribute = fpa.id_product_attribute)';
            $featureProductRawTable = true;
            // Correlate the feature row with the combination currently joined (pa) so that a feature
            // filter and an attribute filter must be satisfied by the same combination, not by two
            // different ones. Product-level feature values (id_product_attribute IS NULL) keep
            // applying to every combination.
            $featureJoinCondition = '(p.id_product = fp.id_product'
                . ' AND (fp.id_product_attribute IS NULL OR fp.id_product_attribute = pa.id_product_attribute))';
            $featureJoinExtra = ['dependencyField' => 'id_product_attribute'];
        }

        $filterToTableMapping = [
            'id_product_attribute' => [
                'tableName' => 'product_attribute',
                'tableAlias' => 'pa',
                'joinCondition' => '(p.id_product = pa.id_product)',
                'joinType' => self::LEFT_JOIN,
            ],
            'id_attribute' => [
                'tableName' => 'product_attribute_combination',
                'tableAlias' => 'pac',
                'joinCondition' => '(pa.id_product_attribute = pac.id_product_attribute)',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_product_attribute',
            ],
            'id_attribute_group' => [
                'tableName' => 'attribute',
                'tableAlias' => 'a',
                'joinCondition' => '(a.id_attribute = pac.id_attribute)',
                'joinType' => self::INNER_JOIN,
                'dependencyField' => 'id_attribute',
            ],
            'id_feature' => array_merge([
                'tableName' => $featureProductTable,
                'tableAlias' => 'fp',
                'joinCondition' => $featureJoinCondition,
                'joinType' => self::INNER_JOIN,
                'rawTable' => $featureProductRawTable,
            ], $featureJoinExtra),
            'id_shop' => [
                'tableName' => 'product_shop',
                'tableAlias' => 'ps',
                'joinCondition' => '(p.id_product = ps.id_product AND ps.id_shop = ' .
                $this->getContext()->shop->id . ' AND ps.active = TRUE)',
                'joinType' => self::INNER_JOIN,
            ],
            'visibility' => [
                'tableName' => 'product_shop',
                'tableAlias' => 'ps',
                'joinCondition' => '(p.id_product = ps.id_product AND ps.id_shop = ' .
                    $this->getContext()->shop->id . ' AND ps.active = TRUE)',
                'joinType' => self::INNER_JOIN,
            ],
            'id_feature_value' => array_merge([
                'tableName' => $featureProductTable,
                'tableAlias' => 'fp',
                'joinCondition' => $featureJoinCondition,
                'joinType' => self::LEFT_JOIN,
                'rawTable' => $featureProductRawTable,
            ], $featureJoinExtra),
            'id_category' => [
                'tableName' => 'category_product',
                'tableAlias' => 'cp',
                'joinCondition' => '(p.id_product = cp.id_product)',
                'joinType' => self::INNER_JOIN,
            ],
            'position' => [
                'tableName' => 'category_product',
                'tableAlias' => 'cp',
                'joinCondition' => '(p.id_product = cp.id_product)',
                'joinType' => self::INNER_JOIN,
            ],
            // The supplier page lists every product associated with the supplier, and that
            // association lives in product_supplier. Without this mapping the filter falls back to
            // p.id_supplier, which only holds the default supplier of the product.
            'id_supplier' => [
                'tableName' => 'product_supplier',
                'tableAlias' => 'psup',
                'joinCondition' => '(p.id_product = psup.id_product)',
                'joinType' => self::INNER_JOIN,
            ],
            'manufacturer_name' => [
                'tableName' => 'manufacturer',
                'tableAlias' => 'm',
                'fieldName' => 'name',
                'joinCondition' => '(p.id_manufacturer = m.id_manufacturer)',
                'joinType' => self::LEFT_JOIN,
                'requiredProductFields' => ['id_manufacturer'],
            ],
            'name' => [
                'tableName' => 'product_lang',
                'tableAlias' => 'pl',
                'joinCondition' => '(p.id_product = pl.id_product AND pl.id_shop = ' .
                $this->getContext()->shop->id . ' AND pl.id_lang = ' . $this->getContext()->language->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'nleft' => [
                'tableName' => 'category',
                'tableAlias' => 'c',
                'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                'joinType' => self::INNER_JOIN,
                'dependencyField' => 'id_category',
            ],
            'nright' => [
                'tableName' => 'category',
                'tableAlias' => 'c',
                'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                'joinType' => self::INNER_JOIN,
                'dependencyField' => 'id_category',
            ],
            'level_depth' => [
                'tableName' => 'category',
                'tableAlias' => 'c',
                'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                'joinType' => self::INNER_JOIN,
                'dependencyField' => 'id_category',
            ],
            'out_of_stock' => [
                'tableName' => 'stock_available',
                'tableAlias' => 'sa',
                'joinCondition' => '(p.id_product = sa.id_product AND IFNULL(pa.id_product_attribute, 0) = sa.id_product_attribute' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_product_attribute',
                'shareOperationJoin' => true,
            ],
            'quantity' => [
                'tableName' => 'stock_available',
                'tableAlias' => 'sa',
                'joinCondition' => '(p.id_product = sa.id_product AND IFNULL(pa.id_product_attribute, 0) = sa.id_product_attribute' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_product_attribute',
                'shareOperationJoin' => true,
                'aggregateFunction' => 'SUM',
                'aggregateFieldName' => 'quantity',
            ],
            'price_min' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_shop = ' . $this->getContext()->shop->id . ' AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'price_max' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_shop = ' . $this->getContext()->shop->id . ' AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'range_start' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_shop = ' . $this->getContext()->shop->id . ' AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'range_end' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_shop = ' . $this->getContext()->shop->id . ' AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'id_group' => [
                'tableName' => 'category_group',
                'tableAlias' => 'cg',
                'joinCondition' => '(cg.id_category = c.id_category)',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'nleft',
            ],
            'sales' => [
                'tableName' => 'product_sale',
                'tableAlias' => 'psales',
                'fieldName' => 'quantity',
                'fieldAlias' => 'sales',
                'joinCondition' => '(psales.id_product = p.id_product)',
                'joinType' => self::LEFT_JOIN,
            ],
            'reduction' => [
                'tableName' => 'specific_price',
                'tableAlias' => 'sp',
                'joinCondition' => '(
                    sp.id_product = p.id_product AND 
                    sp.id_shop IN (0, ' . $this->getContext()->shop->id . ') AND 
                    sp.id_currency IN (0, ' . $this->getContext()->currency->id . ') AND 
                    sp.id_country IN (0, ' . $this->getContext()->country->id . ') AND 
                    sp.id_group IN (0, ' . $this->getContext()->customer->id_default_group . ') AND 
                    sp.from_quantity = 1 AND
                    sp.reduction > 0 AND
                    sp.id_customer = 0 AND
                    sp.id_cart = 0 AND 
                    (sp.from = \'0000-00-00 00:00:00\' OR \'' . date('Y-m-d H:i:s') . '\' >= sp.from) AND 
                    (sp.to = \'0000-00-00 00:00:00\' OR \'' . date('Y-m-d H:i:s') . '\' <= sp.to) 
                )',
                'joinType' => self::LEFT_JOIN,
            ],
        ];

        if ($this->isCombinationFeatureFilteringEnabled()) {
            // The derived table is joined on id_product only (its combination condition is an OR), so
            // the rows of a product are multiplied by its combinations for every join, and the
            // resulting join order differs from one database engine to another. Feature filters and
            // counts join the table of the level their features or values are set on instead, on its
            // full key. See getOperationField() for the filters and getCountedFeatureTable() for the
            // counts. A filter on values set on both levels keeps the derived table.
            $filterToTableMapping['mixed_feature_value'] = array_merge($filterToTableMapping['id_feature_value'], [
                'tableAlias' => 'fpm',
                'fieldName' => 'id_feature_value',
                'joinCondition' => str_replace('fp.', 'fpm.', $featureJoinCondition),
            ]);
            $filterToTableMapping['product_feature_value'] = [
                'tableName' => 'feature_product',
                'tableAlias' => 'fpp',
                'fieldName' => 'id_feature_value',
                'joinCondition' => '(p.id_product = fpp.id_product)',
                'joinType' => self::LEFT_JOIN,
            ];
            $filterToTableMapping['combination_feature_value'] = [
                'tableName' => 'feature_product_attribute',
                'tableAlias' => 'fpc',
                'fieldName' => 'id_feature_value',
                'joinCondition' => '(pa.id_product_attribute = fpc.id_product_attribute)',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_product_attribute',
            ];

            $countedFeatureTable = $this->getCountedFeatureTable();
            if ($countedFeatureTable !== null) {
                foreach (['id_feature', 'id_feature_value'] as $field) {
                    $filterToTableMapping[$field] = array_merge(
                        array_diff_key($filterToTableMapping[$field], ['dependencyField' => true]),
                        $countedFeatureTable
                    );
                }
            }
        }

        return $filterToTableMapping;
    }

    /**
     * Whether this is a facet count query (Filters\Block) on features set on both levels.
     *
     * @return bool
     */
    private function isMixedFeatureCount()
    {
        if ($this->countedFeatureLevel !== null
            || !$this->isCombinationFeatureFilteringEnabled()
            || $this->getSelectFields()->toArray() !== ['id_feature', 'id_feature_value', 'COUNT(DISTINCT p.id_product) c']
            || $this->getGroupFields()->toArray() !== ['id_feature_value']
        ) {
            return false;
        }
        $idFeatures = $this->getCountedFeatureIds();

        return !empty($idFeatures) && CombinationFeature::resolveLevel($this->getFeatureLevels($idFeatures)) === null;
    }

    /**
     * Count features set on both levels from the product-level and the combination-level rows of
     * each product, each read from the table of its level, so a product is counted once per value
     * whatever level carries it.
     *
     * @return string
     */
    private function getMixedFeatureCountQuery()
    {
        $levelQueries = [];
        foreach ([CombinationFeature::LEVEL_PRODUCT, CombinationFeature::LEVEL_COMBINATION] as $level) {
            $levelAdapter = clone $this;
            $levelAdapter->countedFeatureLevel = $level;
            $levelAdapter->setSelectFields(['id_product', 'id_feature', 'id_feature_value']);
            $levelAdapter->resetGroupBy();
            $levelQueries[] = $levelAdapter->getQuery();
        }

        return 'SELECT fp.id_feature, fp.id_feature_value, COUNT(DISTINCT fp.id_product) c'
            . ' FROM (' . implode(' UNION ALL ', $levelQueries) . ') fp'
            . ' GROUP BY fp.id_feature_value';
    }

    /**
     * @return int[] the features counted by a facet count query (Filters\Block)
     */
    private function getCountedFeatureIds()
    {
        $idFeatures = [];
        foreach ($this->getOperationsFilters() as $filterOperations) {
            foreach ($filterOperations as $operations) {
                foreach ($operations as $operation) {
                    if ($operation[0] === 'id_feature') {
                        $idFeatures = array_merge($idFeatures, $operation[1]);
                    }
                }
            }
        }

        return $idFeatures;
    }

    /**
     * The table counting the features of a facet count query, when they are all set on the same
     * level (or when getMixedFeatureCountQuery() forces it).
     *
     * @return array|null
     */
    private function getCountedFeatureTable()
    {
        $idFeatures = $this->getCountedFeatureIds();
        if (empty($idFeatures)) {
            return null;
        }

        $level = $this->countedFeatureLevel !== null
            ? $this->countedFeatureLevel
            : CombinationFeature::resolveLevel($this->getFeatureLevels($idFeatures));
        switch ($level) {
            case CombinationFeature::LEVEL_PRODUCT:
                return [
                    'tableName' => 'feature_product',
                    'joinCondition' => '(p.id_product = fp.id_product)',
                    'rawTable' => false,
                ];
            case CombinationFeature::LEVEL_COMBINATION:
                return [
                    'tableName' => 'feature_product_attribute',
                    'joinCondition' => '(pa.id_product_attribute = fp.id_product_attribute)',
                    'rawTable' => false,
                    'dependencyField' => 'id_product_attribute',
                ];
            default:
                return null;
        }
    }

    /**
     * Whether feature filters must also take combination feature values into account.
     * Extracted so it can be overridden in tests.
     *
     * @return bool
     */
    protected function isCombinationFeatureFilteringEnabled()
    {
        return CombinationFeature::isFilteringEnabled();
    }

    /**
     * Extracted so it can be overridden in tests.
     *
     * @param int[] $idFeatures
     *
     * @return array<int, array{product: bool, combination: bool}>
     */
    protected function getFeatureLevels(array $idFeatures)
    {
        return CombinationFeature::getFeatureLevels($idFeatures);
    }

    /**
     * Extracted so it can be overridden in tests.
     *
     * @param int[] $idFeatureValues
     *
     * @return array<int, array{product: bool, combination: bool}>
     */
    protected function getFeatureValueLevels(array $idFeatureValues)
    {
        return CombinationFeature::getFeatureValueLevels($idFeatureValues);
    }

    /**
     * Pick the mapped field an operation filters on. A feature value filter uses the table of the
     * level its values are set on, and the derived table only when they are set on both levels.
     *
     * @param array $operation
     * @param array $filterToTableMapping
     *
     * @return string
     */
    private function getOperationField(array $operation, array $filterToTableMapping)
    {
        if ($operation[0] !== 'id_feature_value' || !isset($filterToTableMapping['product_feature_value'])) {
            return $operation[0];
        }

        switch (CombinationFeature::resolveLevel($this->getFeatureValueLevels($operation[1]))) {
            case CombinationFeature::LEVEL_PRODUCT:
                return 'product_feature_value';
            case CombinationFeature::LEVEL_COMBINATION:
                return 'combination_feature_value';
            default:
                return 'mixed_feature_value';
        }
    }

    /**
     * Whether the initial population already applies this filter, so the outer query does not need
     * to repeat it. That is the case when the outer query reads nothing per combination, or for
     * values set on products, which are not tied to a combination. Repeating them only adds joins,
     * which some engines pick as the first table (MariaDB 10.11 then joins the initial population
     * without an index).
     *
     * @param string $filterName
     * @param array $filterOperations
     * @param array $filterToTableMapping
     *
     * @return bool
     */
    private function isAppliedByInitialPopulation($filterName, array $filterOperations, array $filterToTableMapping)
    {
        if (!isset($filterToTableMapping['product_feature_value'])
            || $this->getInitialPopulation() === null
            || $this->getInitialPopulation()->getOperationsFilters()->get($filterName) !== $filterOperations
        ) {
            return false;
        }

        if (!$this->readsCombination($filterToTableMapping)) {
            return true;
        }

        foreach ($filterOperations as $operations) {
            foreach ($operations as $operation) {
                if ($this->getOperationField($operation, $filterToTableMapping) !== 'product_feature_value') {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Whether the outer query reads a field tied to a combination, apart from the filters copied
     * from the initial population.
     *
     * @param array $filterToTableMapping
     *
     * @return bool
     */
    private function readsCombination(array $filterToTableMapping)
    {
        $fields = array_merge(
            $this->getSelectFields()->toArray(),
            $this->getGroupFields()->toArray(),
            $this->getFilters()->getKeys(),
            [$this->getOrderField()]
        );
        $initialOperationsFilters = $this->getInitialPopulation()->getOperationsFilters();
        foreach ($this->getOperationsFilters() as $filterName => $filterOperations) {
            if ($initialOperationsFilters->get($filterName) === $filterOperations) {
                continue;
            }
            foreach ($filterOperations as $operations) {
                foreach ($operations as $operation) {
                    $fields[] = $this->getOperationField($operation, $filterToTableMapping);
                }
            }
        }

        foreach ($fields as $field) {
            while (isset($filterToTableMapping[$field])) {
                if ($field === 'id_product_attribute') {
                    return true;
                }
                $field = isset($filterToTableMapping[$field]['dependencyField']) ? $filterToTableMapping[$field]['dependencyField'] : null;
            }
        }

        return false;
    }

    /**
     * Get the joined and escaped value from an multi-dimensional array
     *
     * @param string $separator
     * @param array $values
     *
     * @return string Escaped string value
     */
    protected function getJoinedEscapedValue($separator, array $values)
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->getJoinedEscapedValue($separator, $value);
            } elseif (is_numeric($value)) {
                $values[$key] = pSQL($value);
            } else {
                $values[$key] = "'" . pSQL($value) . "'";
            }
        }

        return implode($separator, $values);
    }

    /**
     * Compute the orderby fields, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return string
     */
    protected function computeOrderByField(array $filterToTableMapping)
    {
        // First, we get the order field from the current instance. That can be strings like 'price', 'name', 'position', etc.
        $orderField = $this->getOrderField();

        // If it's empty, we just return it as is, nothing to do. This is usually a case when getting products
        // for available filters, they reset the order field so we save performance
        if (empty($orderField)) {
            return $orderField;
        }

        // Expose sortable fields from the initial population when their mapped value has a stable output name.
        // An expression is skipped: it is evaluated against the outer query's joins, which the initial population
        // does not have, so selecting it there refers to tables that are not joined at that level.
        if ($this->getInitialPopulation() !== null
            && $orderField !== 'price'
            && strpos($orderField, '(') === false
            && (
                !isset($filterToTableMapping[$orderField]['fieldName'])
                || isset($filterToTableMapping[$orderField]['fieldAlias'])
            )
        ) {
            $this->getInitialPopulation()->addSelectField($orderField);
        }

        // Alter order by field if it's a price column
        if ($orderField === 'price') {
            $orderField = $this->getOrderDirection() === 'asc' ? 'price_min' : 'price_max';
        }

        // Do not try to process the orderField if it already has an alias, or if it's a group function
        // We just append the order direction and return it
        if (strpos($orderField, '.') !== false || strpos($orderField, '(') !== false) {
            return $orderField . ' ' . strtoupper($this->getOrderDirection());
        }

        // In all other cases, add table mapping or p. prefix depending on field type
        $orderField = $this->computeFieldName($orderField, $filterToTableMapping, true);

        /*
         * Do not try to process the orderField if it's a search page. We will use manually constructed list
         * to order products by their position in the search results we got from the core, with inverted order
         */
        if ($orderField == 'p.position' && !empty($this->getInitialPopulation()->getFilters()['id_product']['='][0])) {
            return 'FIELD(p.id_product,' . implode(',', $this->getInitialPopulation()->getFilters()['id_product']['='][0]) . ') ' .
            ($this->getOrderDirection() === 'asc' ? 'DESC' : 'ASC');
        }

        // Alter order by field and add some products to the end of the list, if required
        $orderField = $this->computeShowLast($orderField, $filterToTableMapping);

        // Add sort order
        $orderField .= ' ' . strtoupper($this->getOrderDirection());

        // And return it
        return $orderField;
    }

    /**
     * Sort product list: InStock, OOPS with qty 0, OutOfStock
     *
     * @param string $orderField
     * @param array $filterToTableMapping
     *
     * @return string
     */
    protected function computeShowLast($orderField, $filterToTableMapping)
    {
        // allow only if feature is enabled & it is main product list query (caller ensures $orderField is non-empty)
        if ($this->getInitialPopulation() === null
            || !Configuration::get('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST')
        ) {
            return $orderField;
        }

        // Aggregate stock quantity only when stock-aware ordering is requested.
        $this->getInitialPopulation()->addSelectField('quantity');

        $this->addSelectField('out_of_stock');

        // order by out-of-stock last
        $computedQuantityField = $this->computeFieldName('quantity', $filterToTableMapping);
        $byOutOfStockLast = 'IFNULL(' . $computedQuantityField . ', 0) <= 0';

        /**
         * Default behaviour when out of stock
         * 0 - when deny orders
         * 1 - when allow orders
         *
         * @var int
         */
        $isAvailableWhenOutOfStock = (int) Product::isAvailableWhenOutOfStock(2);

        // computing values for order by 'allow to order last'
        $computedField = $this->computeFieldName('out_of_stock', $filterToTableMapping);
        $computedValue = $isAvailableWhenOutOfStock ? 0 : 1;
        $computedDirection = $isAvailableWhenOutOfStock ? 'ASC' : 'DESC';

        // query: products with zero or less quantity and not available to order go to the end
        $byOOPS = str_replace(
            [':byOutOfStockLast', ':field', ':value', ':direction'],
            [$byOutOfStockLast, $computedField, (string) $computedValue, $computedDirection],
            ':byOutOfStockLast AND FIELD(:field, :value) :direction'
        );

        $orderField = $byOutOfStockLast . ', '
            . $byOOPS . ', '
            . $orderField;

        return $orderField;
    }

    /**
     * Add base product fields referenced by the outer query to its derived product table.
     *
     * Fields backed by mapped tables remain in the outer query so their joins are only added
     * to the facet or result query that actually needs them.
     *
     * @param array $filterToTableMapping
     */
    private function addRequiredInitialPopulationFields(array $filterToTableMapping)
    {
        if ($this->getInitialPopulation() === null) {
            return;
        }

        // Collect fields used by SELECT, GROUP BY and regular filters in the outer query.
        $requiredFields = array_merge(
            $this->getSelectFields()->toArray(),
            $this->getGroupFields()->toArray(),
            $this->getFilters()->getKeys()
        );
        if ($this->getOrderField() !== '' && $this->getOrderField() !== 'price') {
            $requiredFields[] = $this->getOrderField();
        }

        // Include fields referenced by compound operation filters.
        foreach ($this->getOperationsFilters() as $filterOperations) {
            foreach ($filterOperations as $operations) {
                foreach ($operations as $operation) {
                    $requiredFields[] = $operation[0];
                }
            }
        }

        foreach (array_unique($requiredFields) as $fieldName) {
            // Add plain product fields directly to the derived product table.
            if (!ctype_alnum(str_replace('_', '', $fieldName))) {
                continue;
            }

            if (!array_key_exists($fieldName, $filterToTableMapping)) {
                $this->getInitialPopulation()->addSelectField($fieldName);
                continue;
            }

            // Expose explicitly declared product fields required by an outer mapped join.
            if (isset($filterToTableMapping[$fieldName]['requiredProductFields'])) {
                foreach ($filterToTableMapping[$fieldName]['requiredProductFields'] as $requiredProductField) {
                    $this->getInitialPopulation()->addSelectField($requiredProductField);
                }
            }
        }
    }

    /**
     * Check whether a field must be read from its mapped table instead of the initial population.
     *
     * @param string $fieldName
     * @param array $filterToTableMapping
     *
     * @return bool
     */
    private function requiresMappedTable($fieldName, array $filterToTableMapping)
    {
        if (!array_key_exists($fieldName, $filterToTableMapping)) {
            return false;
        }

        // Reuse a stable field alias already exposed by the derived product table.
        return $this->getInitialPopulation() === null
            || !$this->getInitialPopulation()->getSelectFields()->contains($fieldName)
            || (isset($filterToTableMapping[$fieldName]['fieldName']) && !isset($filterToTableMapping[$fieldName]['fieldAlias']));
    }

    /**
     * Check whether a field must be read from its mapped table when used in a filter condition.
     *
     * Aggregated fields are exposed by the derived product table as an aggregate
     * (SUM(sa.quantity) as quantity), which is not interchangeable with the row level value a
     * WHERE condition compares against. They always have to be read from their mapped table,
     * otherwise the filter silently changes meaning.
     *
     * @param string $fieldName
     * @param array $filterToTableMapping
     *
     * @return bool
     */
    private function requiresMappedTableForFilter($fieldName, array $filterToTableMapping)
    {
        if (isset($filterToTableMapping[$fieldName]['aggregateFunction'])) {
            return true;
        }

        return $this->requiresMappedTable($fieldName, $filterToTableMapping);
    }

    /**
     * Add alias to table field name
     *
     * @param string $fieldName
     * @param array $filterToTableMapping
     *
     * @return string Table Field name with an alias
     */
    protected function computeFieldName($fieldName, $filterToTableMapping, $sortByField = false)
    {
        if ($this->requiresMappedTable($fieldName, $filterToTableMapping)) {
            $joinMapping = $filterToTableMapping[$fieldName];
            $fieldName = $joinMapping['tableAlias'] . '.' . (isset($joinMapping['fieldName']) ? $joinMapping['fieldName'] : $fieldName);
            if ($sortByField === false) {
                $fieldName .= isset($joinMapping['fieldAlias']) ? ' as ' . $joinMapping['fieldAlias'] : '';
            }

            if (isset($joinMapping['aggregateFunction'], $joinMapping['aggregateFieldName'])) {
                $fieldName = $joinMapping['aggregateFunction'] . '(' . $fieldName . ') as ' . $joinMapping['aggregateFieldName'];
            }
        } else {
            if (strpos($fieldName, '(') === false) {
                $fieldName = 'p.' . $fieldName;
            }
        }

        return $fieldName;
    }

    /**
     * Compute the select fields, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return array
     */
    protected function computeSelectFields(array $filterToTableMapping)
    {
        // Add already added select fields to current query
        $selectFields = [];
        foreach ($this->getSelectFields() as $key => $selectField) {
            $selectFields[] = $this->computeFieldName($selectField, $filterToTableMapping);
        }

        return $selectFields;
    }

    /**
     * Computer the where conditions that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return array
     */
    protected function computeWhereConditions(array $filterToTableMapping)
    {
        $whereConditions = [];
        $operationIdx = 0;
        foreach ($this->getOperationsFilters() as $filterName => $filterOperations) {
            if ($this->isAppliedByInitialPopulation($filterName, $filterOperations, $filterToTableMapping)) {
                ++$operationIdx;
                continue;
            }

            $operationsConditions = [];
            foreach ($filterOperations as $operations) {
                $conditions = [];
                foreach ($operations as $idx => $operation) {
                    $selectAlias = 'p';
                    $values = $operation[1];
                    $operation[0] = $this->getOperationField($operation, $filterToTableMapping);
                    if ($this->requiresMappedTableForFilter($operation[0], $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$operation[0]];
                        // If index is not the first, append to the table alias for
                        // multi join
                        $selectAlias = $this->getOperationTableAlias($joinMapping, $operationIdx, $idx);
                        $operation[0] = isset($joinMapping['fieldName']) ? $joinMapping['fieldName'] : $operation[0];
                    }

                    if (count($values) === 1) {
                        $operator = !empty($operation[2]) ? $operation[2] : '=';
                        $conditions[] = $selectAlias . '.' . $operation[0] . $operator . current($values);
                    } else {
                        $conditions[] = $selectAlias . '.' . $operation[0] . ' IN (' . $this->getJoinedEscapedValue(', ', $values) . ')';
                    }
                }

                $operationsConditions[] = '(' . implode(' AND ', $conditions) . ')';
            }

            ++$operationIdx;
            if (!empty($operationsConditions)) {
                $whereConditions[] = '(' . implode(' OR ', $operationsConditions) . ')';
            }
        }

        foreach ($this->getFilters() as $filterName => $filterContent) {
            $selectAlias = 'p';
            if ($this->requiresMappedTableForFilter($filterName, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$filterName];
                $selectAlias = $joinMapping['tableAlias'];
                $filterName = isset($joinMapping['fieldName']) ? $joinMapping['fieldName'] : $filterName;
            }

            foreach ($filterContent as $operator => $values) {
                if (count($values) == 1) {
                    $values = current($values);

                    if ($operator === '=') {
                        if (count($values) == 1) {
                            $whereConditions[] =
                                $selectAlias . '.' . $filterName . $operator . "'" . current($values) . "'";
                        } else {
                            $whereConditions[] =
                                $selectAlias . '.' . $filterName . ' IN (' . $this->getJoinedEscapedValue(', ', $values) . ')';
                        }
                    } else {
                        $orConditions = [];
                        foreach ($values as $value) {
                            $orConditions[] = $selectAlias . '.' . $filterName . $operator . $value;
                        }
                        $whereConditions[] = implode(' OR ', $orConditions);
                    }
                }
            }
        }

        // if we have several "groups" of the same filter, we need to use the intersect of the matching products
        // e.g. : mix of id_feature like Composition & Styles
        $idFilteredProducts = null;
        foreach ($this->getFilters() as $filterName => $filterContent) {
            foreach ($filterContent as $operator => $filterValues) {
                if (count($filterValues) <= 1) {
                    continue;
                }

                $idTmpFilteredProducts = [];
                $mysqlAdapter = $this->getFilteredSearchAdapter();
                $mysqlAdapter->addSelectField('id_product');
                $mysqlAdapter->setOrderField('');
                $mysqlAdapter->addFilter($filterName, $filterValues, $operator);
                $idProducts = $mysqlAdapter->execute();
                foreach ($idProducts as $idProduct) {
                    $idTmpFilteredProducts[] = $idProduct['id_product'];
                }

                if ($idFilteredProducts === null) {
                    $idFilteredProducts = $idTmpFilteredProducts;
                } else {
                    $idFilteredProducts += array_intersect($idFilteredProducts, $idTmpFilteredProducts);
                }

                if (empty($idFilteredProducts)) {
                    // set it to 0 to make sure no result will be returned
                    $idFilteredProducts[] = 0;
                    break;
                }

                $whereConditions[] = 'p.id_product IN (' . implode(', ', $idFilteredProducts) . ')';
            }
        }

        return $whereConditions;
    }

    /**
     * @param array $joinMapping
     * @param int $operationIdx
     * @param int $idx
     *
     * @return string
     */
    private function getOperationTableAlias(array $joinMapping, $operationIdx, $idx)
    {
        return $joinMapping['tableAlias'] .
            ($operationIdx === 0 ? '' : '_' . $operationIdx) .
            ($idx === 0 || !empty($joinMapping['shareOperationJoin']) ? '' : '_' . $idx);
    }

    /**
     * Compute the joinConditions needed depending on the fields required in select, where, groupby & orderby fields
     *
     * @param array $filterToTableMapping
     *
     * @return ArrayCollection
     */
    protected function computeJoinConditions(array $filterToTableMapping)
    {
        $joinList = new ArrayCollection();

        $this->addJoinList($joinList, $this->getSelectFields(), $filterToTableMapping);
        $this->addJoinList($joinList, $this->getFilters()->getKeys(), $filterToTableMapping, true);

        $operationIdx = 0;
        foreach ($this->getOperationsFilters() as $filterName => $filterOperations) {
            if ($this->isAppliedByInitialPopulation($filterName, $filterOperations, $filterToTableMapping)) {
                ++$operationIdx;
                continue;
            }

            foreach ($filterOperations as $operations) {
                foreach ($operations as $idx => $operation) {
                    $operation[0] = $this->getOperationField($operation, $filterToTableMapping);
                    if ($this->requiresMappedTableForFilter($operation[0], $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$operation[0]];
                        $tableAlias = $this->getOperationTableAlias($joinMapping, $operationIdx, $idx);
                        if ($tableAlias !== $joinMapping['tableAlias']) {
                            // Index is not the first, append index to tableAlias on joinCondition
                            $joinMapping['joinCondition'] = preg_replace(
                                '~([\(\s=])' . $joinMapping['tableAlias'] . '\.~',
                                '${1}' . $tableAlias . '.',
                                $joinMapping['joinCondition']
                            );
                            $joinMapping['tableAlias'] = $tableAlias;
                        }

                        $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
                    }
                }
            }
            ++$operationIdx;
        }

        $this->addJoinList($joinList, $this->getGroupFields()->getKeys(), $filterToTableMapping);

        if ($this->requiresMappedTable($this->getOrderField(), $filterToTableMapping)) {
            $joinMapping = $filterToTableMapping[$this->getOrderField()];
            $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
        }

        return $joinList;
    }

    /**
     * Helper to add tables infos to the join list.
     *
     * @param ArrayCollection $joinList
     * @param array|ArrayCollection $list
     * @param array $filterToTableMapping
     */
    private function addJoinList(ArrayCollection $joinList, $list, array $filterToTableMapping, $forFilter = false)
    {
        foreach ($list as $field) {
            if ($forFilter
                ? $this->requiresMappedTableForFilter($field, $filterToTableMapping)
                : $this->requiresMappedTable($field, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$field];
                $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
            }
        }
    }

    /**
     * Add the required table infos to the join list, taking care of the dependent tables
     *
     * @param ArrayCollection $joinList
     * @param array $joinMapping
     * @param array $filterToTableMapping
     */
    private function addJoinConditions(ArrayCollection $joinList, array $joinMapping, array $filterToTableMapping)
    {
        if (array_key_exists('dependencyField', $joinMapping)) {
            $dependencyJoinMapping = $filterToTableMapping[$joinMapping['dependencyField']];
            $this->addJoinConditions($joinList, $dependencyJoinMapping, $filterToTableMapping);
        }
        $joinInfos[$joinMapping['tableAlias']] = [
            'tableName' => $joinMapping['tableName'],
            'joinCondition' => $joinMapping['joinCondition'],
            'joinType' => $joinMapping['joinType'],
            'rawTable' => !empty($joinMapping['rawTable']),
        ];

        $joinList->set($joinMapping['tableAlias'] . '_' . $joinMapping['tableName'], $joinInfos);
    }

    /**
     * Compute the groupby condition, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return array
     */
    private function computeGroupByFields(array $filterToTableMapping)
    {
        $groupFields = [];
        if ($this->getGroupFields()->isEmpty()) {
            return $groupFields;
        }

        foreach ($this->getGroupFields() as $key => $values) {
            if (strpos($values, '.') !== false
                || strpos($values, '(') !== false) {
                $groupFields[$key] = $values;
                continue;
            }

            if ($this->requiresMappedTable($values, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$values];
                $groupFields[$key] = $joinMapping['tableAlias'] . '.' . $values;
            } else {
                $groupFields[$key] = 'p.' . $values;
            }
        }

        return $groupFields;
    }

    /**
     * {@inheritdoc}
     */
    public function getMinMaxValue($fieldName)
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['MIN(' . $fieldName . ') as min, MAX(' . $fieldName . ') as max']);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return [(float) $result[0]['min'], (float) $result[0]['max']];
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);

        $result = $mysqlAdapter->valueCount();

        return isset($result[0]['c']) ? (int) $result[0]['c'] : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function valueCount($fieldName = null)
    {
        $this->resetGroupBy();
        if ($fieldName !== null) {
            $this->addGroupBy($fieldName);
            $this->addSelectField($fieldName);
        }

        $this->addSelectField('COUNT(DISTINCT p.id_product) c');
        $this->setOrderField('');

        $this->copyOperationsFilters();

        return $this->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function useFiltersAsInitialPopulation()
    {
        // Initial population has no ORDER BY
        $this->setOrderField('');

        // Keep the base product population narrow until an outer query requests more fields.
        $this->setSelectFields(['id_product']);

        // Clone it, add it to initial population
        $this->initialPopulation = clone $this;

        // Reset all filters so we start clean and add only the base select, we don't need anything else
        $this->resetAll();
        $this->addSelectField('id_product');
    }

    /**
     * @return Context
     */
    protected function getContext()
    {
        return Context::getContext();
    }

    /**
     * @return Db
     */
    protected function getDatabase()
    {
        return Db::getInstance();
    }

    /**
     * Copy stock management operation filters
     * to make sure quantity is also used
     */
    protected function copyOperationsFilters()
    {
        $initialPopulation = $this->getInitialPopulation();
        if (null === $initialPopulation) {
            return;
        }

        $operationsFilters = clone $initialPopulation->getOperationsFilters();
        foreach ($operationsFilters as $operationName => $operations) {
            $this->addOperationsFilter(
                $operationName,
                $operations
            );
        }
    }
}

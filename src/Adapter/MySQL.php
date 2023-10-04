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
     * {@inheritdoc}
     */
    public function getMinMaxPriceValue()
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['price_min', 'MIN(price_min) as min, MAX(price_max) as max']);
        $mysqlAdapter->setLimit(null);
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
        // Prepare mapping for joined tables
        $filterToTableMapping = $this->getFieldMapping();

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

        $query = 'SELECT ' . implode(', ', $selectFields) . ' FROM ' . $referenceTable . ' p';

        foreach ($joinConditions as $joinAliasInfos) {
            foreach ($joinAliasInfos as $tableAlias => $joinInfos) {
                $query .= ' ' . $joinInfos['joinType'] . ' ' . _DB_PREFIX_ . $joinInfos['tableName'] . ' ' .
                       $tableAlias . ' ON ' . $joinInfos['joinCondition'];
            }
        }

        if (!empty($whereConditions)) {
            $query .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        if ($groupFields) {
            $query .= ' GROUP BY ' . implode(', ', $groupFields);
        }

        if ($orderField) {
            $query .= ' ORDER BY ' . $orderField . ' ' . strtoupper($this->getOrderDirection());
            if ($orderField !== 'p.id_product') {
                $query .= ', p.id_product DESC';
            }
        }

        if ($this->limit !== null) {
            $query .= ' LIMIT ' . $this->offset . ', ' . $this->limit;
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
            'id_feature' => [
                'tableName' => 'feature_product',
                'tableAlias' => 'fp',
                'joinCondition' => '(p.id_product = fp.id_product)',
                'joinType' => self::INNER_JOIN,
            ],
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
            'id_feature_value' => [
                'tableName' => 'feature_product',
                'tableAlias' => 'fp',
                'joinCondition' => '(p.id_product = fp.id_product)',
                'joinType' => self::LEFT_JOIN,
            ],
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
            'manufacturer_name' => [
                'tableName' => 'manufacturer',
                'tableAlias' => 'm',
                'fieldName' => 'name',
                'joinCondition' => '(p.id_manufacturer = m.id_manufacturer)',
                'joinType' => self::LEFT_JOIN,
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
                'joinCondition' => '(p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_attribute',
            ],
            'quantity' => [
                'tableName' => 'stock_available',
                'tableAlias' => 'sa',
                'joinCondition' => '(p.id_product = sa.id_product AND IFNULL(pac.id_product_attribute, 0) = sa.id_product_attribute' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
                'dependencyField' => 'id_attribute',
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

        return $filterToTableMapping;
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
        $orderField = $this->getOrderField();

        // If we have set an initial population, add this field into initial population selects
        if ($this->getInitialPopulation() !== null && !empty($orderField)) {
            $this->getInitialPopulation()->addSelectField($orderField);
        }

        // Do not try to process the orderField if it already has an alias, or if it's a group function
        if (empty($orderField) || strpos($orderField, '.') !== false
            || strpos($orderField, '(') !== false) {
            return $orderField;
        }

        // Alter order by field if it's a price column
        if ($orderField === 'price') {
            $orderField = $this->getOrderDirection() === 'asc' ? 'price_min' : 'price_max';
        }

        // Add table mapping or p. prefix depending on field type
        $orderField = $this->computeFieldName($orderField, $filterToTableMapping, true);

        // Alter order by field and add some products to the end of the list, if required
        $orderField = $this->computeShowLast($orderField, $filterToTableMapping);

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
        // allow only if feature is enabled & it is main product list query
        if ($this->getInitialPopulation() === null
            || empty($orderField)
            || !Configuration::get('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST')
        ) {
            return $orderField;
        }

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
            [$byOutOfStockLast, $computedField, $computedValue, $computedDirection],
            ':byOutOfStockLast AND FIELD(:field, :value) :direction'
        );

        $orderField = $byOutOfStockLast . ', '
            . $byOOPS . ', '
            . $orderField;

        return $orderField;
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
        if (array_key_exists($fieldName, $filterToTableMapping)
            && (
                // If the requested order field is in the result, no need to change tableAlias
                // unless a fieldName key exists
                isset($filterToTableMapping[$fieldName]['fieldName'])
                || $this->getInitialPopulation() === null
                || !$this->getInitialPopulation()->getSelectFields()->contains($fieldName)
            )
        ) {
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
            $operationsConditions = [];
            foreach ($filterOperations as $operations) {
                $conditions = [];
                foreach ($operations as $idx => $operation) {
                    $selectAlias = 'p';
                    $values = $operation[1];
                    if (array_key_exists($operation[0], $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$operation[0]];
                        // If index is not the first, append to the table alias for
                        // multi join
                        $selectAlias = $joinMapping['tableAlias'] .
                                     ($operationIdx === 0 ? '' : '_' . $operationIdx) .
                                     ($idx === 0 ? '' : '_' . $idx);
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
            if (array_key_exists($filterName, $filterToTableMapping)) {
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
                $mysqlAdapter->setLimit(null);
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
        $this->addJoinList($joinList, $this->getFilters()->getKeys(), $filterToTableMapping);

        $operationIdx = 0;
        foreach ($this->getOperationsFilters() as $filterOperations) {
            foreach ($filterOperations as $operations) {
                foreach ($operations as $idx => $operation) {
                    if (array_key_exists($operation[0], $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$operation[0]];
                        if ($idx !== 0 || $operationIdx !== 0) {
                            // Index is not the first, append index to tableAlias on joinCondition
                            $joinMapping['joinCondition'] = preg_replace(
                                '~([\(\s=]' . $joinMapping['tableAlias'] . ')\.~',
                                '${1}' .
                                ($operationIdx === 0 ? '' : '_' . $operationIdx) .
                                ($idx === 0 ? '' : '_' . $idx) .
                                '.',
                                $joinMapping['joinCondition']
                            );
                            $joinMapping['tableAlias'] .= ($operationIdx === 0 ? '' : '_' . $operationIdx) .
                                ($idx === 0 ? '' : '_' . $idx);
                        }

                        $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
                    }
                }
            }
            ++$operationIdx;
        }

        $this->addJoinList($joinList, $this->getGroupFields()->getKeys(), $filterToTableMapping);

        if (array_key_exists($this->getOrderField(), $filterToTableMapping)) {
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
    private function addJoinList(ArrayCollection $joinList, $list, array $filterToTableMapping)
    {
        foreach ($list as $field) {
            if (array_key_exists($field, $filterToTableMapping)) {
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

            if (array_key_exists($values, $filterToTableMapping)) {
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
        $mysqlAdapter->setLimit(null);
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
        $this->setLimit(null);
        $this->setOrderField('');

        $this->copyOperationsFilters();

        return $this->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function useFiltersAsInitialPopulation()
    {
        // Initial population has NO LIMIT and no ORDER BY
        $this->setLimit(null);
        $this->setOrderField('');

        // We add basic select fields we will need to matter what
        $this->setSelectFields(
            [
                'id_product',
                'id_manufacturer',
                'quantity',
                'condition',
                'weight',
                'price',
                'sales',
                'on_sale',
                'date_add',
            ]
        );

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

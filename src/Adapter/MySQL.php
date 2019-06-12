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

namespace PrestaShop\Module\FacetedSearch\Adapter;

use Db;
use Context;
use StockAvailable;
use Doctrine\Common\Collections\ArrayCollection;

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
     * @var string
     */
    const STRAIGHT_JOIN = 'STRAIGHT_JOIN';

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
        $filterToTableMapping = $this->getFieldMapping();
        $orderField = $this->computeOrderByField($filterToTableMapping);

        if ($this->getInitialPopulation() === null) {
            $referenceTable = _DB_PREFIX_ . 'product';
        } else {
            $referenceTable = '(' . $this->getInitialPopulation()->getQuery() . ')';
        }

        if (empty($this->getSelectFields())
            && empty($this->getFilters())
            && empty($this->getGroupFields())
            && empty($this->getOrderField())
        ) {
            // avoid adding an extra SELECT FROM (SELECT ...) if it's not needed
            $query = $referenceTable;
            $this->setOrderField('');
        } else {
            $query = 'SELECT ';

            $selectFields = $this->computeSelectFields($filterToTableMapping);
            $whereConditions = $this->computeWhereConditions($filterToTableMapping);
            $joinConditions = $this->computeJoinConditions($filterToTableMapping);
            $groupFields = $this->computeGroupByFields($filterToTableMapping);

            $query .= implode(', ', $selectFields) . ' FROM ' . $referenceTable . ' p';

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
        }

        if ($orderField) {
            $query .= ' ORDER BY ' . $orderField . ' ' . strtoupper($this->getOrderDirection());
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
                'joinType' => self::STRAIGHT_JOIN,
            ],
            'id_attribute' => [
                'tableName' => 'product_attribute_combination',
                'tableAlias' => 'pac',
                'joinCondition' => '(pa.id_product_attribute = pac.id_product_attribute)',
                'joinType' => self::STRAIGHT_JOIN,
                'dependencyField' => 'id_product_attribute',
            ],
            'id_attribute_group' => [
                'tableName' => 'attribute',
                'tableAlias' => 'a',
                'joinCondition' => '(a.id_attribute = pac.id_attribute)',
                'joinType' => self::STRAIGHT_JOIN,
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
                'joinType' => self::INNER_JOIN,
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
                'joinCondition' => '(p.id_product = sa.id_product AND 0 = sa.id_product_attribute ' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
            ],
            'quantity' => [
                'tableName' => 'stock_available',
                'tableAlias' => 'sa',
                'joinCondition' => '(p.id_product = sa.id_product AND 0 = sa.id_product_attribute ' .
                $stockCondition . ')',
                'joinType' => self::LEFT_JOIN,
            ],
            'price_min' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'price_max' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'range_start' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = ' .
                $this->getContext()->currency->id . ' AND psi.id_country = ' . $this->getContext()->country->id . ')',
                'joinType' => self::INNER_JOIN,
            ],
            'range_end' => [
                'tableName' => 'layered_price_index',
                'tableAlias' => 'psi',
                'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = ' .
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
        ];

        return $filterToTableMapping;
    }

    /**
     * Compute the orderby fields, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return string
     */
    private function computeOrderByField(array $filterToTableMapping)
    {
        $orderField = $this->getOrderField();
        if ($this->getInitialPopulation() !== null && !empty($orderField)) {
            $this->getInitialPopulation()->addSelectField($orderField);
        }

        // do not try to process the orderField if it already has an alias, or if it's a group function
        if (empty($orderField) || strpos($orderField, '.') !== false
            || strpos($orderField, '(') !== false) {
            return $orderField;
        }

        if ($orderField === 'price') {
            $orderField = $this->getOrderDirection() === 'asc' ? 'price_min' : 'price_max';
        }

        if (array_key_exists($orderField, $filterToTableMapping)
            && (
                // If the requested order field is in the result, no need to change tableAlias
                // unless a fieldName key exists
                isset($filterToTableMapping[$orderField]['fieldName'])
                || $this->getInitialPopulation() === null
                || !$this->getInitialPopulation()->getSelectFields()->contains($orderField)
            )
        ) {
            $joinMapping = $filterToTableMapping[$orderField];
            $orderField = $joinMapping['tableAlias'] . '.' . (isset($joinMapping['fieldName']) ? $joinMapping['fieldName'] : $orderField);
        } else {
            $orderField = 'p.' . $orderField;
        }

        return $orderField;
    }

    /**
     * Compute the select fields, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return array
     */
    private function computeSelectFields(array $filterToTableMapping)
    {
        $selectFields = [];
        foreach ($this->getSelectFields() as $key => $selectField) {
            $selectAlias = 'p';
            if (array_key_exists($selectField, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$selectField];
                $selectAlias = $joinMapping['tableAlias'];
                if (isset($joinMapping['fieldName'])) {
                    $selectField = $joinMapping['fieldName'];
                }
            }

            if (strpos($selectField, '(') !== false) {
                $selectFields[] = $selectField;
            } else {
                $selectFields[] = $selectAlias . '.' . $selectField;
            }
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
    private function computeWhereConditions(array $filterToTableMapping)
    {
        $whereConditions = [];
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
                        $selectAlias = $joinMapping['tableAlias'] . ($idx === 0 ? '' : $idx);
                        $operation[0] = isset($joinMapping['fieldName']) ? $joinMapping['fieldName'] : $operation[0];
                    }

                    if (count($values) === 1) {
                        $operator = !empty($operation[2]) ? $operation[2] : '=';
                        $conditions[] = $selectAlias . '.' . $operation[0] . $operator . current($values);
                    } else {
                        $conditions[] = $selectAlias . '.' . $operation[0] . ' IN (' . implode(', ', array_map(function ($value) {
                            return is_numeric($value) ? pSQL($value) : "'" . pSQL($value) . "'";
                        }, $values)) . ')';
                    }
                }

                $operationsConditions[] = '(' . implode(' AND ', $conditions) . ')';
            }

            $whereConditions[] = '(' . implode(' OR ', $operationsConditions) . ')';
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
                                $selectAlias . '.' . $filterName . ' IN (' . implode(', ', array_map(function ($value) {
                                    return is_numeric($value) ? pSQL($value) : "'" . pSQL($value) . "'";
                                }, $values)) . ')';
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
    private function computeJoinConditions(array $filterToTableMapping)
    {
        $joinList = new ArrayCollection();

        $this->addJoinList($joinList, $this->getSelectFields(), $filterToTableMapping);
        $this->addJoinList($joinList, $this->getFilters()->getKeys(), $filterToTableMapping);

        foreach ($this->getOperationsFilters() as $filterOperations) {
            foreach ($filterOperations as $operations) {
                foreach ($operations as $idx => $operation) {
                    if (array_key_exists($operation[0], $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$operation[0]];
                        if ($idx !== 0) {
                            // Index is not the first, append index to tableAlias on joinCondition
                            $joinMapping['joinCondition'] = preg_replace(
                                '~([\(\s=]' . $joinMapping['tableAlias'] . ')\.~',
                                '${1}' . $idx . '.',
                                $joinMapping['joinCondition']
                            );
                            $joinMapping['tableAlias'] .= $idx;
                        }

                        $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
                    }
                }
            }
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

        $joinList->set($joinMapping['tableAlias'] . $joinMapping['tableName'], $joinInfos);
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
        if (empty($this->getGroupFields())) {
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
        $mysqlAdapter->setSelectFields(['COUNT(DISTINCT p.id_product) c']);
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return isset($result[0]['c']) ? $result[0]['c'] : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function valueCount($fieldName)
    {
        $this->resetGroupBy();
        $this->addGroupBy($fieldName);
        $this->addSelectField($fieldName);
        $this->addSelectField('COUNT(DISTINCT p.id_product) c');
        $this->setLimit(null);
        $this->setOrderField('');

        return $this->execute();
    }

    /**
     * {@inheritdoc}
     */
    public function useFiltersAsInitialPopulation()
    {
        $this->setLimit(null);
        $this->setOrderField('');
        $this->setSelectFields(
            [
                'id_product',
                'id_manufacturer',
                'quantity',
                'condition',
                'weight',
                'price',
            ]
        );
        $this->initialPopulation = clone $this;
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
}

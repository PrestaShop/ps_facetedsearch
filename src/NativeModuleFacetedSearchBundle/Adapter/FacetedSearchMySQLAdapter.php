<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

use Doctrine\Common\Collections\ArrayCollection;

class FacetedSearchMySQLAdapter extends FacetedSearchAbstract
{
    const LEFT_JOIN = 'LEFT JOIN';
    const INNER_JOIN = 'INNER JOIN';

    private static $referenceTable = _DB_PREFIX_.'product';
    private static $referenceAlias = 'p';

    public function getMinMaxPriceValue()
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['price_min', 'MIN(price_min) as min, MAX(price_max) as max']);
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return [0 => floor($result[0]['min']), 1 => ceil($result[0]['max'])];
    }

    public function getFilteredSearchAdapter($resetFilter = null)
    {
        $mysqlAdapter = new self();
        $mysqlAdapter->initialPopulation = clone ($this->initialPopulation);
        if ($resetFilter) {
            $mysqlAdapter->initialPopulation->resetFilter($resetFilter);
        }

        return $mysqlAdapter;
    }

    public function execute()
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->getQuery());
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        $filterToTableMapping = $this->getFieldMapping();
        $orderField = $this->computeOrderByField($filterToTableMapping);
        if ($this->initialPopulation === null) {
            $referenceTable = self::$referenceTable;
        } else {
            $referenceTable = '('.$this->initialPopulation->getQuery().')';
        }
        if (empty($this->selectFields) && empty($this->filters) && empty($this->groupFields)) {
            $query = $referenceTable;
            $this->orderField = '';
        } else {
            $query = 'SELECT ';

            $selectFields = $this->computeSelectFields($filterToTableMapping);
            $whereConditions = $this->computeWhereConditions($filterToTableMapping);
            $joinConditions = $this->computeJoinConditions($filterToTableMapping);
            $groupFields = $this->computeGroupByFields($filterToTableMapping);

            $query .= implode(', ', $selectFields) . ' FROM ' . $referenceTable . ' ' .
                self::$referenceAlias;

            foreach ($joinConditions as $tableName => $joinAliasConditionInfos) {
                foreach ($joinAliasConditionInfos as $tableAlias => $joinConditionInfos) {
                    $query .= ' ' . $joinConditionInfos['joinType'] . ' ' . _DB_PREFIX_ . $tableName . ' ' .
                        $tableAlias . ' ON ' . $joinConditionInfos['joinCondition'];
                }
            }

            if (!empty($whereConditions)) {
                $query .= ' WHERE ' . implode(' AND ', $whereConditions);
            }

            if ($groupFields) {
                $query .= ' GROUP BY ' . implode(', ', $groupFields);
            }
        }

        if ($this->orderField) {
            $query .= ' ORDER BY ' . $orderField . ' ' . $this->orderDirection;
        }

        if ($this->limit !== null) {
            $query .= ' LIMIT ' . $this->offset . ', ' . $this->limit;
        }

        return $query;
    }

    /**
     * @return array
     */
    protected function getFieldMapping()
    {
        $stockCondition = \StockAvailable::addSqlShopRestriction(
            null,
            null,
            'sa'
        );

        $filterToTableMapping = [
            'id_product_attribute' =>
                [
                    'tableName' => 'product_attribute',
                    'tableAlias' => 'pa',
                    'joinCondition' => '(p.id_product = pa.id_product)',
                    'joinType' => self::LEFT_JOIN
                ],
            'id_attribute' =>
                [
                    'tableName' => 'product_attribute_combination',
                    'tableAlias' => 'pac',
                    'joinCondition' => '(pa.id_product_attribute = pac.id_product_attribute)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'id_attribute_group' =>
                [
                    'tableName' => 'attribute',
                    'tableAlias' => 'a',
                    'joinCondition' => '(a.id_attribute = pac.id_attribute)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_attribute'
                ],
            'id_feature' =>
                [
                    'tableName' => 'feature_product',
                    'tableAlias' => 'fp',
                    'joinCondition' => '(p.id_product = fp.id_product)',
                    'joinType' => self::LEFT_JOIN
                ],
            'id_shop' =>
                [
                    'tableName' => 'product_shop',
                    'tableAlias' => 'ps',
                    'joinCondition' => '(p.id_product = ps.id_product)',
                    'joinType' => self::INNER_JOIN
                ],
            'id_feature_value' =>
                [
                    'tableName' => 'feature_product',
                    'tableAlias' => 'fp',
                    'joinCondition' => '(p.id_product = fp.id_product)',
                    'joinType' => self::LEFT_JOIN
                ],
            'id_category' =>
                [
                    'tableName' => 'category_product',
                    'tableAlias' => 'cp',
                    'joinCondition' => '(p.id_product = cp.id_product)',
                    'joinType' => self::INNER_JOIN
                ],
            'position' =>
                [
                    'tableName' => 'category_product',
                    'tableAlias' => 'cp',
                    'joinCondition' => '(p.id_product = cp.id_product)',
                    'joinType' => self::INNER_JOIN
                ],
            'nleft' =>
                [
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ],
            'nright' =>
                [
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ],
            'level_depth' =>
                [
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ],
            'out_of_stock' =>
                [
                    'tableName' => 'stock_available',
                    'tableAlias' => 'sa',
                    'joinCondition' => '(p.id_product=sa.id_product AND 0 = sa.id_product_attribute '.
                        $stockCondition.')',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'quantity' =>
                [
                    'tableName' => 'stock_available',
                    'tableAlias' => 'sa',
                    'joinCondition' => '(p.id_product=sa.id_product AND 0 = sa.id_product_attribute '.
                        $stockCondition.')',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'price_min' =>
                [
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'price_max' =>
                [
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'range_start' =>
                [
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'range_end' =>
                [
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ],
            'id_group' =>
                [
                    'tableName' => 'category_group',
                    'tableAlias' => 'cg',
                    'joinCondition' => '(cg.id_category = c.id_category)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'nleft'
                ],
        ];

        return $filterToTableMapping;
    }

    private function computeOrderByField($filterToTableMapping)
    {
        if (empty($this->orderField) || strpos($this->orderField, '.') !== false
            || strpos($this->orderField, '(') !== false) {
            return $this->orderField;
        }
        if (array_key_exists($this->orderField, $filterToTableMapping)) {
            $joinMapping = $filterToTableMapping[$this->orderField];
            $orderField = $joinMapping['tableAlias'].'.'.$this->orderField;
        } else {
            $orderField = 'p.'.$this->orderField;
        }

        return $orderField;
    }

    /**
     * @param array           $filterToTableMapping
     *
     * @return array
     */
    private function computeSelectFields($filterToTableMapping)
    {
        $selectFields = [];
        foreach ($this->selectFields as $key => $selectField) {
            $selectAlias = 'p';
            if (array_key_exists($selectField, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$selectField];
                $selectAlias = $joinMapping['tableAlias'];
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
     * @param array           $filterToTableMapping
     *
     * @return array
     */
    private function computeWhereConditions($filterToTableMapping)
    {
        $whereConditions = [];
        foreach ($this->filters as $filterName => $filterContent) {
            foreach ($filterContent as $operator => $values) {
                if (count($values) > 1) {
                    // @TODO : special treatment for intersect the result of two filters
                } else {
                    $values = current($values);
                    $selectAlias = 'p';
                    if (array_key_exists($filterName, $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$filterName];
                        $selectAlias = $joinMapping['tableAlias'];
                    }

                    if ($operator === '=') {
                        if (count($values) == 1) {
                            $whereConditions[] =
                                $selectAlias . '.' . $filterName . $operator . "'" . current($values) . "'";
                        } else {
                            $whereConditions[] =
                                $selectAlias . '.' . $filterName . ' IN (' . implode(', ', array_map(function ($value) {
                                    return "'" . $value . "'";
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

        foreach ($this->columnFilters as $filterName => $filterContent) {
            foreach ($filterContent as $operator => $columnNames) {
                foreach ($columnNames as $columnName) {
                    $selectAlias = 'p';
                    if (array_key_exists($filterName, $filterToTableMapping)) {
                        $joinMapping = $filterToTableMapping[$filterName];
                        $selectAlias = $joinMapping['tableAlias'];
                    }

                    $whereConditions[] = $selectAlias . '.' . $filterName . $operator . $columnName;
                }
            }
        }

        return $whereConditions;
    }

    /**
     * @param array $filterToTableMapping
     *
     * @return ArrayCollection
     */
    private function computeJoinConditions($filterToTableMapping)
    {
        $joinList = new ArrayCollection();

        foreach ($this->selectFields as $key => $selectField) {
            if (array_key_exists($selectField, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$selectField];
                $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
            }
        }

        foreach ($this->filters as $filterName => $filterContent) {
            if (array_key_exists($filterName, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$filterName];
                $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
            }
        }

        foreach ($this->groupFields as $groupFields => $filterContent) {
            if (array_key_exists($groupFields, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$groupFields];
                $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
            }
        }

        if (array_key_exists($this->orderField, $filterToTableMapping)) {
            $joinMapping = $filterToTableMapping[$this->orderField];
            $this->addJoinConditions($joinList, $joinMapping, $filterToTableMapping);
        }

        return $joinList;
    }

    /**
     * @param ArrayCollection $joinList
     * @param array           $joinMapping
     * @param array           $filterToTableMapping
     *
     */
    private function addJoinConditions(ArrayCollection $joinList, $joinMapping, $filterToTableMapping)
    {
        if (array_key_exists('dependencyField', $joinMapping)) {
            $dependencyJoinMapping = $filterToTableMapping[$joinMapping['dependencyField']];
            $this->addJoinConditions($joinList, $dependencyJoinMapping, $filterToTableMapping);
        }
        $joinInfos[$joinMapping['tableAlias']] = [
            'joinCondition' => $joinMapping['joinCondition'],
            'joinType' => $joinMapping['joinType']
        ];

        $joinList->set($joinMapping['tableName'], $joinInfos);
    }

    private function computeGroupByFields($filterToTableMapping)
    {
        $groupFields = [];
        if (empty($this->groupFields)) {
            return $groupFields;
        }

        foreach ($this->groupFields as $key => $values) {
            if (strpos($values, '.') !== false
                || strpos($values, '(') !== false) {
                $groupFields[$key] = $values;
                continue;
            }
            if (array_key_exists($values, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$values];
                $groupFields[$key] = $joinMapping['tableAlias'].'.'.$values;
            } else {
                $groupFields[$key] = 'p.'.$values;
            }
        }

        return $groupFields;
    }

    public function getMinMaxValue($fieldName)
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['MIN('.$fieldName.') as min, MAX('.$fieldName.') as max']);
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return [0 => $result[0]['min'], 1 => $result[0]['max']];
    }

    public function getFieldRanges($fieldName, $outputLength)
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields([$fieldName, 'ROUND((-MIN('.$fieldName.') + MAX('.$fieldName.')) / '.
            $outputLength.') AS diff']);
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();
        $diff = $result[0]['diff'];

        if ($diff == 0) {
            $diff = 1;
        }

        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields([$fieldName, 'FLOOR('.$fieldName.'/'.$diff.')*'.$diff.' as range_start',
            '(FLOOR('.$fieldName.'/'.$diff.')+1)*'.$diff.'-1 as range_end', 'COUNT(DISTINCT(p.id_product)) nbr']);
        $mysqlAdapter->addGroupBy('FLOOR('.$fieldName.' / '.$diff.')');
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        return $mysqlAdapter->execute();
    }

    public function count()
    {
        $mysqlAdapter = $this->getFilteredSearchAdapter();
        $mysqlAdapter->copyFilters($this);
        $mysqlAdapter->setSelectFields(['COUNT(DISTINCT p.id_product) c']);
        $mysqlAdapter->setLimit(null);
        $mysqlAdapter->setOrderField('');

        $result = $mysqlAdapter->execute();

        return $result[0]['c'];
    }

    public function valueCount($fieldName)
    {
        $this->resetGroupBy();
        $this->addGroupBy($fieldName);
        $this->addSelectField($fieldName);
        $this->addSelectField('COUNT(DISTINCT p.id_product) c');
        $this->setLimit(null);
        $this->setOrderField('');
        $results = $this->execute();
        return $results;
    }


    public function useFiltersAsInitialPopulation()
    {
        $this->setLimit(null);
        $this->setOrderField('');
        $this->setSelectFields(['id_product', 'id_manufacturer', 'quantity', 'condition', 'weight', 'price']);
        $this->initialPopulation = clone $this;
        $this->resetAllFilters();
    }
}

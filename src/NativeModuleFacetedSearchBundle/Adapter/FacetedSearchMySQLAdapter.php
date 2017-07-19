<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

use Doctrine\Common\Collections\ArrayCollection;

class FacetedSearchMySQLAdapter extends FacetedSearchAbstract
{
    const LEFT_JOIN = 'LEFT JOIN';
    const INNER_JOIN = 'INNER JOIN';

    private static $referenceTable = _DB_PREFIX_.'product';
    private static $referenceAlias = 'p';

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function getFilteredSearchAdapter($resetFilter = null, $skipInitialPopulation = false)
    {
        $mysqlAdapter = new self();
        if ($this->initialPopulation && !$skipInitialPopulation) {
            $mysqlAdapter->initialPopulation = clone $this->initialPopulation;
            if ($resetFilter) {
                $mysqlAdapter->initialPopulation->resetFilter($resetFilter);
            }
        }

        return $mysqlAdapter;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        return \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($this->getQuery());
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
        if ($this->initialPopulation === null) {
            $referenceTable = self::$referenceTable;
        } else {
            $referenceTable = '('.$this->initialPopulation->getQuery().')';
        }

        if (empty($this->selectFields) && empty($this->filters) && empty($this->groupFields)
            && empty($this->orderField)) {
            // avoid adding an extra SELECT FROM (SELECT ...) if it's not needed
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

        if ($orderField) {
            $query .= ' ORDER BY ' . $orderField . ' ' . $this->orderDirection;
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
        $stockCondition = \StockAvailable::addSqlShopRestriction(
            null,
            null,
            'sa'
        );

        $filterToTableMapping = array(
            'id_product_attribute' =>
                array(
                    'tableName' => 'product_attribute',
                    'tableAlias' => 'pa',
                    'joinCondition' => '(p.id_product = pa.id_product)',
                    'joinType' => self::LEFT_JOIN
                ),
            'id_attribute' =>
                array(
                    'tableName' => 'product_attribute_combination',
                    'tableAlias' => 'pac',
                    'joinCondition' => '(pa.id_product_attribute = pac.id_product_attribute)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'id_attribute_group' =>
                array(
                    'tableName' => 'attribute',
                    'tableAlias' => 'a',
                    'joinCondition' => '(a.id_attribute = pac.id_attribute)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_attribute'
                ),
            'id_feature' =>
                array(
                    'tableName' => 'feature_product',
                    'tableAlias' => 'fp',
                    'joinCondition' => '(p.id_product = fp.id_product)',
                    'joinType' => self::INNER_JOIN
                ),
            'id_shop' =>
                array(
                    'tableName' => 'product_shop',
                    'tableAlias' => 'ps',
                    'joinCondition' => '(p.id_product = ps.id_product AND ps.id_shop = '.
                        \Context::getContext()->shop->id.')',
                    'joinType' => self::INNER_JOIN
                ),
            'id_feature_value' =>
                array(
                    'tableName' => 'feature_product',
                    'tableAlias' => 'fp',
                    'joinCondition' => '(p.id_product = fp.id_product)',
                    'joinType' => self::LEFT_JOIN
                ),
            'id_category' =>
                array(
                    'tableName' => 'category_product',
                    'tableAlias' => 'cp',
                    'joinCondition' => '(p.id_product = cp.id_product)',
                    'joinType' => self::INNER_JOIN
                ),
            'position' =>
                array(
                    'tableName' => 'category_product',
                    'tableAlias' => 'cp',
                    'joinCondition' => '(p.id_product = cp.id_product)',
                    'joinType' => self::INNER_JOIN
                ),
            'name' =>
                array(
                    'tableName' => 'product_lang',
                    'tableAlias' => 'pl',
                    'joinCondition' => '(p.id_product = pl.id_product AND pl.id_shop = '.
                        \Context::getContext()->shop->id.' AND pl.id_lang = '.\Context::getContext()->language->id.')',
                    'joinType' => self::INNER_JOIN
                ),
            'nleft' =>
                array(
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ),
            'nright' =>
                array(
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ),
            'level_depth' =>
                array(
                    'tableName' => 'category',
                    'tableAlias' => 'c',
                    'joinCondition' => '(cp.id_category = c.id_category AND c.active=1)',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_category'
                ),
            'out_of_stock' =>
                array(
                    'tableName' => 'stock_available',
                    'tableAlias' => 'sa',
                    'joinCondition' => '(p.id_product=sa.id_product AND 0 = sa.id_product_attribute '.
                        $stockCondition.')',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'quantity' =>
                array(
                    'tableName' => 'stock_available',
                    'tableAlias' => 'sa',
                    'joinCondition' => '(p.id_product=sa.id_product AND 0 = sa.id_product_attribute '.
                        $stockCondition.')',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'price_min' =>
                array(
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'price_max' =>
                array(
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'range_start' =>
                array(
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'range_end' =>
                array(
                    'tableName' => 'layered_price_index',
                    'tableAlias' => 'psi',
                    'joinCondition' => '(psi.id_product = p.id_product AND psi.id_currency = '.
                        \Context::getContext()->currency->id.' AND psi.id_country = '.\Context::getContext()->country->id.')',
                    'joinType' => self::INNER_JOIN,
                    'dependencyField' => 'id_product_attribute'
                ),
            'id_group' =>
                array(
                    'tableName' => 'category_group',
                    'tableAlias' => 'cg',
                    'joinCondition' => '(cg.id_category = c.id_category)',
                    'joinType' => self::LEFT_JOIN,
                    'dependencyField' => 'nleft'
                ),
        );

        return $filterToTableMapping;
    }

    /**
     * Compute the orderby fields, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return string
     */
    private function computeOrderByField($filterToTableMapping)
    {
        // do not try to process the orderField if it already has an alias, or if it's a group function
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
     * Compute the select fields, adding the proper alias that will be added to the final query
     *
     * @param array           $filterToTableMapping
     *
     * @return array
     */
    private function computeSelectFields($filterToTableMapping)
    {
        $selectFields = array();
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
     * Computer the where conditions that will be added to the final query
     *
     * @param array           $filterToTableMapping
     *
     * @return array
     */
    private function computeWhereConditions($filterToTableMapping)
    {
        $whereConditions = array();
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


        foreach ($this->filters as $filterName => $filterContent) {
            $selectAlias = 'p';
            if (array_key_exists($filterName, $filterToTableMapping)) {
                $joinMapping = $filterToTableMapping[$filterName];
                $selectAlias = $joinMapping['tableAlias'];
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
                                    return "'" . $value . "'";
                                }, $values)) . ')';
                        }
                    } else {
                        $orConditions = array();
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
        foreach ($this->filters as $filterName => $filterContent) {
            foreach ($filterContent as $operator => $filterValues) {
                if (count($filterValues) > 1) {
                    foreach($filterValues as $values) {
                        $idTmpFilteredProducts = array();
                        $mysqlAdapter = $this->getFilteredSearchAdapter();
                        $mysqlAdapter->addSelectField('id_product');
                        $mysqlAdapter->setLimit(null);
                        $mysqlAdapter->setOrderField('');
                        $mysqlAdapter->addFilter($filterName, $values, $operator);
                        $idProducts = $mysqlAdapter->execute();
                        foreach($idProducts as $idProduct) {
                            $idTmpFilteredProducts[] = $idProduct['id_product'];
                        }

                        if ($idFilteredProducts === null) {
                            $idFilteredProducts = $idTmpFilteredProducts;
                        } else {
                            $idFilteredProducts = array_intersect($idFilteredProducts, $idTmpFilteredProducts);
                        }
                        if (empty($idFilteredProducts)) {
                            // set it to 0 to make sure no result will be returned
                            $idFilteredProducts[] = 0;
                            break;
                        }
                    }

                    $whereConditions[] =
                        'p.id_product IN (' . implode(', ', array_map(function ($value) {
                            return "'" . $value . "'";
                        }, $idFilteredProducts)) . ')';
                }
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
     * Add the required table infos to the join list, taking care of the dependent tables
     *
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

    /**
     * Compute the groupby condition, adding the proper alias that will be added to the final query
     *
     * @param array $filterToTableMapping
     *
     * @return array
     */
    private function computeGroupByFields($filterToTableMapping)
    {
        $groupFields = array();
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
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

    /**
     * @inheritdoc
     */
    public function useFiltersAsInitialPopulation()
    {
        $this->setLimit(null);
        $this->setOrderField('');
        $this->setSelectFields(['id_product', 'id_manufacturer', 'quantity', 'condition', 'weight', 'price']);
        $this->initialPopulation = clone $this;
        $this->resetAll();
        $this->addSelectField('id_product');
    }
}

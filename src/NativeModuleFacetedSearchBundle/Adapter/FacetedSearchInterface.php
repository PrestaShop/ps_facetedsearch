<?php

namespace NativeModuleFacetedSearchBundle\Adapter;

interface FacetedSearchInterface
{
    /**
     * Set order by field
     *
     * @param string $fieldName
     *
     * @return self
     */
    public function setOrderField($fieldName);

    /**
     * Set the order by direction for the given field
     *
     * @param string $direction
     *
     * @return self
     */
    public function setOrderDirection($direction);

    /**
     * Set the limit and offset associated with the current search
     *
     * @param int $limit
     * @param int $offset
     *
     * @return self
     */
    public function setLimit($limit, $offset = 0);

    /**
     * Execute the search
     *
     * @return mixed
     */
    public function execute();

    /**
     * Get the min & max value of the field filedName associated with the current search
     *
     * @param string $fieldName
     *
     * @return mixed
     */
    public function getMinMaxValue($fieldName);

    /**
     * Get the min & max value of the price associated with the current search
     *
     * @return array
     */
    public function getMinMaxPriceValue();

    /**
     * Get the range of values + associated number of product for the given fieldName
     * It try to generate the required outputLength number of ranges, if possible
     *
     * @param string $fieldName
     * @param int    $outputLength
     *
     * @return mixed
     */
    public function getFieldRanges($fieldName, $outputLength);

    /**
     * Return all the filters associated with the current search
     *
     * @return mixed
     */
    public function getFilters();

    /**
     * Return all the column filters associated with the current search
     *
     * @return mixed
     */
    public function getColumnFilters();

    /**
     * Return the number of results associated for the current search
     *
     * @return int
     */
    public function count();

    /**
     * Move the current search into the "initialPopulation"
     * This initialPopulation will be used to generate the first derived table 'FROM (SELECT ...)' in the final query
     * e.g. : SELECT ... FROM (initialPopulation) p JOIN ....
     */
    public function useFiltersAsInitialPopulation();

    /**
     * Create a new SearchAdapter, keeping the initialPopulation of the current Search
     *
     * @param string $resetFilter reset this filter inside the initialPopulation
     * @param bool   $skipInitialPopulation if enable, do not copy the initialPopulation filter
     *
     * @return FacetedSearchInterface
     */
    public function getFilteredSearchAdapter($resetFilter = null, $skipInitialPopulation = false);

    /**
     * Add a new filter with filterName, operator & values to the current search
     * If several values are provided with the = operator, it's converted automatically to a IN () in the final query
     *
     * @param string $filterName
     * @param array  $values
     * @param string $operator
     *
     * @return self
     */
    public function addFilter($filterName, $values, $operator = '=');

    /**
     * Add a column filter
     * E.g. WHERE price_min=price_max
     *
     * @param string $filterName
     * @param string $columnName
     * @param string $operator
     *
     * @return self
     */
    public function addColumnFilter($filterName, $columnName, $operator = '=');

    /**
     * Add fieldName in the current search result
     *
     * @param string $fieldName
     *
     * @return self
     */
    public function addSelectField($fieldName);

    /**
     * Returns the number of distinct products, group by fieldName values
     *
     * @param string $fieldName
     *
     * @return mixed
     */
    public function valueCount($fieldName);

    /**
     * Reset the column filters
     *
     * @return self
     */
    public function resetColumnFilters();

    /**
     * Reset the filter for the given filterName
     *
     * @param string $filterName
     *
     * @return mixed
     */
    public function resetFilter($filterName);

    /**
     * Return the filter associated with filterName
     *
     * @param string $filterName
     *
     * @return mixed
     */
    public function getFilter($filterName);

    /**
     * Set the filterName to the given array value
     *
     * @param string $filterName
     * @param array  $value
     *
     * @return mixed
     */
    public function setFilter($filterName, $value);

    /**
     * Return the current initialPopulation
     *
     * @return self
     */
    public function getInitialPopulation();

    /**
     * Return all the filters / groupFields / selectFields
     *
     * @return self
     */
    public function resetAll();

    /**
     * Copy all the filters & columnFilters from facetedSearch to the current search
     *
     * @param FacetedSearchInterface $facetedSearch
     */
    public function copyFilters(FacetedSearchInterface $facetedSearch);

    /**
     * Set all the select fields
     *
     * @param array $selectFields
     *
     * @return self
     */
    public function setSelectFields($selectFields);

    /**
     * Reset all the select fields
     *
     * @return self
     */
    public function resetSelectField();

    /**
     * Add a group by field
     *
     * @param string $groupField
     *
     * @return self
     */
    public function addGroupBy($groupField);

    /**
     * Set the group by fields
     *
     * @param array $groupFields
     *
     * @return self
     */
    public function setGroupFields($groupFields);

    /**
     * Reset the group by conditions
     *
     * @return self
     */
    public function resetGroupBy();
}

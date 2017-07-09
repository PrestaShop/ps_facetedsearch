<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

interface FacetedSearchInterface {
    function setOrderField($fieldName);

    function setOrderDirection($direction);

    function setLimit($limit);

    /**
     * @return array
     */
    function execute();

    function getMinMaxValue($fieldName);

    function getFieldRanges($fieldName, $outputLength);

    function count();

    function useFiltersAsInitialPopulation();

    /**
     * Return a new search adapter, initialized to restrict the results to the initial population
     *
     * @return FacetedSearchInterface
     */
    function getFilteredSearchAdapter();

    public function addFilter($filterName, $values, $operator = '=');

    public function addSelectField($fieldName);

    function valueCount($fieldName);
}
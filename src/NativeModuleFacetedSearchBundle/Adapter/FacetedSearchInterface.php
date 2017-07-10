<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

interface FacetedSearchInterface
{
    public function setOrderField($fieldName);

    public function setOrderDirection($direction);

    public function setLimit($limit);

    /**
     * @return array
     */
    public function execute();

    public function getMinMaxValue($fieldName);

    public function getMinMaxPriceValue();

    public function getFieldRanges($fieldName, $outputLength);

    public function count();

    public function useFiltersAsInitialPopulation();

    /**
     * Return a new search adapter, initialized to restrict the results to the initial population
     *
     * @return FacetedSearchInterface
     */
    public function getFilteredSearchAdapter();

    public function addFilter($filterName, $values, $operator = '=');

    public function addSelectField($fieldName);

    public function valueCount($fieldName);
}

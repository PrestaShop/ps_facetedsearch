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
}
<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

abstract class FacetedSearchAbstract implements FacetedSearchInterface {
    protected $filters = [];

    protected $priceFilters;

    protected $orderField = 'id_product';

    protected $orderDirection = 'DESC';

    protected $selectFields = [];

    protected $groupFields = [];

    protected $limit = 20;

    protected $offset = 0;

    public function __construct() {
    }

    /**
     * @param $filterName
     */
    public function resetFilter($filterName) {
        unset($this->filters[$filterName]);
    }

    public function resetAllFilters() {
        $this->enabledFilters = [];
        $this->selectFields = [];
        $this->groupFields = [];
        $this->filters = [];
    }

    /**
     * @param $filterName
     * @return mixed
     */
    public function getFilter($filterName) {
        if (isset($this->filters[$filterName])) {
            return $this->filters[$filterName];
        }

        return null;
    }

    public function getFilters() {
        return $this->filters;
    }

    public function setFilters($filters) {
        $this->filters = $filters;
    }

    /**
     * @param string $filterName
     * @param array $values
     * @param string $operator
     */
    public function addFilter($filterName, $values, $operator = '=') {
        $this->filters[$filterName][$operator] = $values;
    }

    public function addSelectField($fieldName) {
        $this->selectFields[] = $fieldName;
    }

    public function setSelectFields($selectFields) {
        $this->selectFields = $selectFields;
    }

    public function resetSelectField() {
        $this->selectFields = [];
    }

    public function addGroupBy($groupField) {
        $this->groupFields[] = $groupField;
    }

    public function setGroupFields($groupFields) {
        $this->groupFields = $groupFields;
    }

    public function resetGroupBy() {
        $this->groupFields = [];
    }

    /**
     * @param $filterName
     * @param $value
     */
    public function setFilter($filterName, $value) {
        if ($value === null) {
            return;
        }
        $this->filters[$filterName] = $value;
    }

    /**
     * @param string $filterName
     * @param array $values
     * @param string $operator
     */
    public function addPriceFilter($filterName, $values, $operator = '=') {
        $this->priceFilters[$filterName][$operator][] = $values;
    }

    /**
     * @param $fieldName
     */
    public function setOrderField($fieldName) {
        $this->orderField = $fieldName;
    }

    /**
     * @param $direction
     */
    public function setOrderDirection($direction) {
        $this->orderDirection = $direction;
    }

    /**
     * @param $limit
     */
    public function setLimit($limit, $offset = 0) {
        $this->limit = $limit;
        $this->offset = $offset;
    }
}
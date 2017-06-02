<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

abstract class FacetedSearchAbstract implements FacetedSearchInterface {
    private $filters;

    private $priceFilters;

    private $orderField = 'product';

    private $orderDirection = 'DESC';

    private $disableFiltersByDefault = false;

    private $enabledFilters = [];

    private $selectFields = [];

    private $groupFields = [];

    private $limit = 20;

    private $offset = 0;

    public function __construct() {
    }

    /**
     * @param $filterName
     */
    public function resetFilter($filterName) {
        unset($this->filters[$filterName]);
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

    public function resetSelectField() {
        $this->selectFields = [];
    }

    public function addGroupBy($groupField) {
        $this->groupFields[] = $groupField;
    }

    public function resetGroupBy() {
        $this->groupFields = [];
    }

    public function valueCount($fieldName, $extraSelectFields = []) {
        $filter = $this->getFilter($fieldName);
        $this->resetFilter($fieldName);
        $this->addGroupBy($fieldName);
        $this->addSelectField($fieldName);
        $this->addSelectField('COUNT(*) c');
        foreach($extraSelectFields as $extraSelectField) {
            $this->addSelectField($extraSelectField);
        }
        $results = $this->execute();
        $this->resetGroupBy();
        $this->setFilter($fieldName, $filter);
        return $results;
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
     * @param $value
     */
    public function setDisableFiltersByDefault($value) {
        $this->disableFiltersByDefault = (bool) $value;
        $this->enabledFilters = [];
    }

    /**
     * @param $limit
     */
    public function setLimit($limit, $offset = 0) {
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function enableFilter($filterName) {
        $this->enabledFilters[$filterName] = true;
    }
}
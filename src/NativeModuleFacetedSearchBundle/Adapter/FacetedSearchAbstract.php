<?php
namespace NativeModuleFacetedSearchBundle\Adapter;

use Doctrine\Common\Collections\ArrayCollection;

abstract class FacetedSearchAbstract implements FacetedSearchInterface
{
    protected $filters;

    protected $columnFilters = [];

    protected $orderField = 'id_product';

    protected $orderDirection = 'DESC';

    protected $selectFields = [];

    protected $groupFields = [];

    protected $limit = 20;

    protected $offset = 0;

    /** @var FacetedSearchInterface */
    protected $initialPopulation = null;

    public function __construct()
    {
        $this->filters = new ArrayCollection();
    }

    public function getInitialPopulation()
    {
        return $this->initialPopulation;
    }

    /**
     * @param $filterName
     */
    public function resetFilter($filterName)
    {
        unset($this->filters[$filterName]);

        return $this;
    }

    public function resetColumnFilters()
    {
        $this->columnFilters = [];

        return $this;
    }

    public function resetAllFilters()
    {
        $this->selectFields = [];
        $this->groupFields = [];
        $this->filters = [];
        $this->columnFilters = [];

        return $this;
    }

    /**
     * @param $filterName
     * @return mixed
     */
    public function getFilter($filterName)
    {
        if (isset($this->filters[$filterName])) {
            return $this->filters[$filterName];
        }

        return null;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function copyFilters(FacetedSearchInterface $facetedSearch)
    {
        $this->filters = $facetedSearch->getFilters();
        $this->columnFilters = $facetedSearch->getColumnFilters();
    }

    public function getColumnFilters()
    {
        return $this->columnFilters;
    }

    /**
     * @param string $filterName
     * @param array $values
     * @param string $operator
     */
    public function addFilter($filterName, $values, $operator = '=')
    {
        $filters = $this->filters->get($filterName);
        $filters[$operator][] = $values;
        $this->filters->set($filterName, $filters);
    }

    public function addColumnFilter($filterName, $columnName, $operator = '=')
    {
        $this->columnFilters[$filterName][$operator][] = $columnName;

        return $this;
    }

    public function addSelectField($fieldName)
    {
        $this->selectFields[] = $fieldName;

        return $this;
    }

    public function setSelectFields($selectFields)
    {
        $this->selectFields = $selectFields;

        return $this;
    }

    public function resetSelectField()
    {
        $this->selectFields = [];

        return $this;
    }

    public function addGroupBy($groupField)
    {
        $this->groupFields[] = $groupField;

        return $this;
    }

    public function setGroupFields($groupFields)
    {
        $this->groupFields = $groupFields;

        return $this;
    }

    public function resetGroupBy()
    {
        $this->groupFields = [];

        return $this;
    }

    /**
     * @param $filterName
     * @param $value
     */
    public function setFilter($filterName, $value)
    {
        if ($value === null) {
            return;
        }
        $this->filters[$filterName] = $value;

        return $this;
    }

    /**
     * @param $fieldName
     */
    public function setOrderField($fieldName)
    {
        $this->orderField = $fieldName;

        return $this;
    }

    /**
     * @param $direction
     */
    public function setOrderDirection($direction)
    {
        $this->orderDirection = $direction;

        return $this;
    }

    /**
     * @param $limit
     */
    public function setLimit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }
}

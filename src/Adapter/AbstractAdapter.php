<?php

namespace PrestaShop\Module\FacetedSearch\Adapter;

use Doctrine\Common\Collections\ArrayCollection;

abstract class AbstractAdapter implements InterfaceAdapter
{
    protected $filters;

    protected $columnFilters = array();

    protected $orderField = 'id_product';

    protected $orderDirection = 'DESC';

    protected $selectFields = array();

    protected $groupFields = array();

    protected $limit = 20;

    protected $offset = 0;

    /** @var InterfaceAdapter */
    protected $initialPopulation = null;

    public function __construct()
    {
        $this->filters = new ArrayCollection();
    }

    public function __clone()
    {
        $this->filters = clone $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function getInitialPopulation()
    {
        return $this->initialPopulation;
    }

    /**
     * @inheritdoc
     */
    public function resetFilter($filterName)
    {
        unset($this->filters[$filterName]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function resetColumnFilters()
    {
        $this->columnFilters = array();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function resetAll()
    {
        $this->selectFields = array();
        $this->groupFields = array();
        $this->filters = array();
        $this->columnFilters = array();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFilter($filterName)
    {
        if (isset($this->filters[$filterName])) {
            return $this->filters[$filterName];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function copyFilters(InterfaceAdapter $facetedSearch)
    {
        $this->filters = $facetedSearch->getFilters();
        $this->columnFilters = $facetedSearch->getColumnFilters();
    }

    /**
     * @inheritdoc
     */
    public function getColumnFilters()
    {
        return $this->columnFilters;
    }

    /**
     * @inheritdoc
     */
    public function addFilter($filterName, $values, $operator = '=')
    {
        $filters = $this->filters->get($filterName);
        $filters[$operator][] = $values;
        $this->filters->set($filterName, $filters);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addColumnFilter($filterName, $columnName, $operator = '=')
    {
        $this->columnFilters[$filterName][$operator][] = $columnName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addSelectField($fieldName)
    {
        $this->selectFields[] = $fieldName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setSelectFields($selectFields)
    {
        $this->selectFields = $selectFields;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function resetSelectField()
    {
        $this->selectFields = array();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addGroupBy($groupField)
    {
        $this->groupFields[] = $groupField;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setGroupFields($groupFields)
    {
        $this->groupFields = $groupFields;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function resetGroupBy()
    {
        $this->groupFields = array();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setFilter($filterName, $value)
    {
        if ($value === null) {
            return $this;
        }
        $this->filters[$filterName] = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setOrderField($fieldName)
    {
        $this->orderField = $fieldName;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setOrderDirection($direction)
    {
        $this->orderDirection = $direction;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setLimit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }
}

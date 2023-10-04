<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\FacetedSearch\Product;

use Category;
use Configuration;
use Context;
use FrontController;
use Group;
use PrestaShop\Module\FacetedSearch\Adapter\AbstractAdapter;
use PrestaShop\Module\FacetedSearch\Adapter\MySQL as MySQLAdapter;
use PrestaShop\Module\FacetedSearch\Definition\Availability;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;

class Search
{
    const STOCK_MANAGEMENT_FILTER = 'with_stock_management';
    const HIGHLIGHTS_FILTER = 'extras';

    /**
     * @var bool
     */
    protected $psStockManagement;

    /**
     * @var bool
     */
    protected $psOrderOutOfStock;

    /**
     * @var AbstractAdapter
     */
    protected $searchAdapter;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductSearchQuery
     */
    protected $query;

    /**
     * Search constructor.
     *
     * @param Context $context
     * @param string $adapterType
     */
    public function __construct(Context $context, $adapterType = MySQLAdapter::TYPE)
    {
        $this->context = $context;

        switch ($adapterType) {
            case MySQLAdapter::TYPE:
            default:
                $this->searchAdapter = new MySQLAdapter();
        }

        if ($this->psStockManagement === null) {
            $this->psStockManagement = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
        }

        if ($this->psOrderOutOfStock === null) {
            $this->psOrderOutOfStock = (bool) Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }
    }

    /**
     * @return AbstractAdapter
     */
    public function getSearchAdapter()
    {
        return $this->searchAdapter;
    }

    /**
     * @return ProductSearchQuery
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param ProductSearchQuery $query
     *
     * @return $this
     */
    public function setQuery(ProductSearchQuery $query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Init the initial population of the search filter
     *
     * @param array $selectedFilters
     */
    public function initSearch($selectedFilters)
    {
        // Adds basic filters that are common for every search, like shop and group limitations
        $this->addCommonFilters();

        // Add filters that the user has selected for current query
        $this->addSearchFilters($selectedFilters);

        // Adds filters that specific for this controller
        $this->addControllerSpecificFilters();

        // Add group by to remove duplicate values
        $this->getSearchAdapter()->addGroupBy('id_product');

        // Move the current search into the "initialPopulation"
        // This initialPopulation will be used to generate the base table in the final query
        $this->getSearchAdapter()->useFiltersAsInitialPopulation();
    }

    /**
     * Adds filters that the user has specifically selected for current query
     *
     * @param array $selectedFilters
     */
    private function addSearchFilters($selectedFilters)
    {
        foreach ($selectedFilters as $key => $filterValues) {
            if (!count($filterValues)) {
                continue;
            }

            switch ($key) {
                case 'id_feature':
                    $operationsFilter = [];
                    foreach ($filterValues as $featureId => $filterValue) {
                        $this->getSearchAdapter()->addOperationsFilter(
                            'with_features_' . $featureId,
                            [[['id_feature_value', $filterValue]]]
                        );
                    }
                    break;

                case 'id_attribute_group':
                    $operationsFilter = [];
                    foreach ($filterValues as $attributeId => $filterValue) {
                        $this->getSearchAdapter()->addOperationsFilter(
                            'with_attributes_' . $attributeId,
                            [[['id_attribute', $filterValue]]]
                        );
                    }
                    break;

                case 'category':
                    $this->addFilter('id_category', $filterValues);
                    break;

                case 'extras':
                    // Filter for new products
                    if (in_array('new', $filterValues)) {
                        $timeCondition = date(
                            'Y-m-d 00:00:00',
                            strtotime(
                                ((int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT') > 0 ?
                                '-' . ((int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT') - 1) . ' days' :
                                '+ 1 days')
                            )
                        );
                        // Reset filter to prevent two same filters if we are on new products page
                        $this->getSearchAdapter()->addFilter('date_add', ["'" . $timeCondition . "'"], '>');
                    }

                    // Filter for discounts - they must work as OR
                    $operationsFilter = [];
                    if (in_array('discount', $filterValues)) {
                        $operationsFilter[] = [
                            ['reduction', [0], '>'],
                        ];
                    }
                    if (in_array('sale', $filterValues)) {
                        $operationsFilter[] = [
                            ['on_sale', [1], '='],
                        ];
                    }
                    if (!empty($operationsFilter)) {
                        $this->getSearchAdapter()->addOperationsFilter(
                            self::HIGHLIGHTS_FILTER,
                            $operationsFilter
                        );
                    }
                    break;

                case 'availability':
                    /*
                    * $filterValues options can have following values:
                    * 0 - Not available - 0 or less quantity and disabled backorders
                    * 1 - Available - Positive quantity or enabled backorders
                    * 2 - In stock - Positive quantity
                    */

                    // If all three values are checked, we show everything
                    if (count($filterValues) == 3) {
                        break;
                    }

                    // If stock management is deactivated, we show everything
                    if (!$this->psStockManagement) {
                        break;
                    }

                    $operationsFilter = [];

                    // Simple cases with 1 option selected
                    if (count($filterValues) == 1) {
                        // Not available
                        if ($filterValues[0] == Availability::NOT_AVAILABLE) {
                            $operationsFilter[] = [
                                ['quantity', [0], '<='],
                                ['out_of_stock', $this->psOrderOutOfStock ? [0] : [0, 2], '='],
                            ];
                        // Available
                        } elseif ($filterValues[0] == Availability::AVAILABLE) {
                            $operationsFilter[] = [
                                ['out_of_stock', $this->psOrderOutOfStock ? [1, 2] : [1], '='],
                            ];
                            $operationsFilter[] = [
                                ['quantity', [0], '>'],
                            ];
                        // In stock
                        } elseif ($filterValues[0] == Availability::IN_STOCK) {
                            $operationsFilter[] = [
                                ['quantity', [0], '>'],
                            ];
                        }
                        // Cases with 2 options selected
                    } elseif (count($filterValues) == 2) {
                        // Not available and available, we show everything
                        if (in_array(Availability::NOT_AVAILABLE, $filterValues) && in_array(Availability::AVAILABLE, $filterValues)) {
                            break;
                        // Not available or in stock
                        } elseif (in_array(Availability::NOT_AVAILABLE, $filterValues) && in_array(Availability::IN_STOCK, $filterValues)) {
                            $operationsFilter[] = [
                                ['quantity', [0], '<='],
                                ['out_of_stock', $this->psOrderOutOfStock ? [0] : [0, 2], '='],
                            ];
                            $operationsFilter[] = [
                                ['quantity', [0], '>'],
                            ];
                        // Available or in stock
                        } elseif (in_array(Availability::AVAILABLE, $filterValues) && in_array(Availability::IN_STOCK, $filterValues)) {
                            $operationsFilter[] = [
                                ['out_of_stock', $this->psOrderOutOfStock ? [1, 2] : [1], '='],
                            ];
                            $operationsFilter[] = [
                                ['quantity', [0], '>'],
                            ];
                        }
                    }

                    $this->getSearchAdapter()->addOperationsFilter(
                        self::STOCK_MANAGEMENT_FILTER,
                        $operationsFilter
                    );
                    break;

                case 'manufacturer':
                    $this->addFilter('id_manufacturer', $filterValues);
                    break;

                case 'condition':
                    if (count($selectedFilters['condition']) == 3) {
                        break;
                    }
                    $this->addFilter('condition', $filterValues);
                    break;

                case 'weight':
                    if (!empty($selectedFilters['weight'][0]) || !empty($selectedFilters['weight'][1])) {
                        $this->getSearchAdapter()->addFilter(
                            'weight',
                            [(float) $selectedFilters['weight'][0]],
                            '>='
                        );
                        $this->getSearchAdapter()->addFilter(
                            'weight',
                            [(float) $selectedFilters['weight'][1]],
                            '<='
                        );
                    }
                    break;

                case 'price':
                    if (isset($selectedFilters['price'])
                        && (
                            $selectedFilters['price'][0] !== '' || $selectedFilters['price'][1] !== ''
                        )
                    ) {
                        $this->addPriceFilter(
                            (float) $selectedFilters['price'][0],
                            (float) $selectedFilters['price'][1]
                        );
                    }
                    break;
            }
        }
    }

    /**
     * Adds filters that are common for every search
     */
    private function addCommonFilters()
    {
        // Setting proper shop
        $this->getSearchAdapter()->addFilter('id_shop', [(int) $this->context->shop->id]);

        // Visibility of a product must be in catalog or both (search & catalog)
        $this->addFilter('visibility', ['both', 'catalog']);

        // User must belong to one of the groups that can access the product
        // (Actually it's categories that define access to a product, user must have access to at least
        // one category the product is assigned to.)
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $this->addFilter('id_group', empty($groups) ? [Group::getCurrent()->id] : $groups);
        }
    }

    /**
     * Adds filters that specific for category page
     */
    private function addControllerSpecificFilters()
    {
        // Category page
        if ($this->query->getQueryType() == 'category') {
            // We check if some specific filter of this type wasn't added before by the customer
            if (!empty($this->getSearchAdapter()->getFilter('id_category'))) {
                return;
            }

            // Get category ID from the query or home category as a fallback
            $idCategory = (int) $this->query->getIdCategory();
            if (empty($idCategory)) {
                $idCategory = (int) Configuration::get('PS_HOME_CATEGORY');
            }
            $category = new Category((int) $idCategory);

            // If we want to display only products from this category AND not it's subcategories,
            // we add this one specific category ID, otherwise, we will add everything using nleft and nright
            if (Configuration::get('PS_LAYERED_FULL_TREE')) {
                $this->getSearchAdapter()->addFilter('nleft', [$category->nleft], '>=');
                $this->getSearchAdapter()->addFilter('nright', [$category->nright], '<=');
            } else {
                $this->addFilter('id_category', [$idCategory]);
            }

            // If we want to display products, which have this category as their default category
            if (Configuration::get('PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY')) {
                $this->addFilter('id_category_default', [$idCategory]);
            }
        }

        // Manufacturer controller
        if ($this->query->getQueryType() == 'manufacturer') {
            $this->getSearchAdapter()->addFilter('id_manufacturer', [$this->query->getIdManufacturer()]);
        }

        // Supplier controller
        if ($this->query->getQueryType() == 'supplier') {
            $this->getSearchAdapter()->addFilter('id_supplier', [$this->query->getIdSupplier()]);
        }

        /*
         * New products controller
         *
         * Comparsion works works on a day basis, not 24 hours.
         * If you set 1 day, only products created TODAY will be new.
         * If there is a zero set to disable this feature, it creates unreachable condition.
         */
        if ($this->query->getQueryType() == 'new-products') {
            // We check if some specific filter of this type wasn't added before
            if (!empty($this->getSearchAdapter()->getFilter('date_add'))) {
                return;
            }

            $timeCondition = date(
                'Y-m-d 00:00:00',
                strtotime(
                    ((int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT') > 0 ?
                    '-' . ((int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT') - 1) . ' days' :
                    '+ 1 days')
                )
            );
            $this->getSearchAdapter()->addFilter('date_add', ["'" . $timeCondition . "'"], '>');
        }

        /*
         * Bestsellers controller
         *
         * We are selecting all products from product_sale table.
         */
        if ($this->query->getQueryType() == 'best-sales') {
            $this->getSearchAdapter()->addFilter('sales', [0], '>');
        }

        /*
         * Prices drop controller
         *
         * We are selecting products that have a specific price created meeting certain conditions.
         */
        if ($this->query->getQueryType() == 'prices-drop') {
            // We check if some specific filter of this type wasn't added before
            if (!empty($this->getSearchAdapter()->getFilter('reduction'))) {
                return;
            }

            $this->getSearchAdapter()->addFilter('reduction', [0], '>');
        }

        /*
         * Search controller
         *
         * We are using a fast backport to get a product pool, which is then passed to the query.
         * Core search provider does simmilar thing. If nothing is found, we return a value
         * (NULL string) that will ensure empty result. It would be better to stop the search
         * sooner in the logic, in the future.
         */
        if ($this->query->getQueryType() == 'search') {
            $productPool = (new CoreSearchBackport())->getProductPool($this->query);
            $this->getSearchAdapter()->addFilter(
                'id_product',
                empty($productPool) ? ['NULL'] : $productPool
            );
        }
    }

    /**
     * Add a filter with the filterValues extracted from the selectedFilters
     *
     * @param string $filterName
     * @param array $filterValues
     */
    public function addFilter($filterName, array $filterValues)
    {
        $values = [];
        foreach ($filterValues as $filterValue) {
            if (is_array($filterValue)) {
                foreach ($filterValue as $subFilterValue) {
                    $values[] = (int) $subFilterValue;
                }
            } else {
                $values[] = $filterValue;
            }
        }

        if (!empty($values)) {
            $this->getSearchAdapter()->addFilter($filterName, $values);
        }
    }

    /**
     * Add a price filter
     *
     * @param float $minPrice
     * @param float $maxPrice
     */
    private function addPriceFilter($minPrice, $maxPrice)
    {
        $this->getSearchAdapter()->addFilter('price_min', [$maxPrice], '<=');
        $this->getSearchAdapter()->addFilter('price_max', [$minPrice], '>=');
    }
}

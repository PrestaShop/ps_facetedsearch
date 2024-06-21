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
if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PrestaShop\Module\FacetedSearch\Filters\Converter;
use PrestaShop\Module\FacetedSearch\HookDispatcher;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Ps_Facetedsearch extends Module implements WidgetInterface
{
    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    const PS_16_EQUIVALENT_MODULE = 'blocklayered';

    /**
     * Lock indexation if too many products
     *
     * @var int
     */
    const LOCK_TOO_MANY_PRODUCTS = 5000;

    /**
     * Lock template filter creation if too many products
     *
     * @var int
     */
    const LOCK_TEMPLATE_CREATION = 20000;

    /**
     * US iso code, used to prevent taxes usage while computing prices
     *
     * @var array
     */
    const ISO_CODE_TAX_FREE = [
        'US',
    ];

    /**
     * Number of digits for MySQL DECIMAL
     *
     * @var int
     */
    const DECIMAL_DIGITS = 6;

    /**
     * @var array List of controllers supported by this module
     */
    protected $supportedControllers = [];

    /**
     * @var bool
     */
    private $ajax;

    /**
     * @var int
     */
    private $psLayeredFullTree;

    /**
     * @var Db
     */
    private $database;

    /**
     * @var HookDispatcher
     */
    private $hookDispatcher;

    public function __construct()
    {
        $this->name = 'ps_facetedsearch';
        $this->tab = 'front_office_features';
        $this->version = '3.16.1';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ajax = (bool) Tools::getValue('ajax');

        parent::__construct();

        $this->displayName = $this->trans('Faceted search', [], 'Modules.Facetedsearch.Admin');
        $this->description = $this->trans('Filter your catalog to help visitors picture the category tree and browse your store easily.', [], 'Modules.Facetedsearch.Admin');
        $this->psLayeredFullTree = (int) Configuration::get('PS_LAYERED_FULL_TREE');
        $this->ps_versions_compliancy = ['min' => '1.7.6.0', 'max' => _PS_VERSION_];

        $this->hookDispatcher = new HookDispatcher($this);

        $this->initializeSupportedControllers();
    }

    /**
     * Check if method is an ajax request.
     * This check is an old behavior and only check for _GET value.
     *
     * @return bool
     */
    public function isAjax()
    {
        return (bool) $this->ajax;
    }

    /**
     * Return the current database instance
     *
     * @return Db
     */
    public function getDatabase()
    {
        if ($this->database === null) {
            $this->database = Db::getInstance();
        }

        return $this->database;
    }

    /**
     * Return current context
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->context;
    }

    protected function getDefaultFilters()
    {
        return [
            'layered_selection_subcategories' => [
                'label' => 'Sub-categories filter',
            ],
            'layered_selection_stock' => [
                'label' => 'Product stock filter',
            ],
            'layered_selection_condition' => [
                'label' => 'Product condition filter',
            ],
            'layered_selection_manufacturer' => [
                'label' => 'Product brand filter',
            ],
            'layered_selection_weight_slider' => [
                'label' => 'Product weight filter (slider)',
                'slider' => true,
            ],
            'layered_selection_price_slider' => [
                'label' => 'Product price filter (slider)',
                'slider' => true,
            ],
            'layered_selection_extras' => [
                'label' => 'Product extras filter',
            ],
        ];
    }

    public function install()
    {
        $installed = parent::install()
                   && $this->registerHook($this->getHookDispatcher()->getAvailableHooks());

        // Installation failed (or hook registration) => uninstall the module
        if (!$installed) {
            $this->uninstall();

            return false;
        }

        if ($this->uninstallPrestaShop16Module()) {
            $this->rebuildLayeredStructure();
            $this->buildLayeredCategories();

            $this->rebuildPriceIndexTable();

            $this->getDatabase()->execute('ALTER TABLE ' . _DB_PREFIX_ . 'layered_filter CHANGE `filters` `filters` LONGTEXT NULL');
            $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_friendly_url');
        } else {
            Configuration::updateValue('PS_LAYERED_CACHE_ENABLED', 1);
            Configuration::updateValue('PS_LAYERED_SHOW_QTIES', 1);
            Configuration::updateValue('PS_LAYERED_FULL_TREE', 1);
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_USETAX', 1);
            Configuration::updateValue('PS_LAYERED_FILTER_CATEGORY_DEPTH', 1);
            Configuration::updateValue('PS_ATTRIBUTE_ANCHOR_SEPARATOR', '-');
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_ROUNDING', 1);
            Configuration::updateValue('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST', 0);
            Configuration::updateValue('PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY', 0);
            Configuration::updateValue('PS_USE_JQUERY_UI_SLIDER', 1);
            Configuration::updateValue('PS_LAYERED_DEFAULT_CATEGORY_TEMPLATE', 0);

            $this->psLayeredFullTree = 1;

            $this->rebuildLayeredStructure();
            $this->buildLayeredCategories();

            $productsCount = $this->getDatabase()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product`');

            if ($productsCount < static::LOCK_TEMPLATE_CREATION) {
                $this->createDefaultTemplate();
            }

            $this->rebuildPriceIndexTable();
            $this->installIndexableAttributeTable();
            $this->installProductAttributeTable();

            if ($productsCount < static::LOCK_TOO_MANY_PRODUCTS) {
                $this->fullPricesIndexProcess();
                $this->indexAttributes();
            }
        }

        return true;
    }

    public function uninstall()
    {
        /* Delete all configurations */
        Configuration::deleteByName('PS_LAYERED_CACHE_ENABLED');
        Configuration::deleteByName('PS_LAYERED_SHOW_QTIES');
        Configuration::deleteByName('PS_LAYERED_FULL_TREE');
        Configuration::deleteByName('PS_LAYERED_INDEXED');
        Configuration::deleteByName('PS_LAYERED_FILTER_PRICE_USETAX');
        Configuration::deleteByName('PS_LAYERED_FILTER_CATEGORY_DEPTH');
        Configuration::deleteByName('PS_LAYERED_FILTER_PRICE_ROUNDING');
        Configuration::deleteByName('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST');
        Configuration::deleteByName('PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY');

        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_category');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_filter');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_filter_block');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_filter_shop');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_attribute_group');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_feature');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_price_index');
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_product_attribute');

        return parent::uninstall();
    }

    /**
     * Migrate data from 1.6 equivalent module (if applicable), then uninstall
     */
    private function uninstallPrestaShop16Module()
    {
        if (!Module::isInstalled(self::PS_16_EQUIVALENT_MODULE)) {
            return false;
        }
        /** @var Module|bool $oldModule */
        $oldModule = Module::getInstanceByName(self::PS_16_EQUIVALENT_MODULE);
        if ($oldModule) {
            // This closure calls the parent class to prevent data to be erased
            // It allows the new module to be configured without migration
            $parentUninstallClosure = function () {
                return parent::uninstall();
            };
            $parentUninstallClosure = $parentUninstallClosure->bindTo($oldModule, get_class($oldModule));
            $parentUninstallClosure();
        }

        return true;
    }

    /**
     * @return HookDispatcher
     */
    public function getHookDispatcher()
    {
        return $this->hookDispatcher;
    }

    /*
     * Generate data product attributes
     *
     * @param int $idProduct
     *
     * @return boolean
     */
    public function indexAttributes($idProduct = null)
    {
        if (null === $idProduct) {
            $this->getDatabase()->execute('TRUNCATE ' . _DB_PREFIX_ . 'layered_product_attribute');
        } else {
            $this->getDatabase()->execute(
                'DELETE FROM ' . _DB_PREFIX_ . 'layered_product_attribute
                WHERE id_product = ' . (int) $idProduct
            );
        }

        return $this->getDatabase()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'layered_product_attribute` (`id_attribute`, `id_product`, `id_attribute_group`, `id_shop`)
            SELECT pac.id_attribute, pa.id_product, ag.id_attribute_group, product_attribute_shop.`id_shop`
            FROM ' . _DB_PREFIX_ . 'product_attribute pa' .
            Shop::addSqlAssociation('product_attribute', 'pa') . '
            INNER JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac ON pac.id_product_attribute = pa.id_product_attribute
            INNER JOIN ' . _DB_PREFIX_ . 'attribute a ON (a.id_attribute = pac.id_attribute)
            INNER JOIN ' . _DB_PREFIX_ . 'attribute_group ag ON ag.id_attribute_group = a.id_attribute_group
            ' . ($idProduct === null ? '' : 'AND pa.id_product = ' . (int) $idProduct) . '
            GROUP BY a.id_attribute, pa.id_product , product_attribute_shop.`id_shop`'
        );
    }

    /*
     * Generate data for product features
     *
     * @return boolean
     */
    public function indexFeatures()
    {
        return $this->getDatabase()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'layered_indexable_feature` ' .
            'SELECT id_feature, 1 FROM `' . _DB_PREFIX_ . 'feature` ' .
            'WHERE id_feature NOT IN (SELECT id_feature FROM ' .
            '`' . _DB_PREFIX_ . 'layered_indexable_feature`)'
        );
    }

    /*
     * Generate data for product attribute group
     *
     * @return boolean
     */
    public function indexAttributeGroup()
    {
        return $this->getDatabase()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'layered_indexable_attribute_group` ' .
            'SELECT id_attribute_group, 1 FROM `' . _DB_PREFIX_ . 'attribute_group` ' .
            'WHERE id_attribute_group NOT IN (SELECT id_attribute_group FROM ' .
            '`' . _DB_PREFIX_ . 'layered_indexable_attribute_group`)'
        );
    }

    /**
     * Full prices index process
     *
     * @param int $cursor in order to restart indexing from the last state
     * @param bool $ajax
     * @param bool $smart
     */
    public function fullPricesIndexProcess($cursor = 0, $ajax = false, $smart = false)
    {
        if ($cursor == 0 && !$smart) {
            $this->rebuildPriceIndexTable();
        }

        return $this->indexPrices($cursor, true, $ajax, $smart);
    }

    /**
     * Prices index process
     *
     * @param int $cursor in order to restart indexing from the last state
     * @param bool $ajax
     */
    public function pricesIndexProcess($cursor = 0, $ajax = false)
    {
        return $this->indexPrices($cursor, false, $ajax);
    }

    /**
     * Index product prices
     *
     * @param int $idProduct
     * @param bool $smart Delete before reindex
     */
    public function indexProductPrices($idProduct, $smart = true)
    {
        static $groups = null;

        if ($groups === null) {
            $groups = $this->getDatabase()->executeS('SELECT id_group FROM `' . _DB_PREFIX_ . 'group_reduction`');
            if (!$groups) {
                $groups = [];
            }
        }

        $shopList = Shop::getShops(false, null, true);

        foreach ($shopList as $idShop) {
            $currencyList = Currency::getCurrencies(false, 1, new Shop($idShop));

            $minPrice = [];
            $maxPrice = [];

            if ($smart) {
                $this->getDatabase()->execute('DELETE FROM `' . _DB_PREFIX_ . 'layered_price_index` WHERE `id_product` = ' . (int) $idProduct . ' AND `id_shop` = ' . (int) $idShop);
            }

            $taxRatesByCountry = $this->getDatabase()->executeS(
                'SELECT t.rate rate, tr.id_country, c.iso_code ' .
                'FROM `' . _DB_PREFIX_ . 'product_shop` p ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'tax_rules_group` trg ON  ' .
                '(trg.id_tax_rules_group = p.id_tax_rules_group AND p.id_shop = ' . (int) $idShop . ') ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'tax_rule` tr ON (tr.id_tax_rules_group = trg.id_tax_rules_group) ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'tax` t ON (t.id_tax = tr.id_tax AND t.active = 1) ' .
                'JOIN `' . _DB_PREFIX_ . 'country` c ON (tr.id_country=c.id_country AND c.active = 1) ' .
                'WHERE id_product = ' . (int) $idProduct . ' ' .
                'GROUP BY id_product, tr.id_country'
            );

            if (empty($taxRatesByCountry) || !Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX')) {
                $shopCountries = Country::getCountriesByIdShop($idShop, $this->getContext()->language->id);
                $taxCountries = array_filter($shopCountries, function ($country) {
                    return $country['active'];
                });
                $taxRatesByCountry = array_map(function ($country) {
                    return [
                        'rate' => 0,
                        'id_country' => $country['id_country'],
                        'iso_code' => $country['iso_code'],
                    ];
                }, $taxCountries);
            }

            $productMinPrices = $this->getDatabase()->executeS(
                'SELECT id_shop, id_currency, id_country, id_group, from_quantity
                FROM `' . _DB_PREFIX_ . 'specific_price`
                WHERE id_product = ' . (int) $idProduct . ' AND id_shop IN (0,' . (int) $idShop . ')'
            );

            $countries = Country::getCountries($this->getContext()->language->id, true, false, false);
            foreach ($countries as $country) {
                $idCountry = $country['id_country'];

                // Get price by currency & country, without reduction!
                foreach ($currencyList as $currency) {
                    $price = Product::priceCalculation(
                        $idShop,
                        (int) $idProduct,
                        null,
                        $idCountry,
                        null,
                        null,
                        $currency['id_currency'],
                        null,
                        null,
                        false,
                        6, // Decimals
                        false,
                        false,
                        true,
                        $specificPriceOutput,
                        true
                    );

                    $minPrice[$idCountry][$currency['id_currency']] = $price;
                    $maxPrice[$idCountry][$currency['id_currency']] = $price;
                }

                foreach ($productMinPrices as $specificPrice) {
                    foreach ($currencyList as $currency) {
                        if ($specificPrice['id_currency'] &&
                            $specificPrice['id_currency'] != $currency['id_currency']
                        ) {
                            continue;
                        }

                        $price = Product::priceCalculation(
                            $idShop,
                            (int) $idProduct,
                            null,
                            $idCountry,
                            null,
                            null,
                            $currency['id_currency'],
                            (($specificPrice['id_group'] == 0) ? null : $specificPrice['id_group']),
                            $specificPrice['from_quantity'],
                            false,
                            6,
                            false,
                            true,
                            true,
                            $specificPriceOutput,
                            true
                        );

                        if ($price > $maxPrice[$idCountry][$currency['id_currency']]) {
                            $maxPrice[$idCountry][$currency['id_currency']] = $price;
                        }

                        if ($price == 0) {
                            continue;
                        }

                        if (null === $minPrice[$idCountry][$currency['id_currency']] || $price < $minPrice[$idCountry][$currency['id_currency']]) {
                            $minPrice[$idCountry][$currency['id_currency']] = $price;
                        }
                    }
                }

                foreach ($groups as $group) {
                    foreach ($currencyList as $currency) {
                        $price = Product::priceCalculation(
                            $idShop,
                            (int) $idProduct,
                            null,
                            (int) $idCountry,
                            null,
                            null,
                            (int) $currency['id_currency'],
                            (int) $group['id_group'],
                            null,
                            false,
                            6,
                            false,
                            true,
                            true,
                            $specificPriceOutput,
                            true
                        );

                        if (!isset($maxPrice[$idCountry][$currency['id_currency']])) {
                            $maxPrice[$idCountry][$currency['id_currency']] = 0;
                        }

                        if (!isset($minPrice[$idCountry][$currency['id_currency']])) {
                            $minPrice[$idCountry][$currency['id_currency']] = null;
                        }

                        if ($price == 0) {
                            continue;
                        }

                        if (null === $minPrice[$idCountry][$currency['id_currency']] || $price < $minPrice[$idCountry][$currency['id_currency']]) {
                            $minPrice[$idCountry][$currency['id_currency']] = $price;
                        }

                        if ($price > $maxPrice[$idCountry][$currency['id_currency']]) {
                            $maxPrice[$idCountry][$currency['id_currency']] = $price;
                        }
                    }
                }
            }

            $values = [];
            foreach ($taxRatesByCountry as $taxRateByCountry) {
                $taxRate = $taxRateByCountry['rate'];
                $idCountry = $taxRateByCountry['id_country'];
                foreach ($currencyList as $currency) {
                    $minPriceValue = array_key_exists($idCountry, $minPrice) ? $minPrice[$idCountry][$currency['id_currency']] : 0;
                    $maxPriceValue = array_key_exists($idCountry, $maxPrice) ? $maxPrice[$idCountry][$currency['id_currency']] : 0;
                    if (!in_array($taxRateByCountry['iso_code'], self::ISO_CODE_TAX_FREE)) {
                        $minPriceValue = Tools::ps_round($minPriceValue * (100 + $taxRate) / 100, self::DECIMAL_DIGITS);
                        $maxPriceValue = Tools::ps_round($maxPriceValue * (100 + $taxRate) / 100, self::DECIMAL_DIGITS);
                    }

                    $values[] = '(' . (int) $idProduct . ',
                        ' . (int) $currency['id_currency'] . ',
                        ' . $idShop . ',
                        ' . (float) $minPriceValue . ',
                        ' . (float) $maxPriceValue . ',
                        ' . (int) $idCountry . ')';
                }
            }

            if (!empty($values)) {
                $this->getDatabase()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'layered_price_index` (id_product, id_currency, id_shop, price_min, price_max, id_country)
                     VALUES ' . implode(',', $values) . '
                     ON DUPLICATE KEY UPDATE id_product = id_product' // Avoid duplicate keys
                );
            }
        }
    }

    /**
     * Get page content
     */
    public function getContent()
    {
        $message = '';

        if (Tools::isSubmit('SubmitFilter')) {
            // Get filter data
            $templateName = Tools::getValue('layered_tpl_name');
            $controllers = Tools::getValue('controllers');
            $categoryBox = Tools::getValue('categoryBox');

            if (empty($templateName)) {
                $message = $this->displayError($this->trans('Filter template name required (cannot be empty)', [], 'Modules.Facetedsearch.Admin'));
            } elseif (empty($controllers)) {
                $message = $this->displayError($this->trans('You must select at least one page.', [], 'Modules.Facetedsearch.Admin'));
            } elseif (in_array('category', $controllers) && (empty($categoryBox) || !is_array($categoryBox))) {
                $message = $this->displayError($this->trans('You must select at least one category if you want to use this filter template on category pages.', [], 'Modules.Facetedsearch.Admin'));
            } else {
                // Prepare values
                $filterValues = [
                    'shop_list' => [],
                    'categories' => [],
                    'controllers' => [],
                ];

                // Associate shops in case of multistore
                if (isset($_POST['checkBoxShopAsso_layered_filter'])) {
                    foreach ($_POST['checkBoxShopAsso_layered_filter'] as $idShop => $row) {
                        $filterValues['shop_list'][] = (int) $idShop;
                    }
                } else {
                    $filterValues['shop_list'] = [(int) $this->getContext()->shop->id];
                }

                // Add categories to filter values
                if (!empty($categoryBox)) {
                    foreach ($categoryBox as $idCategory) {
                        $filterValues['categories'][] = (int) $idCategory;
                    }
                }

                // Add controllers to filter values
                foreach ($controllers as $controller) {
                    $filterValues['controllers'][] = $controller;
                }

                // Add filters themselves
                foreach ($_POST as $key => $value) {
                    if (!preg_match('~^(?P<key>layered_selection_.*)(?<!_filter_)(?<!type)(?<!show_limit)$~', $key, $matches)) {
                        continue;
                    }

                    $filterValues[$matches['key']] = [
                        'filter_type' => (int) Tools::getValue($matches['key'] . '_filter_type', 0),
                        'filter_show_limit' => (int) Tools::getValue($matches['key'] . '_filter_show_limit', 0),
                    ];
                }

                $filterData = [
                    'name' => pSQL($templateName),
                    'filters' => pSQL(serialize($filterValues)),
                    'n_categories' => (int) count($filterValues['categories']),
                ];

                // New filter or editing existing
                $idLayeredFilter = (int) Tools::getValue('id_layered_filter');
                if (!$idLayeredFilter) {
                    $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'layered_filter ' .
                            '(name, filters, n_categories, date_add, id_layered_filter) ' .
                            'VALUES (' .
                            '"' . pSQL($filterData['name']) . '", ' .
                            '"' . $filterData['filters'] . '", ' .
                            '' . (int) $filterData['n_categories'] . ', ' .
                            '"' . pSQL(date('Y-m-d H:i:s')) . '", ' .
                            '' . $idLayeredFilter . ')';
                    $this->getDatabase()->execute($sql);
                    $idLayeredFilter = (int) $this->getDatabase()->Insert_ID();
                } else {
                    $this->getDatabase()->execute(
                        'DELETE FROM ' . _DB_PREFIX_ . 'layered_filter_shop WHERE `id_layered_filter` = ' . (int) $idLayeredFilter
                    );
                    $sql = 'UPDATE ' . _DB_PREFIX_ . 'layered_filter ' .
                            'SET name = "' . pSQL($filterData['name']) . '", ' .
                            'filters = "' . $filterData['filters'] . '", ' .
                            'n_categories = ' . (int) $filterData['n_categories'] . ' ' .
                            'WHERE id_layered_filter = ' . $idLayeredFilter;
                    $this->getDatabase()->execute($sql);
                }

                // Add multistore associations to filters
                if (!empty($filterValues['shop_list'])) {
                    foreach ($filterValues['shop_list'] as $id_shop) {
                        $this->getDatabase()->execute(
                            'INSERT INTO ' . _DB_PREFIX_ . 'layered_filter_shop (`id_layered_filter`, `id_shop`)
                            VALUES(' . $idLayeredFilter . ', ' . (int) $id_shop . ')'
                        );
                    }
                }

                // Rebuild categories and confirm
                $this->buildLayeredCategories();
                $message = $this->displayConfirmation(
                    $this->trans('Your filter', [], 'Modules.Facetedsearch.Admin') . ' "' .
                    Tools::safeOutput($templateName) . '" ' .
                    (
                        !empty($_POST['id_layered_filter']) ?
                        $this->trans('was updated successfully.', [], 'Modules.Facetedsearch.Admin') :
                        $this->trans('was added successfully.', [], 'Modules.Facetedsearch.Admin')
                    )
                );
            }
        } elseif (Tools::isSubmit('submitLayeredSettings')) {
            Configuration::updateValue('PS_LAYERED_CACHE_ENABLED', (int) Tools::getValue('ps_layered_cache_enabled'));
            Configuration::updateValue('PS_LAYERED_SHOW_QTIES', (int) Tools::getValue('ps_layered_show_qties'));
            Configuration::updateValue('PS_LAYERED_FULL_TREE', (int) Tools::getValue('ps_layered_full_tree'));
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_USETAX', (int) Tools::getValue('ps_layered_filter_price_usetax'));
            Configuration::updateValue('PS_LAYERED_FILTER_CATEGORY_DEPTH', (int) Tools::getValue('ps_layered_filter_category_depth'));
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_ROUNDING', (int) Tools::getValue('ps_layered_filter_price_rounding'));
            Configuration::updateValue('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST', (int) Tools::getValue('ps_layered_filter_show_out_of_stock_last'));
            Configuration::updateValue('PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY', (int) Tools::getValue('ps_layered_filter_by_default_category'));
            Configuration::updateValue('PS_USE_JQUERY_UI_SLIDER', (int) Tools::getValue('ps_use_jquery_ui_slider'));
            Configuration::updateValue('PS_LAYERED_DEFAULT_CATEGORY_TEMPLATE', (int) Tools::getValue('ps_layered_default_category_template'));

            $this->psLayeredFullTree = (int) Tools::getValue('ps_layered_full_tree');

            $message = '<div class="alert alert-success">' . $this->trans('Settings saved successfully', [], 'Modules.Facetedsearch.Admin') . '</div>';
            $this->invalidateLayeredFilterBlockCache();
        } elseif (Tools::getValue('deleteFilterTemplate')) {
            $layered_values = $this->getDatabase()->getValue(
                'SELECT filters
                FROM ' . _DB_PREFIX_ . 'layered_filter
                WHERE id_layered_filter = ' . (int) Tools::getValue('id_layered_filter')
            );

            if ($layered_values) {
                $this->getDatabase()->execute(
                    'DELETE FROM ' . _DB_PREFIX_ . 'layered_filter
                    WHERE id_layered_filter = ' . (int) Tools::getValue('id_layered_filter') . ' LIMIT 1'
                );
                $this->buildLayeredCategories();
                $message = $this->displayConfirmation($this->trans('Filter template deleted, categories updated (reverted to default Filter template).', [], 'Modules.Facetedsearch.Admin'));
            } else {
                $message = $this->displayError($this->trans('Filter template not found', [], 'Modules.Facetedsearch.Admin'));
            }
        }

        // Assign general variables
        $this->context->smarty->assign('uri', $this->getPathUri());

        // Assign assets
        if (file_exists(_PS_ROOT_DIR_ . '/js/vendor/Sortable.min.js')) {
            $this->context->controller->addJS(_PS_JS_DIR_ . 'vendor/Sortable.min.js');
        } else {
            if (method_exists($this->context->controller, 'addJquery')) {
                $this->context->controller->addJS(_PS_JS_DIR_ . 'jquery/plugins/jquery.sortable.js');
            }
        }
        $this->context->controller->addJS($this->_path . 'views/dist/back.js');
        $this->context->controller->addCSS($this->_path . 'views/dist/back.css');

        // Render screen for adding new template
        if (Tools::getValue('add_new_filters_template')) {
            return $this->renderAdminTemplateEdit();
        }

        if (Tools::getValue('edit_filters_template')) {
            // Try to get template to edit from database
            $idLayeredFilter = (int) Tools::getValue('id_layered_filter');
            $template = $this->getFilterTemplate($idLayeredFilter);
            if (!empty($template)) {
                return $this->renderAdminTemplateEdit($template);
            } else {
                $message = $this->displayError($this->trans('Filter template not found', [], 'Modules.Facetedsearch.Admin'));
            }
        }

        $this->context->smarty->assign('message', $message);

        // Render general admin screen
        return $this->renderAdminMain();
    }

    /**
     * Returns content for main module configuration screen
     */
    public function renderAdminMain()
    {
        // General purpose variables
        $features = $this->getAvailableFeatures();
        $attributeGroups = $this->getAvailableAttributes();

        $cronToken = substr(Tools::hash('ps_facetedsearch/index'), 0, 10);
        $this->context->smarty->assign([
            'PS_LAYERED_INDEXED' => (int) Configuration::getGlobalValue('PS_LAYERED_INDEXED'),
            'current_url' => Tools::safeOutput(preg_replace('/&deleteFilterTemplate=[0-9]*&id_layered_filter=[0-9]*/', '', $_SERVER['REQUEST_URI'])),
            'id_lang' => $this->getContext()->cookie->id_lang,
            'token' => $cronToken,
            'base_folder' => urlencode(_PS_ADMIN_DIR_),
            'price_indexer_url' => $this->context->link->getModuleLink('ps_facetedsearch', 'cron', ['ajax' => true, 'action' => 'indexPrices', 'token' => $cronToken]),
            'full_price_indexer_url' => $this->context->link->getModuleLink('ps_facetedsearch', 'cron', ['ajax' => true, 'action' => 'indexPrices', 'full' => 1, 'token' => $cronToken]),
            'attribute_indexer_url' => $this->context->link->getModuleLink('ps_facetedsearch', 'cron', ['ajax' => true, 'action' => 'indexAttributes', 'token' => $cronToken]),
            'clear_cache_url' => $this->context->link->getModuleLink('ps_facetedsearch', 'cron', ['ajax' => true, 'action' => 'clearCache', 'token' => $cronToken]),
            'filters_templates' => $this->getExistingFiltersOverview(),
            'show_quantities' => Configuration::get('PS_LAYERED_SHOW_QTIES'),
            'cache_enabled' => Configuration::get('PS_LAYERED_CACHE_ENABLED'),
            'full_tree' => $this->psLayeredFullTree,
            'category_depth' => Configuration::get('PS_LAYERED_FILTER_CATEGORY_DEPTH'),
            'price_use_tax' => (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX'),
            'limit_warning' => $this->displayLimitPostWarning(21 + count($attributeGroups) * 3 + count($features) * 3),
            'price_use_rounding' => (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING'),
            'show_out_of_stock_last' => (bool) Configuration::get('PS_LAYERED_FILTER_SHOW_OUT_OF_STOCK_LAST'),
            'filter_by_default_category' => (bool) Configuration::get('PS_LAYERED_FILTER_BY_DEFAULT_CATEGORY'),
            'use_jquery_ui_slider' => (bool) Configuration::get('PS_USE_JQUERY_UI_SLIDER'),
            'default_category_template' => Configuration::get('PS_LAYERED_DEFAULT_CATEGORY_TEMPLATE'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/manage.tpl');
    }

    /**
     * Returns content for filter template creation or editing
     */
    public function renderAdminTemplateEdit($template = null)
    {
        // Get general data for use in template settings
        $features = $this->getAvailableFeatures();
        $attributeGroups = $this->getAvailableAttributes();

        // Get available controllers
        $controller_options = $this->getSupportedControllers();

        // Initialize category tree component
        $treeCategoriesHelper = new HelperTreeCategories('categories-treeview');
        $treeCategoriesHelper
            ->setRootCategory((Shop::getContext() == Shop::CONTEXT_SHOP ? Category::getRootCategory()->id_category : 0))
            ->setUseCheckBox(true);

        // If we are editing an already existing template, we will load its data,
        // check categories and add selected filters. Otherwise, we prepare empty template.
        if ($template !== null) {
            $filters = Tools::unSerialize($template['filters']);
            $id_layered_filter = $template['id_layered_filter'];
            $template_name = $template['name'];

            // Check categories
            $treeCategoriesHelper->setSelectedCategories($filters['categories']);

            // Check controllers assigned
            if (!empty($filters['controllers'])) {
                foreach ($filters['controllers'] as $controller) {
                    if (isset($controller_options[$controller])) {
                        $controller_options[$controller]['checked'] = true;
                    }
                }
            }

            // We need to clear all data except the filters themselves, due to JS processing them
            unset($filters['categories']);
            unset($filters['controllers']);
            unset($filters['shop_list']);
        } else {
            $id_layered_filter = 0;
            $filters = [];
            $template_name = sprintf($this->trans('My template - %s', [], 'Modules.Facetedsearch.Admin'), date('Y-m-d'));
        }

        // Assign multistore related data
        if (Shop::isFeatureActive() && count(Shop::getShops(true, null, true)) > 1) {
            $helper = new HelperForm();
            $helper->id = Tools::getValue('id_layered_filter', null);
            $helper->table = 'layered_filter';
            $helper->identifier = 'id_layered_filter';
            $this->context->smarty->assign('asso_shops', $helper->renderAssoShop());
        }

        $this->context->smarty->assign([
            'current_url' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]),
            'id_layered_filter' => $id_layered_filter,
            'template_name' => $template_name,
            'attribute_groups' => $attributeGroups,
            'features' => $features,
            'filters' => $filters,
            'total_filters' => 6 + count($attributeGroups) + count($features),
            'default_filters' => $this->getDefaultFilters(),
            'categories_tree' => $treeCategoriesHelper->render(),
            'controller_options' => $controller_options,
        ]);

        // We are using two separate templates depending on context
        if ($template !== null) {
            return $this->display(__FILE__, 'views/templates/admin/view.tpl');
        } else {
            return $this->display(__FILE__, 'views/templates/admin/add.tpl');
        }
    }

    public function displayLimitPostWarning($count)
    {
        $return = [];
        if ((ini_get('suhosin.post.max_vars') && ini_get('suhosin.post.max_vars') < $count) || (ini_get('suhosin.request.max_vars') && ini_get('suhosin.request.max_vars') < $count)) {
            $return['error_type'] = 'suhosin';
            $return['post.max_vars'] = ini_get('suhosin.post.max_vars');
            $return['request.max_vars'] = ini_get('suhosin.request.max_vars');
            $return['needed_limit'] = $count + 100;
        } elseif (ini_get('max_input_vars') && ini_get('max_input_vars') < $count) {
            $return['error_type'] = 'conf';
            $return['max_input_vars'] = ini_get('max_input_vars');
            $return['needed_limit'] = $count + 100;
        }

        return $return;
    }

    private function query($sqlQuery)
    {
        return $this->getDatabase()->query($sqlQuery);
    }

    /**
     * Returns array with all available attributes on the shop. Only used in backoffice.
     */
    private function getAvailableAttributes()
    {
        return $this->getDatabase()->executeS(
            'SELECT ag.id_attribute_group, ag.is_color_group, agl.name, COUNT(DISTINCT(a.id_attribute)) n
            FROM ' . _DB_PREFIX_ . 'attribute_group ag
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl ON (agl.id_attribute_group = ag.id_attribute_group)
            LEFT JOIN ' . _DB_PREFIX_ . 'attribute a ON (a.id_attribute_group = ag.id_attribute_group)
            WHERE agl.id_lang = ' . (int) $this->getContext()->cookie->id_lang . '
            GROUP BY ag.id_attribute_group'
        );
    }

    /**
     * Returns array with all available features on the shop. Only used in backoffice.
     */
    private function getAvailableFeatures()
    {
        return $this->getDatabase()->executeS(
            'SELECT fl.id_feature, fl.name, COUNT(DISTINCT(fv.id_feature_value)) n
            FROM ' . _DB_PREFIX_ . 'feature_lang fl
            LEFT JOIN ' . _DB_PREFIX_ . 'feature_value fv ON (fv.id_feature = fl.id_feature)
            WHERE (fv.custom IS NULL OR fv.custom = 0) AND fl.id_lang = ' . (int) $this->getContext()->cookie->id_lang . '
            GROUP BY fl.id_feature'
        );
    }

    /**
     * Returns array with existing filters set up in the module, for overview on a main page
     */
    private function getExistingFiltersOverview()
    {
        // Get data about current filters in database
        $filters_templates = $this->getDatabase()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'layered_filter ORDER BY date_add DESC');

        $supportedControllers = $this->getSupportedControllers();
        foreach ($filters_templates as $k => $v) {
            // Format controllers
            $filters_templates[$k]['controllers'] = '';

            // Let's get filter data
            $data = Tools::unSerialize($v['filters']);
            if (empty($data['controllers'])) {
                continue;
            }

            // Now we will loop through the controllers assigned to this template and if we
            // have a nice translation for this controller name, we will use it
            $tmp = [];
            foreach ($data['controllers'] as $c) {
                $tmp[] = (isset($supportedControllers[$c]) ? $supportedControllers[$c]['name'] : $c);
            }
            $filters_templates[$k]['controllers'] = implode(', ', $tmp);

            // Format date for different core versions. Since 8.0, it has only two arguments.
            if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                $filters_templates[$k]['date_add'] = Tools::displayDate($v['date_add'], true);
            } else {
                $filters_templates[$k]['date_add'] = Tools::displayDate($v['date_add'], null, true);
            }
        }

        return $filters_templates;
    }

    /**
     * Rebuild layered structure
     */
    public function rebuildLayeredStructure()
    {
        @set_time_limit(0);

        /* Set memory limit to 128M only if current is lower */
        $memoryLimit = Tools::getMemoryLimit();
        if ($memoryLimit != -1 && $memoryLimit < 128 * 1024 * 1024) {
            @ini_set('memory_limit', '128M');
        }

        /* Delete and re-create the layered categories table */
        $this->getDatabase()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'layered_category');

        $this->getDatabase()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'layered_category` (
            `id_layered_category` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            `controller` VARCHAR(64) NOT NULL,
            `id_category` INT(10) UNSIGNED NOT NULL,
            `id_value` INT(10) UNSIGNED NULL DEFAULT \'0\',
            `type` ENUM(\'category\',\'id_feature\',\'id_attribute_group\',\'availability\',\'condition\',\'manufacturer\',\'weight\',\'price\',\'extras\') NOT NULL,
            `position` INT(10) UNSIGNED NOT NULL,
            `filter_type` int(10) UNSIGNED NOT NULL DEFAULT 0,
            `filter_show_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
            KEY `id_category_shop` (`id_category`, `id_shop`, `type`, id_value, `position`),
            KEY `id_category` (`id_category`,`type`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        $this->getDatabase()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'layered_filter` (
            `id_layered_filter` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(64) NOT NULL,
            `filters` LONGTEXT NULL,
            `n_categories` INT(10) UNSIGNED NOT NULL,
            `date_add` DATETIME NOT NULL
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        $this->getDatabase()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'layered_filter_block` (
            `hash` CHAR(32) NOT NULL DEFAULT "" PRIMARY KEY,
            `data` TEXT NULL
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        $this->getDatabase()->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'layered_filter_shop` (
            `id_layered_filter` INT(10) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL,
            PRIMARY KEY (`id_layered_filter`, `id_shop`),
            KEY `id_shop` (`id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    /**
     * This method creates the first initial filter after installing the module,
     * from all available features and attributes.
     */
    public function createDefaultTemplate()
    {
        @set_time_limit(0);

        // Default filter data
        $filterData = [
            'categories' => [],
            'controllers' => [],
        ];

        // Add all stable controllers (except search)
        foreach ($this->getSupportedControllers() as $controller_name => $data) {
            if ($controller_name != 'search') {
                $filterData['controllers'][] = $controller_name;
            }
        }

        /* Set memory limit to 128M only if current is lower */
        $memoryLimit = Tools::getMemoryLimit();
        if ($memoryLimit != -1 && $memoryLimit < 128 * 1024 * 1024) {
            @ini_set('memory_limit', '128M');
        }

        $db = $this->getDatabase();
        $nCategories = [];
        $doneCategories = [];

        $alias = 'product_shop';
        $joinProduct = Shop::addSqlAssociation('product', 'p');
        $joinProductAttribute = Shop::addSqlAssociation('product_attribute', 'pa');

        // Fetch all available attributes and their values
        $attributeGroups = $this->query(
            'SELECT a.id_attribute, a.id_attribute_group
            FROM ' . _DB_PREFIX_ . 'attribute a
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac ON (pac.id_attribute = a.id_attribute)
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON (pa.id_product_attribute = pac.id_product_attribute)
            LEFT JOIN ' . _DB_PREFIX_ . 'product p ON (p.id_product = pa.id_product)
            ' . $joinProduct . $joinProductAttribute . '
            LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON (cp.id_product = p.id_product)
            LEFT JOIN ' . _DB_PREFIX_ . 'category c ON (c.id_category = cp.id_category)
            WHERE c.active = 1
            AND ' . $alias . '.active = 1 AND ' . $alias . '.`visibility` IN ("both", "catalog")'
        );

        $attributeGroupsById = [];
        while ($row = $db->nextRow($attributeGroups)) {
            $attributeGroupsById[(int) $row['id_attribute']] = (int) $row['id_attribute_group'];
        }

        // Fetch all available features and their values
        $features = $this->query(
            'SELECT fv.id_feature_value, fv.id_feature
            FROM ' . _DB_PREFIX_ . 'feature_value fv
            LEFT JOIN ' . _DB_PREFIX_ . 'feature_product fp ON (fp.id_feature_value = fv.id_feature_value)
            LEFT JOIN ' . _DB_PREFIX_ . 'product p ON (p.id_product = fp.id_product)
            ' . $joinProduct . '
            LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON (cp.id_product = p.id_product)
            LEFT JOIN ' . _DB_PREFIX_ . 'category c ON (c.id_category = cp.id_category)
            WHERE (fv.custom IS NULL OR fv.custom = 0) AND c.active = 1
            AND ' . $alias . '.active = 1 AND ' . $alias . '.`visibility` IN ("both", "catalog") '
        );

        $featuresById = [];
        while ($row = $db->nextRow($features)) {
            $featuresById[(int) $row['id_feature_value']] = (int) $row['id_feature'];
        }

        $result = $this->query(
            'SELECT p.id_product,
            GROUP_CONCAT(DISTINCT fv.id_feature_value) features,
            GROUP_CONCAT(DISTINCT cp.id_category) categories,
            GROUP_CONCAT(DISTINCT pac.id_attribute) attributes
            FROM ' . _DB_PREFIX_ . 'product p
            LEFT JOIN ' . _DB_PREFIX_ . 'category_product cp ON (cp.id_product = p.id_product)
            LEFT JOIN ' . _DB_PREFIX_ . 'category c ON (c.id_category = cp.id_category)
            LEFT JOIN ' . _DB_PREFIX_ . 'feature_product fp ON (fp.id_product = p.id_product)
            LEFT JOIN ' . _DB_PREFIX_ . 'feature_value fv ON (fv.id_feature_value = fp.id_feature_value)
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute pa ON (pa.id_product = p.id_product)
            ' . $joinProduct . $joinProductAttribute . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
            WHERE c.active = 1
            AND ' . $alias . '.active = 1 AND ' . $alias . '.`visibility` IN ("both", "catalog")
            AND (fv.custom IS NULL OR fv.custom = 0)
            GROUP BY p.id_product'
        );

        $shopList = Shop::getShops(false, null, true);

        $toInsert = false;
        while ($product = $db->nextRow($result)) {
            $a = $c = $f = [];
            if (!empty($product['attributes'])) {
                $a = array_flip(explode(',', $product['attributes']));
            }

            if (!empty($product['categories'])) {
                $c = array_flip(explode(',', $product['categories']));
            }

            if (!empty($product['features'])) {
                $f = array_flip(explode(',', $product['features']));
            }

            $filterData['shop_list'] = $shopList;

            foreach ($c as $idCategory => $category) {
                if (!in_array($idCategory, $filterData['categories'])) {
                    $filterData['categories'][] = $idCategory;
                }

                if (!isset($nCategories[(int) $idCategory])) {
                    $nCategories[(int) $idCategory] = 1;
                }

                // Stock filter
                if (!isset($doneCategories[(int) $idCategory]['q'])) {
                    $filterData['layered_selection_stock'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['q'] = true;
                    $toInsert = true;
                }

                // Add extras filter
                if (!isset($doneCategories[(int) $idCategory]['e'])) {
                    $filterData['layered_selection_extras'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['e'] = true;
                    $toInsert = true;
                }

                // Price filter
                if (!isset($doneCategories[(int) $idCategory]['p'])) {
                    $filterData['layered_selection_price_slider'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['p'] = true;
                    $toInsert = true;
                }

                // Category filter
                if (!isset($doneCategories[(int) $idCategory]['cat'])) {
                    $filterData['layered_selection_subcategories'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['cat'] = true;
                    $toInsert = true;
                }

                // Attribute filter
                if (is_array($attributeGroupsById) && count($attributeGroupsById) > 0) {
                    foreach ($a as $kAttribute => $attribute) {
                        if (!isset($doneCategories[(int) $idCategory]['a' . (int) $attributeGroupsById[(int) $kAttribute]])) {
                            $filterData['layered_selection_ag_' . (int) $attributeGroupsById[(int) $kAttribute]] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                            $doneCategories[(int) $idCategory]['a' . (int) $attributeGroupsById[(int) $kAttribute]] = true;
                            $toInsert = true;
                        }
                    }
                }

                // Features filter
                if (is_array($featuresById) && count($featuresById) > 0) {
                    foreach ($f as $kFeature => $feature) {
                        if (!isset($doneCategories[(int) $idCategory]['f' . (int) $featuresById[(int) $kFeature]])) {
                            $filterData['layered_selection_feat_' . (int) $featuresById[(int) $kFeature]] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                            $doneCategories[(int) $idCategory]['f' . (int) $featuresById[(int) $kFeature]] = true;
                            $toInsert = true;
                        }
                    }
                }

                // Manufacturer filter
                if (!isset($doneCategories[(int) $idCategory]['m'])) {
                    $filterData['layered_selection_manufacturer'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['m'] = true;
                    $toInsert = true;
                }

                // Condition filter
                if (!isset($doneCategories[(int) $idCategory]['c'])) {
                    $filterData['layered_selection_condition'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['c'] = true;
                    $toInsert = true;
                }

                // Weight filter
                if (!isset($doneCategories[(int) $idCategory]['w'])) {
                    $filterData['layered_selection_weight_slider'] = ['filter_type' => Converter::WIDGET_TYPE_CHECKBOX, 'filter_show_limit' => 0];
                    $doneCategories[(int) $idCategory]['w'] = true;
                    $toInsert = true;
                }
            }
        }

        // If there are any filters available to setup, we will create the filter template
        if ($toInsert) {
            $this->getDatabase()->execute('INSERT INTO ' . _DB_PREFIX_ . 'layered_filter(name, filters, n_categories, date_add)
VALUES (\'' . sprintf($this->trans('My template %s', [], 'Modules.Facetedsearch.Admin'), date('Y-m-d')) . '\', \'' . pSQL(serialize($filterData)) . '\', ' . count($filterData['categories']) . ', NOW())');

            $last_id = $this->getDatabase()->Insert_ID();
            $this->getDatabase()->execute('DELETE FROM ' . _DB_PREFIX_ . 'layered_filter_shop WHERE `id_layered_filter` = ' . $last_id);
            foreach ($shopList as $idShop) {
                $this->getDatabase()->execute('INSERT INTO ' . _DB_PREFIX_ . 'layered_filter_shop (`id_layered_filter`, `id_shop`)
VALUES(' . $last_id . ', ' . (int) $idShop . ')');
            }
        }

        // Now we need to build layered_category table from this template
        $this->buildLayeredCategories();
    }

    /**
     * This method gets serialized data of filter templates from layered_filter table and builds detailed
     * information, one category = one line.
     */
    public function buildLayeredCategories()
    {
        // Get data for all filter templates in the database
        $templates = $this->getDatabase()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'layered_filter ORDER BY date_add DESC');

        // We will keep track of pages categories where filter was already set, so we don't have multiple
        // filters for the same category and shop.
        $alreadyAssigned = [];

        // Clear cache
        $this->invalidateLayeredFilterBlockCache();

        // Remove all previous data from layered_category
        $this->getDatabase()->execute('TRUNCATE ' . _DB_PREFIX_ . 'layered_category');

        // If no filter templates are defined, nothing else to do here
        if (!count($templates)) {
            return true;
        }

        // We will insert our queries by batches of hundred queries
        $sqlInsertPrefix = 'INSERT INTO ' . _DB_PREFIX_ . 'layered_category (id_category, controller, id_shop, id_value, type, position, filter_show_limit, filter_type) VALUES ';
        $sqlInsert = '';
        $nbSqlValuesToInsert = 0;

        // Now we will loop through each filter template
        foreach ($templates as $filterTemplate) {
            // We will get it's data and convert it into array
            $data = Tools::unSerialize($filterTemplate['filters']);
            foreach ($data['shop_list'] as $idShop) {
                if (!isset($alreadyAssigned[$idShop])) {
                    $alreadyAssigned[$idShop] = [];
                }

                // Now let's generate data for each controller in the template
                foreach ($data['controllers'] as $controller) {
                    // If it's a category controller, we will do it for each category
                    // Otherwise, we will use just one line with zero
                    $categories = ($controller == 'category' ? $data['categories'] : [0]);

                    foreach ($categories as $idCategory) {
                        $n = 0;

                        // Make unique job name and check if already generated something for this scenario
                        // If yes, skip it, otherwise note this info for next time
                        $jobName = $controller . '-' . $idCategory;
                        if (in_array($jobName, $alreadyAssigned[$idShop])) {
                            continue;
                        }
                        $alreadyAssigned[$idShop][] = $jobName;

                        foreach ($data as $key => $value) {
                            // The template contains some other data than filters, so we clean it up a bit
                            // All filters begin with layered_selection
                            if (substr($key, 0, 17) != 'layered_selection') {
                                continue;
                            }

                            $type = $value['filter_type'];
                            $limit = $value['filter_show_limit'];
                            ++$n;

                            if ($key == 'layered_selection_stock') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'availability\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_subcategories') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'category\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_condition') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'condition\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_weight_slider') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'weight\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_price_slider') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'price\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_manufacturer') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'manufacturer\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif (substr($key, 0, 21) == 'layered_selection_ag_') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', ' . (int) str_replace('layered_selection_ag_', '', $key) . ',
    \'id_attribute_group\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif (substr($key, 0, 23) == 'layered_selection_feat_') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', ' . (int) str_replace('layered_selection_feat_', '', $key) . ',
    \'id_feature\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            } elseif ($key == 'layered_selection_extras') {
                                $sqlInsert .= '(' . (int) $idCategory . ', \'' . $controller . '\', ' . (int) $idShop . ', NULL,\'extras\',' . (int) $n . ', ' . (int) $limit . ', ' . (int) $type . '),';
                            }

                            ++$nbSqlValuesToInsert;

                            // If we reached the limit, we will execute it and flush our "cache"
                            if ($nbSqlValuesToInsert >= 100) {
                                $this->getDatabase()->execute($sqlInsertPrefix . rtrim($sqlInsert, ','));
                                $sqlInsert = '';
                                $nbSqlValuesToInsert = 0;
                            }
                        }
                    }
                }
            }
        }

        // We will execute remaining queries because we almost certainly didn't reach 100 in the batch
        if ($nbSqlValuesToInsert) {
            $this->getDatabase()->execute($sqlInsertPrefix . rtrim($sqlInsert, ','));
        }
    }

    /**
     * Render template
     *
     * @param string $template
     * @param array $params
     *
     * @return string
     */
    public function render($template, array $params = [])
    {
        $this->context->smarty->assign($params);

        return $this->display(__FILE__, $template);
    }

    /**
     * Check if link rewrite are availables and corrects
     *
     * @param array $params
     */
    public function checkLinksRewrite($params)
    {
        foreach (Language::getLanguages(false) as $language) {
            $idLang = $language['id_lang'];
            $urlNameLang = Tools::getValue('url_name_' . $idLang);
            if ($urlNameLang && Tools::str2url($urlNameLang) != strtolower($urlNameLang)) {
                $params['errors'][] = $this->trans(
                    '"%s" is not a valid url',
                    [Tools::safeOutput($urlNameLang, true)],
                    'Modules.Facetedsearch.Admin'
                );
            }
        }
    }

    /**
     * Dispatch hooks
     *
     * @param string $methodName
     * @param array $arguments
     */
    public function __call($methodName, array $arguments)
    {
        return $this->getHookDispatcher()->dispatch(
            $methodName,
            !empty($arguments[0]) ? $arguments[0] : []
        );
    }

    /**
     * Invalid filter block cache
     */
    public function invalidateLayeredFilterBlockCache()
    {
        return $this->getDatabase()->execute('TRUNCATE TABLE ' . _DB_PREFIX_ . 'layered_filter_block');
    }

    /**
     * Install price indexes table
     */
    public function rebuildPriceIndexTable()
    {
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_price_index`');

        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_price_index` (
            `id_product` INT  NOT NULL,
            `id_currency` INT NOT NULL,
            `id_shop` INT NOT NULL,
            `price_min` DECIMAL(20, 6) NOT NULL,
            `price_max` DECIMAL(20, 6) NOT NULL,
            `id_country` INT NOT NULL,
            PRIMARY KEY (`id_product`, `id_currency`, `id_shop`, `id_country`),
            INDEX `id_currency` (`id_currency`),
            INDEX `price_min` (`price_min`),
            INDEX `price_max` (`price_max`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    /**
     * create table product attribute.
     */
    private function installProductAttributeTable()
    {
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_product_attribute`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_product_attribute` (
            `id_attribute` int(10) unsigned NOT NULL,
            `id_product` int(10) unsigned NOT NULL,
            `id_attribute_group` int(10) unsigned NOT NULL DEFAULT "0",
            `id_shop` int(10) unsigned NOT NULL DEFAULT "1",
            PRIMARY KEY (`id_attribute`, `id_product`, `id_shop`),
            UNIQUE KEY `id_attribute_group` (`id_attribute_group`,`id_attribute`,`id_product`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    /**
     * Install indexable attribute table
     */
    private function installIndexableAttributeTable()
    {
        // Attributes Groups
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_attribute_group`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_attribute_group` (
            `id_attribute_group` INT NOT NULL,
            `indexable` BOOL NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_attribute_group`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
        $this->getDatabase()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'layered_indexable_attribute_group` (id_attribute_group)
            SELECT id_attribute_group FROM `' . _DB_PREFIX_ . 'attribute_group`'
        );

        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value` (
            `id_attribute_group` INT NOT NULL,
            `id_lang` INT NOT NULL,
            `url_name` VARCHAR(128),
            `meta_title` VARCHAR(128),
            PRIMARY KEY (`id_attribute_group`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        // Attributes
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value` (
            `id_attribute` INT NOT NULL,
            `id_lang` INT NOT NULL,
            `url_name` VARCHAR(128),
            `meta_title` VARCHAR(128),
            PRIMARY KEY (`id_attribute`, `id_lang`)
           )  ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        // Features
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_feature`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_feature` (
            `id_feature` INT NOT NULL,
            `indexable` BOOL NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_feature`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        $this->getDatabase()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'layered_indexable_feature` (id_feature)
            SELECT id_feature FROM `' . _DB_PREFIX_ . 'feature`'
        );

        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value` (
            `id_feature` INT NOT NULL,
            `id_lang` INT NOT NULL,
            `url_name` VARCHAR(128) NOT NULL,
            `meta_title` VARCHAR(128),
            PRIMARY KEY (`id_feature`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );

        // Features values
        $this->getDatabase()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value`');
        $this->getDatabase()->execute(
            'CREATE TABLE `' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value` (
            `id_feature_value` INT NOT NULL,
            `id_lang` INT NOT NULL,
            `url_name` VARCHAR(128),
            `meta_title` VARCHAR(128),
            PRIMARY KEY (`id_feature_value`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;'
        );
    }

    /**
     * Index prices
     *
     * @param int $cursor last indexed id_product
     * @param bool $full
     * @param bool $ajax
     * @param bool $smart
     *
     * @return int|string|bool
     */
    private function indexPrices($cursor = 0, $full = false, $ajax = false, $smart = false)
    {
        if ($full) {
            $nbProducts = (int) $this->getDatabase()->getValue(
                'SELECT count(DISTINCT p.`id_product`) ' .
                'FROM ' . _DB_PREFIX_ . 'product p ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ' .
                'ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))'
            );
        } else {
            $nbProducts = (int) $this->getDatabase()->getValue(
                'SELECT COUNT(DISTINCT p.`id_product`) ' .
                'FROM `' . _DB_PREFIX_ . 'product` p ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog")) ' .
                'LEFT JOIN  `' . _DB_PREFIX_ . 'layered_price_index` psi ON (psi.id_product = p.id_product) ' .
                'WHERE psi.id_product IS NULL'
            );
        }

        $maxExecutiontime = @ini_get('max_execution_time');
        if ($maxExecutiontime > 5 || $maxExecutiontime <= 0) {
            $maxExecutiontime = 5;
        }

        $startTime = microtime(true);

        $indexedProducts = 0;
        $length = 100;
        do {
            $lastCursor = $cursor;
            $cursor = (int) $this->indexPricesUnbreakable((int) $cursor, $full, $smart, $length);
            if ($cursor == 0) {
                $lastCursor = $cursor;
                break;
            }
            $time_elapsed = microtime(true) - $startTime;
            $indexedProducts += $length;
        } while (
            $cursor < $nbProducts
            && (Tools::getMemoryLimit() == -1 || Tools::getMemoryLimit() > memory_get_peak_usage())
            && $time_elapsed < $maxExecutiontime
        );

        if (($nbProducts > 0 && !$full || $cursor != $lastCursor && $full) && !$ajax) {
            return $this->indexPrices((int) $cursor, $full, $ajax, $smart);
        }

        if ($ajax && $nbProducts > 0 && $cursor != $lastCursor && $full) {
            return json_encode([
                'total' => $nbProducts,
                'cursor' => $cursor,
                'count' => $indexedProducts,
            ]);
        }

        if ($ajax && $nbProducts > 0 && !$full) {
            return json_encode([
                'total' => $nbProducts,
                'cursor' => $cursor,
                'count' => $indexedProducts,
            ]);
        }

        Configuration::updateGlobalValue('PS_LAYERED_INDEXED', 1);

        if ($ajax) {
            return json_encode([
                'result' => 'ok',
            ]);
        }

        return $nbProducts;
    }

    /**
     * Index prices unbreakable
     *
     * @param int $cursor last indexed id_product
     * @param bool $full All products, otherwise only indexed products
     * @param bool $smart Delete before reindex
     * @param int $length nb of products to index
     *
     * @return int
     */
    private function indexPricesUnbreakable($cursor, $full = false, $smart = false, $length = 100)
    {
        if ($full) {
            $query = 'SELECT p.`id_product` ' .
                'FROM `' . _DB_PREFIX_ . 'product` p ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ' .
                'ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog")) ' .
                'WHERE p.id_product > ' . (int) $cursor . ' ' .
                'GROUP BY p.`id_product` ' .
                'ORDER BY p.`id_product` LIMIT 0,' . (int) $length;
        } else {
            $query = 'SELECT p.`id_product` ' .
                'FROM `' . _DB_PREFIX_ . 'product` p ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ' .
                'ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog")) ' .
                'LEFT JOIN  `' . _DB_PREFIX_ . 'layered_price_index` psi ON (psi.id_product = p.id_product) ' .
                'WHERE psi.id_product IS NULL ' .
                'GROUP BY p.`id_product` ' .
                'ORDER BY p.`id_product` LIMIT 0,' . (int) $length;
        }

        $lastIdProduct = 0;
        foreach ($this->getDatabase()->executeS($query) as $product) {
            $this->indexProductPrices((int) $product['id_product'], ($smart && $full));
            $lastIdProduct = $product['id_product'];
        }

        return (int) $lastIdProduct;
    }

    /**
     * {@inheritdoc}
     */
    public function renderWidget($hookName, array $configuration)
    {
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch(
            'module:ps_facetedsearch/ps_facetedsearch.tpl'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getWidgetVariables($hookName, array $configuration)
    {
        return [];
    }

    /**
     * Provides data about single filter template.
     *
     * @param int $idFilterTemplate ID of filter template
     *
     * @return array Filter data
     */
    public function getFilterTemplate($idFilterTemplate)
    {
        return $this->getDatabase()->getRow(
            'SELECT *
            FROM `' . _DB_PREFIX_ . 'layered_filter`
            WHERE id_layered_filter = ' . (int) $idFilterTemplate
        );
    }

    /**
     * Returns array with all controllers supported by this module
     */
    public function getSupportedControllers()
    {
        return $this->supportedControllers;
    }

    public function setSupportedControllers($supportedControllers)
    {
        $this->supportedControllers = $supportedControllers;
    }

    /**
     * Returns array with all controllers supported by this module
     */
    public function isControllerSupported($controller)
    {
        return isset($this->supportedControllers[$controller]);
    }

    /**
     * Should this controller filter blocks be cached?
     */
    public function shouldCacheController(string $controller)
    {
        return $this->supportedControllers[$controller]['cacheable'];
    }

    public function initializeSupportedControllers()
    {
        $this->setSupportedControllers([
            'category' => [
                'name' => $this->trans('Category', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => true,
            ],
            'manufacturer' => [
                'name' => $this->trans('Manufacturer', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => true,
            ],
            'supplier' => [
                'name' => $this->trans('Supplier', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => true,
            ],
            'new-products' => [
                'name' => $this->trans('New products', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => false,
            ],
            'best-sales' => [
                'name' => $this->trans('Best sales', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => false,
            ],
            'prices-drop' => [
                'name' => $this->trans('Prices drop', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => false,
            ],
            'search' => [
                'name' => $this->trans('Search', [], 'Modules.Facetedsearch.Admin') . ' ' . $this->trans('(experimental)', [], 'Modules.Facetedsearch.Admin'),
                'cacheable' => false,
            ],
        ]);
    }
}

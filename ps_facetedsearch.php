<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use \PrestaShop\PrestaShop\Core\Module\WidgetInterface;

require_once implode(DIRECTORY_SEPARATOR, array(
    __DIR__, 'src', 'Ps_FacetedsearchProductSearchProvider.php',
));

require_once implode(DIRECTORY_SEPARATOR, array(
    __DIR__, 'src', 'Ps_FacetedsearchRangeAggregator.php',
));

class Ps_Facetedsearch extends Module implements WidgetInterface
{
    private $nbr_products;
    private $ps_layered_full_tree;

    public function __construct()
    {
        $this->name = 'ps_facetedsearch';
        $this->tab = 'front_office_features';
        $this->version = '2.1.2';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Faceted search', array(), 'Modules.Facetedsearch.Admin');
        $this->description = $this->trans('Displays a block allowing multiple filters.', array(), 'Modules.Facetedsearch.Admin');
        $this->ps_layered_full_tree = Configuration::get('PS_LAYERED_FULL_TREE');
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $installed = parent::install() && $this->registerHook(array(
                'categoryAddition',
                'categoryUpdate',
                'attributeGroupForm',
                'afterSaveAttributeGroup',
                'afterDeleteAttributeGroup',
                'featureForm',
                'afterDeleteFeature',
                'afterSaveFeature',
                'categoryDeletion',
                'afterSaveProduct',
                'postProcessAttributeGroup',
                'postProcessFeature',
                'featureValueForm',
                'postProcessFeatureValue',
                'afterDeleteFeatureValue',
                'afterSaveFeatureValue',
                'attributeForm',
                'postProcessAttribute',
                'afterDeleteAttribute',
                'afterSaveAttribute',
                'productSearchProvider',
                'displayLeftColumn',

            ));

        if ($installed) {
            Configuration::updateValue('PS_LAYERED_SHOW_QTIES', 1);
            Configuration::updateValue('PS_LAYERED_FULL_TREE', 1);
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_USETAX', 1);
            Configuration::updateValue('PS_LAYERED_FILTER_CATEGORY_DEPTH', 1);
            Configuration::updateValue('PS_ATTRIBUTE_ANCHOR_SEPARATOR', '-');
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_ROUNDING', 1);

            $this->ps_layered_full_tree = 1;

            $this->rebuildLayeredStructure();
            $this->buildLayeredCategories();

            $products_count = Db::getInstance()->getValue('SELECT COUNT(*) FROM `'._DB_PREFIX_.'product`');

            if ($products_count < 20000) { // Lock template filter creation if too many products
                // build the cache by pack of 100 categories to avoid storing too many infos in an individual row in
                // layered_filter table
                $categories = Category::getCategories(false, true, false);

                // get all id_category
                $categoryIds = array_column($categories, 'id_category');

                // group by 100 ids
                $chunks = array_chunk($categoryIds, 100);

                // rebuild layered cache for each chunk
                foreach ($chunks as $chunk) {
                    $this->rebuildLayeredCache(array(), $chunk, false);
                }

                $this->buildLayeredCategories();
            }

            self::installPriceIndexTable();
            $this->installIndexableAttributeTable();
            $this->installProductAttributeTable();

            if ($products_count < 5000) {
                // Lock indexation if too many products

                self::fullPricesIndexProcess();
                $this->indexAttribute();
            }

            return true;
        } else {
            // Installation failed (or hook registration) => uninstall the module
            $this->uninstall();

            return false;
        }
    }

    public function hookProductSearchProvider($params)
    {
        $query = $params['query'];
        // do something with query,
        // e.g. use $query->getIdCategory()
        // to choose a template for filters.
        // Query is an instance of:
        // PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery
        if ($query->getIdCategory()) {
            return new Ps_FacetedsearchProductSearchProvider($this);
        } else {
            return null;
        }
    }

    public function uninstall()
    {
        /* Delete all configurations */
        Configuration::deleteByName('PS_LAYERED_SHOW_QTIES');
        Configuration::deleteByName('PS_LAYERED_FULL_TREE');
        Configuration::deleteByName('PS_LAYERED_INDEXED');
        Configuration::deleteByName('PS_LAYERED_FILTER_PRICE_USETAX');
        Configuration::deleteByName('PS_LAYERED_FILTER_CATEGORY_DEPTH');
        Configuration::deleteByName('PS_LAYERED_FILTER_PRICE_ROUNDING');

        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_price_index');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_attribute_group');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_feature');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_attribute_lang_value');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_attribute_group_lang_value');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_feature_lang_value');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_indexable_feature_value_lang_value');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_category');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_filter');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_filter_shop');
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_product_attribute');

        return parent::uninstall();
    }

    private static function installPriceIndexTable()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_price_index`');

        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_price_index` (
			`id_product` INT  NOT NULL,
			`id_currency` INT NOT NULL,
			`id_shop` INT NOT NULL,
			`price_min` INT NOT NULL,
			`price_max` INT NOT NULL,
		PRIMARY KEY (`id_product`, `id_currency`, `id_shop`),
		INDEX `id_currency` (`id_currency`),
		INDEX `price_min` (`price_min`), INDEX `price_max` (`price_max`)
		)  ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
    }

    private function installIndexableAttributeTable()
    {
        // Attributes Groups
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_attribute_group`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_attribute_group` (
		`id_attribute_group` INT NOT NULL,
		`indexable` BOOL NOT NULL DEFAULT 0,
		PRIMARY KEY (`id_attribute_group`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
        Db::getInstance()->execute('
		INSERT INTO `'._DB_PREFIX_.'layered_indexable_attribute_group`
		SELECT id_attribute_group, 1 FROM `'._DB_PREFIX_.'attribute_group`');

        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_attribute_group_lang_value`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_attribute_group_lang_value` (
		`id_attribute_group` INT NOT NULL,
		`id_lang` INT NOT NULL,
		`url_name` VARCHAR(128),
		`meta_title` VARCHAR(128),
		PRIMARY KEY (`id_attribute_group`, `id_lang`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');

        // Attributes
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_attribute_lang_value`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_attribute_lang_value` (
		`id_attribute` INT NOT NULL,
		`id_lang` INT NOT NULL,
		`url_name` VARCHAR(128),
		`meta_title` VARCHAR(128),
		PRIMARY KEY (`id_attribute`, `id_lang`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');

        // Features
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_feature`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_feature` (
		`id_feature` INT NOT NULL,
		`indexable` BOOL NOT NULL DEFAULT 0,
		PRIMARY KEY (`id_feature`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('
		INSERT INTO `'._DB_PREFIX_.'layered_indexable_feature`
		SELECT id_feature, 1 FROM `'._DB_PREFIX_.'feature`');

        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_feature_lang_value`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_feature_lang_value` (
		`id_feature` INT NOT NULL,
		`id_lang` INT NOT NULL,
		`url_name` VARCHAR(128) NOT NULL,
		`meta_title` VARCHAR(128),
		PRIMARY KEY (`id_feature`, `id_lang`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');

        // Features values
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_indexable_feature_value_lang_value`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_indexable_feature_value_lang_value` (
		`id_feature_value` INT NOT NULL,
		`id_lang` INT NOT NULL,
		`url_name` VARCHAR(128),
		`meta_title` VARCHAR(128),
		PRIMARY KEY (`id_feature_value`, `id_lang`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
    }

    /**
     * create table product attribute.
     */
    public function installProductAttributeTable()
    {
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'layered_product_attribute`');
        Db::getInstance()->execute('
		CREATE TABLE `'._DB_PREFIX_.'layered_product_attribute` (
		`id_attribute` int(10) unsigned NOT NULL,
		`id_product` int(10) unsigned NOT NULL,
		`id_attribute_group` int(10) unsigned NOT NULL DEFAULT "0",
		`id_shop` int(10) unsigned NOT NULL DEFAULT "1",
		PRIMARY KEY (`id_attribute`, `id_product`, `id_shop`),
		UNIQUE KEY `id_attribute_group` (`id_attribute_group`,`id_attribute`,`id_product`, `id_shop`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
    }

    //ATTRIBUTES GROUP
    public function hookAfterSaveAttributeGroup($params)
    {
        if (!$params['id_attribute_group'] || Tools::getValue('layered_indexable') === false) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_group
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        );
        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_group_lang_value
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        );

        Db::getInstance()->execute(
            'INSERT INTO '._DB_PREFIX_.'layered_indexable_attribute_group (`id_attribute_group`, `indexable`)
			VALUES ('.(int) $params['id_attribute_group'].', '.(int) Tools::getValue('layered_indexable').')'
        );

        foreach (Language::getLanguages(false) as $language) {
            $seo_url = Tools::getValue('url_name_'.(int) $language['id_lang']);

            if (empty($seo_url)) {
                $seo_url = Tools::getValue('name_'.(int) $language['id_lang']);
            }

            Db::getInstance()->execute(
                'INSERT INTO '._DB_PREFIX_.'layered_indexable_attribute_group_lang_value
				(`id_attribute_group`, `id_lang`, `url_name`, `meta_title`)
				VALUES (
					'.(int) $params['id_attribute_group'].', '.(int) $language['id_lang'].',
					\''.pSQL(Tools::link_rewrite($seo_url)).'\',
					\''.pSQL(Tools::getValue('meta_title_'.(int) $language['id_lang']), true).'\'
				)'
            );
        }
    }

    public function hookAfterDeleteAttributeGroup($params)
    {
        if (!$params['id_attribute_group']) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_group
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        );
        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_group_lang_value
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        );
    }

    public function hookPostProcessAttributeGroup($params)
    {
        foreach (Language::getLanguages(false) as $language) {
            $id_lang = $language['id_lang'];

            if (Tools::getValue('url_name_'.$id_lang)) {
                if (Tools::link_rewrite(Tools::getValue('url_name_'.$id_lang)) != strtolower(Tools::getValue('url_name_'.$id_lang))) {
                    $params['errors'][] = Tools::displayError($this->trans('"%s" is not a valid url', array(Tools::getValue('url_name_'.$id_lang)), 'Modules.Facetedsearch.Admin'));
                }
            }
        }
    }

    public function hookAttributeGroupForm($params)
    {
        $values = array();
        $is_indexable = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `indexable`
			FROM '._DB_PREFIX_.'layered_indexable_attribute_group
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        );

        if ($is_indexable === false) {
            $is_indexable = true;
        }

        if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang` FROM '._DB_PREFIX_.'layered_indexable_attribute_group_lang_value
			WHERE `id_attribute_group` = '.(int) $params['id_attribute_group']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = array('url_name' => $data['url_name'], 'meta_title' => $data['meta_title']);
            }
        }

        $this->context->smarty->assign(array(
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
            'is_indexable' => (bool) $is_indexable,
        ));

        return $this->display(__FILE__, 'attribute_group_form.tpl');
    }

    //ATTRIBUTES
    public function hookAfterSaveAttribute($params)
    {
        if (!$params['id_attribute']) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_lang_value
			WHERE `id_attribute` = '.(int) $params['id_attribute']
        );

        foreach (Language::getLanguages(false) as $language) {
            $seo_url = Tools::getValue('url_name_'.(int) $language['id_lang']);

            if (empty($seo_url)) {
                $seo_url = Tools::getValue('name_'.(int) $language['id_lang']);
            }

            Db::getInstance()->execute(
                'INSERT INTO '._DB_PREFIX_.'layered_indexable_attribute_lang_value
				(`id_attribute`, `id_lang`, `url_name`, `meta_title`)
				VALUES (
					'.(int) $params['id_attribute'].', '.(int) $language['id_lang'].',
					\''.pSQL(Tools::link_rewrite($seo_url)).'\',
					\''.pSQL(Tools::getValue('meta_title_'.(int) $language['id_lang']), true).'\'
				)'
            );
        }
    }

    public function hookAfterDeleteAttribute($params)
    {
        if (!$params['id_attribute']) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_attribute_lang_value
			WHERE `id_attribute` = '.(int) $params['id_attribute']
        );
    }

    public function hookPostProcessAttribute($params)
    {
        foreach (Language::getLanguages(false) as $language) {
            $id_lang = $language['id_lang'];

            if (Tools::getValue('url_name_'.$id_lang)) {
                if (Tools::link_rewrite(Tools::getValue('url_name_'.$id_lang)) != strtolower(Tools::getValue('url_name_'.$id_lang))) {
                    $params['errors'][] = Tools::displayError($this->trans('"%s" is not a valid url', array(Tools::getValue('url_name_'.$id_lang)), 'Modules.Facetedsearch.Admin'));
                }
            }
        }
    }

    public function hookAttributeForm($params)
    {
        $values = array();

        if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang`
			FROM '._DB_PREFIX_.'layered_indexable_attribute_lang_value
			WHERE `id_attribute` = '.(int) $params['id_attribute']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = array('url_name' => $data['url_name'], 'meta_title' => $data['meta_title']);
            }
        }

        $this->context->smarty->assign(array(
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
        ));

        return $this->display(__FILE__, 'attribute_form.tpl');
    }

    //FEATURES
    public function hookAfterSaveFeature($params)
    {
        if (!$params['id_feature'] || Tools::getValue('layered_indexable') === false) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_feature
			WHERE `id_feature` = '.(int) $params['id_feature']
        );
        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_feature_lang_value
			WHERE `id_feature` = '.(int) $params['id_feature']
        );

        Db::getInstance()->execute(
            'INSERT INTO '._DB_PREFIX_.'layered_indexable_feature
			(`id_feature`, `indexable`)
			VALUES ('.(int) $params['id_feature'].', '.(int) Tools::getValue('layered_indexable').')'
        );

        foreach (Language::getLanguages(false) as $language) {
            $seo_url = Tools::getValue('url_name_'.(int) $language['id_lang']);

            if (empty($seo_url)) {
                $seo_url = Tools::getValue('name_'.(int) $language['id_lang']);
            }

            Db::getInstance()->execute(
                'INSERT INTO '._DB_PREFIX_.'layered_indexable_feature_lang_value
				(`id_feature`, `id_lang`, `url_name`, `meta_title`)
				VALUES (
					'.(int) $params['id_feature'].', '.(int) $language['id_lang'].',
					\''.pSQL(Tools::link_rewrite($seo_url)).'\',
					\''.pSQL(Tools::getValue('meta_title_'.(int) $language['id_lang']), true).'\'
				)'
            );
        }
    }

    public function hookAfterDeleteFeature($params)
    {
        if (!$params['id_feature']) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_feature
			WHERE `id_feature` = '.(int) $params['id_feature']
        );
    }

    public function hookPostProcessFeature($params)
    {
        foreach (Language::getLanguages(false) as $language) {
            $id_lang = $language['id_lang'];

            if (Tools::getValue('url_name_'.$id_lang)) {
                if (Tools::link_rewrite(Tools::getValue('url_name_'.$id_lang)) != strtolower(Tools::getValue('url_name_'.$id_lang))) {
                    $params['errors'][] = Tools::displayError($this->trans('"%s" is not a valid url', array(Tools::getValue('url_name_'.$id_lang)), 'Modules.Facetedsearch.Admin'));
                }
            }
        }
    }

    public function hookFeatureForm($params)
    {
        $values = array();
        $is_indexable = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT `indexable`
			FROM '._DB_PREFIX_.'layered_indexable_feature
			WHERE `id_feature` = '.(int) $params['id_feature']
        );

        if ($is_indexable === false) {
            $is_indexable = true;
        }

        if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang` FROM '._DB_PREFIX_.'layered_indexable_feature_lang_value
			WHERE `id_feature` = '.(int) $params['id_feature']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = array('url_name' => $data['url_name'], 'meta_title' => $data['meta_title']);
            }
        }

        $this->context->smarty->assign(array(
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
            'is_indexable' => (bool) $is_indexable,
        ));

        return $this->display(__FILE__, 'feature_form.tpl');
    }

    //FEATURES VALUE
    public function hookAfterSaveFeatureValue($params)
    {
        if (!$params['id_feature_value']) {
            return;
        }

        //Removing all indexed language data for this attribute value id
        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_feature_value_lang_value
			WHERE `id_feature_value` = '.(int) $params['id_feature_value']
        );

        foreach (Language::getLanguages(false) as $language) {
            $seo_url = Tools::getValue('url_name_'.(int) $language['id_lang']);

            if (empty($seo_url)) {
                $seo_url = Tools::getValue('name_'.(int) $language['id_lang']);
            }

            Db::getInstance()->execute(
                'INSERT INTO '._DB_PREFIX_.'layered_indexable_feature_value_lang_value
				(`id_feature_value`, `id_lang`, `url_name`, `meta_title`)
				VALUES (
					'.(int) $params['id_feature_value'].', '.(int) $language['id_lang'].',
					\''.pSQL(Tools::link_rewrite($seo_url)).'\',
					\''.pSQL(Tools::getValue('meta_title_'.(int) $language['id_lang']), true).'\'
				)'
            );
        }
    }

    public function hookAfterDeleteFeatureValue($params)
    {
        if (!$params['id_feature_value']) {
            return;
        }

        Db::getInstance()->execute(
            'DELETE FROM '._DB_PREFIX_.'layered_indexable_feature_value_lang_value
			WHERE `id_feature_value` = '.(int) $params['id_feature_value']
        );
    }

    public function hookPostProcessFeatureValue($params)
    {
        foreach (Language::getLanguages(false) as $language) {
            $id_lang = $language['id_lang'];

            if (Tools::getValue('url_name_'.$id_lang)) {
                if (Tools::link_rewrite(Tools::getValue('url_name_'.$id_lang)) != strtolower(Tools::getValue('url_name_'.$id_lang))) {
                    $params['errors'][] = Tools::displayError($this->trans('"%s" is not a valid url', array(Tools::getValue('url_name_'.$id_lang)), 'Modules.Facetedsearch.Admin'));
                }
            }
        }
    }

    public function hookFeatureValueForm($params)
    {
        $values = array();

        if ($result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang`
			FROM '._DB_PREFIX_.'layered_indexable_feature_value_lang_value
			WHERE `id_feature_value` = '.(int) $params['id_feature_value']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = array('url_name' => $data['url_name'], 'meta_title' => $data['meta_title']);
            }
        }

        $this->context->smarty->assign(array(
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
        ));

        return $this->display(__FILE__, 'feature_value_form.tpl');
    }

    public function hookAfterSaveProduct($params)
    {
        if (!$params['id_product']) {
            return;
        }

        self::indexProductPrices((int) $params['id_product']);
        $this->indexAttribute((int) $params['id_product']);
    }

    public function renderWidget($hookName, array $configuration)
    {
        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        return $this->fetch('module:ps_facetedsearch/ps_facetedsearch.tpl');
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return array();
    }

    public function hookCategoryAddition($params)
    {
        $this->rebuildLayeredCache(array(), array((int) $params['category']->id));
    }

    public function hookCategoryUpdate($params)
    {
        /* The category status might (active, inactive) have changed, we have to update the layered cache table structure */
        if (isset($params['category']) && !$params['category']->active) {
            $this->hookCategoryDeletion($params);
        }
    }

    public function hookCategoryDeletion($params)
    {
        $layered_filter_list = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT * FROM '._DB_PREFIX_.'layered_filter'
        );

        foreach ($layered_filter_list as $layered_filter) {
            $data = Tools::unSerialize($layered_filter['filters']);

            if (in_array((int) $params['category']->id, $data['categories'])) {
                unset($data['categories'][array_search((int) $params['category']->id, $data['categories'])]);
                Db::getInstance()->execute(
                    'UPDATE `'._DB_PREFIX_.'layered_filter`
					SET `filters` = \''.pSQL(serialize($data)).'\'
					WHERE `id_layered_filter` = '.(int) $layered_filter['id_layered_filter']
                );
            }
        }

        $this->buildLayeredCategories();
    }

    /*
     * Generate data product attribute
     */
    public function indexAttribute($id_product = null)
    {
        if (is_null($id_product)) {
            Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'layered_product_attribute');
        } else {
            Db::getInstance()->execute(
                'DELETE FROM '._DB_PREFIX_.'layered_product_attribute
				WHERE id_product = '.(int) $id_product
            );
        }

        Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.'layered_product_attribute` (`id_attribute`, `id_product`, `id_attribute_group`, `id_shop`)
			SELECT pac.id_attribute, pa.id_product, ag.id_attribute_group, product_attribute_shop.`id_shop`
			FROM '._DB_PREFIX_.'product_attribute pa'.
            Shop::addSqlAssociation('product_attribute', 'pa').'
			INNER JOIN '._DB_PREFIX_.'product_attribute_combination pac ON pac.id_product_attribute = pa.id_product_attribute
			INNER JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute = pac.id_attribute)
			INNER JOIN '._DB_PREFIX_.'attribute_group ag ON ag.id_attribute_group = a.id_attribute_group
			'.(is_null($id_product) ? '' : 'AND pa.id_product = '.(int) $id_product).'
			GROUP BY a.id_attribute, pa.id_product , product_attribute_shop.`id_shop`'
        );

        return 1;
    }

    /*
     * $cursor $cursor in order to restart indexing from the last state
     */
    public static function fullPricesIndexProcess($cursor = 0, $ajax = false, $smart = false)
    {
        if ($cursor == 0 && !$smart) {
            self::installPriceIndexTable();
        }

        return self::indexPrices($cursor, true, $ajax, $smart);
    }

    /*
     * $cursor $cursor in order to restart indexing from the last state
     */
    public static function pricesIndexProcess($cursor = 0, $ajax = false)
    {
        return self::indexPrices($cursor, false, $ajax);
    }

    private static function indexPrices($cursor = null, $full = false, $ajax = false, $smart = false)
    {
        if ($full) {
            $nb_products = (int) Db::getInstance()->getValue('
				SELECT count(DISTINCT p.`id_product`)
				FROM '._DB_PREFIX_.'product p
				INNER JOIN `'._DB_PREFIX_.'product_shop` ps
					ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))');
        } else {
            $nb_products = (int) Db::getInstance()->getValue('
				SELECT COUNT(DISTINCT p.`id_product`) FROM `'._DB_PREFIX_.'product` p
				INNER JOIN `'._DB_PREFIX_.'product_shop` ps
					ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))
				LEFT JOIN  `'._DB_PREFIX_.'layered_price_index` psi ON (psi.id_product = p.id_product)
				WHERE psi.id_product IS NULL');
        }

        $max_executiontime = @ini_get('max_execution_time');
        if ($max_executiontime > 5 || $max_executiontime <= 0) {
            $max_executiontime = 5;
        }

        $start_time = microtime(true);

        if (function_exists('memory_get_peak_usage')) {
            do {
                $cursor = (int) self::indexPricesUnbreakable((int) $cursor, $full, $smart);
                $time_elapsed = microtime(true) - $start_time;
            } while ($cursor < $nb_products
                && (Tools::getMemoryLimit() == -1 || Tools::getMemoryLimit() > memory_get_peak_usage())
                && $time_elapsed < $max_executiontime);
        } else {
            do {
                $cursor = (int) self::indexPricesUnbreakable((int) $cursor, $full, $smart);
                $time_elapsed = microtime(true) - $start_time;
            } while ($cursor < $nb_products && $time_elapsed < $max_executiontime);
        }
        if (($nb_products > 0 && !$full || $cursor < $nb_products && $full) && !$ajax) {
            $token = substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10);
            if (Tools::usingSecureMode()) {
                $domain = Tools::getShopDomainSsl(true);
            } else {
                $domain = Tools::getShopDomain(true);
            }

            if (!Tools::file_get_contents($domain.__PS_BASE_URI__.'modules/ps_facetedsearch/ps_facetedsearch-price-indexer.php?token='.$token.'&cursor='.(int) $cursor.'&full='.(int) $full)) {
                self::indexPrices((int) $cursor, (int) $full);
            }

            return $cursor;
        }
        if ($ajax && $nb_products > 0 && $cursor < $nb_products && $full) {
            return '{"cursor": '.$cursor.', "count": '.($nb_products - $cursor).'}';
        } elseif ($ajax && $nb_products > 0 && !$full) {
            return '{"cursor": '.$cursor.', "count": '.($nb_products).'}';
        } else {
            Configuration::updateGlobalValue('PS_LAYERED_INDEXED', 1);

            if ($ajax) {
                return '{"result": "ok"}';
            } else {
                return -1;
            }
        }
    }

    /*
     * $cursor $cursor in order to restart indexing from the last state
     */
    private static function indexPricesUnbreakable($cursor, $full = false, $smart = false)
    {
        static $length = 100; // Nb of products to index

        if (is_null($cursor)) {
            $cursor = 0;
        }

        if ($full) {
            $query = '
				SELECT p.`id_product`
				FROM `'._DB_PREFIX_.'product` p
				INNER JOIN `'._DB_PREFIX_.'product_shop` ps
					ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))
				GROUP BY p.`id_product`
				ORDER BY p.`id_product` LIMIT '.(int) $cursor.','.(int) $length;
        } else {
            $query = '
				SELECT p.`id_product`
				FROM `'._DB_PREFIX_.'product` p
				INNER JOIN `'._DB_PREFIX_.'product_shop` ps
					ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))
				LEFT JOIN  `'._DB_PREFIX_.'layered_price_index` psi ON (psi.id_product = p.id_product)
				WHERE psi.id_product IS NULL
				GROUP BY p.`id_product`
				ORDER BY p.`id_product` LIMIT 0,'.(int) $length;
        }

        foreach (Db::getInstance()->executeS($query) as $product) {
            self::indexProductPrices((int) $product['id_product'], ($smart && $full));
        }

        return (int) ($cursor + $length);
    }

    public static function indexProductPrices($id_product, $smart = true)
    {
        static $groups = null;

        if (is_null($groups)) {
            $groups = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT id_group FROM `'._DB_PREFIX_.'group_reduction`');
            if (!$groups) {
                $groups = array();
            }
        }

        $shop_list = Shop::getShops(false, null, true);

        foreach ($shop_list as $id_shop) {
            static $currency_list = null;

            if (is_null($currency_list)) {
                $currency_list = Currency::getCurrencies(false, 1, new Shop($id_shop));
            }

            $min_price = array();
            $max_price = array();

            if ($smart) {
                Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'layered_price_index` WHERE `id_product` = '.(int) $id_product.' AND `id_shop` = '.(int) $id_shop);
            }

            if (Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX')) {
                $max_tax_rate = Db::getInstance()->getValue('
					SELECT max(t.rate) max_rate
					FROM `'._DB_PREFIX_.'product_shop` p
					LEFT JOIN `'._DB_PREFIX_.'tax_rules_group` trg ON (trg.id_tax_rules_group = p.id_tax_rules_group AND p.id_shop = '.(int) $id_shop.')
					LEFT JOIN `'._DB_PREFIX_.'tax_rule` tr ON (tr.id_tax_rules_group = trg.id_tax_rules_group)
					LEFT JOIN `'._DB_PREFIX_.'tax` t ON (t.id_tax = tr.id_tax AND t.active = 1)
					WHERE id_product = '.(int) $id_product.'
					GROUP BY id_product');
            } else {
                $max_tax_rate = 0;
            }

            $product_min_prices = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT id_shop, id_currency, id_country, id_group, from_quantity
			FROM `'._DB_PREFIX_.'specific_price`
			WHERE id_product = '.(int) $id_product);

            // Get min price
            foreach ($currency_list as $currency) {
                $price = Product::priceCalculation(
                    $id_shop,
                    (int) $id_product,
                    null,
                    null,
                    null,
                    null,
                    $currency['id_currency'],
                    null,
                    null,
                    false,
                    6,
                    false,
                    true,
                    true,
                    $specific_price_output,
                    true
                );

                if (!isset($max_price[$currency['id_currency']])) {
                    $max_price[$currency['id_currency']] = 0;
                }
                if (!isset($min_price[$currency['id_currency']])) {
                    $min_price[$currency['id_currency']] = null;
                }
                if ($price > $max_price[$currency['id_currency']]) {
                    $max_price[$currency['id_currency']] = $price;
                }
                if ($price == 0) {
                    continue;
                }
                if (is_null($min_price[$currency['id_currency']]) || $price < $min_price[$currency['id_currency']]) {
                    $min_price[$currency['id_currency']] = $price;
                }
            }

            foreach ($product_min_prices as $specific_price) {
                foreach ($currency_list as $currency) {
                    if ($specific_price['id_currency'] && $specific_price['id_currency'] != $currency['id_currency']) {
                        continue;
                    }
                    $price = Product::priceCalculation(
                        (($specific_price['id_shop'] == 0) ? null : (int) $specific_price['id_shop']),
                        (int) $id_product,
                        null,
                        (($specific_price['id_country'] == 0) ? null : $specific_price['id_country']),
                        null,
                        null,
                        $currency['id_currency'],
                        (($specific_price['id_group'] == 0) ? null : $specific_price['id_group']),
                        $specific_price['from_quantity'],
                        false,
                        6,
                        false,
                        true,
                        true,
                        $specific_price_output,
                        true
                    );

                    if (!isset($max_price[$currency['id_currency']])) {
                        $max_price[$currency['id_currency']] = 0;
                    }
                    if (!isset($min_price[$currency['id_currency']])) {
                        $min_price[$currency['id_currency']] = null;
                    }
                    if ($price > $max_price[$currency['id_currency']]) {
                        $max_price[$currency['id_currency']] = $price;
                    }
                    if ($price == 0) {
                        continue;
                    }
                    if (is_null($min_price[$currency['id_currency']]) || $price < $min_price[$currency['id_currency']]) {
                        $min_price[$currency['id_currency']] = $price;
                    }
                }
            }

            foreach ($groups as $group) {
                foreach ($currency_list as $currency) {
                    $price = Product::priceCalculation(
                        null,
                        (int) $id_product,
                        null,
                        null,
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
                        $specific_price_output,
                        true
                    );

                    if (!isset($max_price[$currency['id_currency']])) {
                        $max_price[$currency['id_currency']] = 0;
                    }
                    if (!isset($min_price[$currency['id_currency']])) {
                        $min_price[$currency['id_currency']] = null;
                    }
                    if ($price > $max_price[$currency['id_currency']]) {
                        $max_price[$currency['id_currency']] = $price;
                    }
                    if ($price == 0) {
                        continue;
                    }
                    if (is_null($min_price[$currency['id_currency']]) || $price < $min_price[$currency['id_currency']]) {
                        $min_price[$currency['id_currency']] = $price;
                    }
                }
            }

            $values = array();
            foreach ($currency_list as $currency) {
                $values[] = '('.(int) $id_product.',
					'.(int) $currency['id_currency'].',
					'.$id_shop.',
					'.(int) $min_price[$currency['id_currency']].',
					'.(int) Tools::ps_round($max_price[$currency['id_currency']] * (100 + $max_tax_rate) / 100, 0).')';
            }

            Db::getInstance()->execute('
				INSERT INTO `'._DB_PREFIX_.'layered_price_index` (id_product, id_currency, id_shop, price_min, price_max)
				VALUES '.implode(',', $values).'
				ON DUPLICATE KEY UPDATE id_product = id_product # avoid duplicate keys');
        }
    }

    public function getContent()
    {
        global $cookie;
        $message = '';

        if (Tools::isSubmit('SubmitFilter')) {
            if (!Tools::getValue('layered_tpl_name')) {
                $message = $this->displayError($this->trans('Filter template name required (cannot be empty)', array(), 'Modules.Facetedsearch.Admin'));
            } elseif (!Tools::getValue('categoryBox')) {
                $message = $this->displayError($this->trans('You must select at least one category.', array(), 'Modules.Facetedsearch.Admin'));
            } else {
                if (Tools::getValue('id_layered_filter')) {
                    Db::getInstance()->execute(
                        'DELETE FROM '._DB_PREFIX_.'layered_filter
						WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter')
                    );
                    $this->buildLayeredCategories();
                }

                if (Tools::getValue('scope') == 1) {
                    Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'layered_filter');
                    $categories = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
                        'SELECT id_category
						FROM '._DB_PREFIX_.'category'
                    );

                    foreach ($categories as $category) {
                        $_POST['categoryBox'][] = (int) $category['id_category'];
                    }
                }

                $id_layered_filter = (int) Tools::getValue('id_layered_filter');

                if (!$id_layered_filter) {
                    $id_layered_filter = (int) Db::getInstance()->Insert_ID();
                }

                $shop_list = array();

                if (isset($_POST['checkBoxShopAsso_layered_filter'])) {
                    foreach ($_POST['checkBoxShopAsso_layered_filter'] as $id_shop => $row) {
                        $assos[] = array('id_object' => (int) $id_layered_filter, 'id_shop' => (int) $id_shop);
                        $shop_list[] = (int) $id_shop;
                    }
                } else {
                    $shop_list = array(Context::getContext()->shop->id);
                }

                Db::getInstance()->execute(
                    'DELETE FROM '._DB_PREFIX_.'layered_filter_shop
					WHERE `id_layered_filter` = '.(int) $id_layered_filter
                );

                if (count($_POST['categoryBox'])) {
                    /* Clean categoryBox before use */
                    if (isset($_POST['categoryBox']) && is_array($_POST['categoryBox'])) {
                        foreach ($_POST['categoryBox'] as &$category_box_tmp) {
                            $category_box_tmp = (int) $category_box_tmp;
                        }
                    }

                    $filter_values = array();

                    foreach ($_POST['categoryBox'] as $idc) {
                        $filter_values['categories'][] = (int) $idc;
                    }

                    $filter_values['shop_list'] = $shop_list;
                    $values = false;

                    foreach ($_POST['categoryBox'] as $id_category_layered) {
                        foreach ($_POST as $key => $value) {
                            if (substr($key, 0, 17) == 'layered_selection' && $value == 'on') {
                                $values = true;
                                $type = 0;
                                $limit = 0;

                                if (Tools::getValue($key.'_filter_type')) {
                                    $type = Tools::getValue($key.'_filter_type');
                                }
                                if (Tools::getValue($key.'_filter_show_limit')) {
                                    $limit = Tools::getValue($key.'_filter_show_limit');
                                }

                                $filter_values[$key] = array(
                                    'filter_type' => (int) $type,
                                    'filter_show_limit' => (int) $limit,
                                );
                            }
                        }
                    }

                    $values_to_insert = array(
                        'name' => pSQL(Tools::getValue('layered_tpl_name')),
                        'filters' => pSQL(serialize($filter_values)),
                        'n_categories' => (int) count($filter_values['categories']),
                        'date_add' => date('Y-m-d H:i:s'), );

                    if (isset($_POST['id_layered_filter']) && $_POST['id_layered_filter']) {
                        $values_to_insert['id_layered_filter'] = (int) Tools::getValue('id_layered_filter');
                    }

                    $id_layered_filter = isset($values_to_insert['id_layered_filter']) ? (int) $values_to_insert['id_layered_filter'] : 'NULL';
                    $sql = 'INSERT INTO '._DB_PREFIX_.'layered_filter (name, filters, n_categories, date_add, id_layered_filter) VALUES ("'.pSQL($values_to_insert['name']).'", "'.$values_to_insert['filters'].'",'.(int) $values_to_insert['n_categories'].',"'.pSQL($values_to_insert['date_add']).'",'.$id_layered_filter.')';
                    Db::getInstance()->execute($sql);
                    $id_layered_filter = (int) Db::getInstance()->Insert_ID();

                    if (isset($assos)) {
                        foreach ($assos as $asso) {
                            Db::getInstance()->execute(
                                'INSERT INTO '._DB_PREFIX_.'layered_filter_shop (`id_layered_filter`, `id_shop`)
							VALUES('.$id_layered_filter.', '.(int) $asso['id_shop'].')'
                            );
                        }
                    }

                    $this->buildLayeredCategories();
                    $message = $this->displayConfirmation($this->trans('Your filter', array(), 'Modules.Facetedsearch.Admin').' "'.Tools::safeOutput(Tools::getValue('layered_tpl_name')).'" '.
                        ((isset($_POST['id_layered_filter']) && $_POST['id_layered_filter']) ? $this->trans('was updated successfully.', array(), 'Modules.Facetedsearch.Admin') : $this->trans('was added successfully.', array(), 'Modules.Facetedsearch.Admin')));
                }
            }
        } elseif (Tools::isSubmit('submitLayeredSettings')) {
            Configuration::updateValue('PS_LAYERED_SHOW_QTIES', (int) Tools::getValue('ps_layered_show_qties'));
            Configuration::updateValue('PS_LAYERED_FULL_TREE', (int) Tools::getValue('ps_layered_full_tree'));
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_USETAX', (int) Tools::getValue('ps_layered_filter_price_usetax'));
            Configuration::updateValue('PS_LAYERED_FILTER_CATEGORY_DEPTH', (int) Tools::getValue('ps_layered_filter_category_depth'));
            Configuration::updateValue('PS_LAYERED_FILTER_PRICE_ROUNDING', (int) Tools::getValue('ps_layered_filter_price_rounding'));

            $this->ps_layered_full_tree = (int) Tools::getValue('ps_layered_full_tree');

            if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                $message = '<div class="alert alert-success">'.$this->trans('Settings saved successfully', array(), 'Modules.Facetedsearch.Admin').'</div>';
            } else {
                $message = '<div class="conf">'.$this->trans('Settings saved successfully', array(), 'Modules.Facetedsearch.Admin').'</div>';
            }
        } elseif (Tools::getValue('deleteFilterTemplate')) {
            $layered_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
                'SELECT filters
				FROM '._DB_PREFIX_.'layered_filter
				WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter')
            );

            if ($layered_values) {
                Db::getInstance()->execute(
                    'DELETE FROM '._DB_PREFIX_.'layered_filter
					WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter').' LIMIT 1'
                );
                $this->buildLayeredCategories();
                $message = $this->displayConfirmation($this->trans('Filter template deleted, categories updated (reverted to default Filter template).', array(), 'Modules.Facetedsearch.Admin'));
            } else {
                $message = $this->displayError($this->trans('Filter template not found', array(), 'Modules.Facetedsearch.Admin'));
            }
        }

        $category_box = array();
        $attribute_groups = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT ag.id_attribute_group, ag.is_color_group, agl.name, COUNT(DISTINCT(a.id_attribute)) n
			FROM '._DB_PREFIX_.'attribute_group ag
			LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (agl.id_attribute_group = ag.id_attribute_group)
			LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute_group = ag.id_attribute_group)
			WHERE agl.id_lang = '.(int) $cookie->id_lang.'
			GROUP BY ag.id_attribute_group'
        );

        $features = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT fl.id_feature, fl.name, COUNT(DISTINCT(fv.id_feature_value)) n
			FROM '._DB_PREFIX_.'feature_lang fl
			LEFT JOIN '._DB_PREFIX_.'feature_value fv ON (fv.id_feature = fl.id_feature)
			WHERE (fv.custom IS NULL OR fv.custom = 0) AND fl.id_lang = '.(int) $cookie->id_lang.'
			GROUP BY fl.id_feature'
        );

        if (Shop::isFeatureActive() && count(Shop::getShops(true, null, true)) > 1) {
            $helper = new HelperForm();
            $helper->id = Tools::getValue('id_layered_filter', null);
            $helper->table = 'layered_filter';
            $helper->identifier = 'id_layered_filter';
            $this->context->smarty->assign('asso_shops', $helper->renderAssoShop());
        }

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
            $tree_categories_helper = new HelperTreeCategories('categories-treeview');
            $tree_categories_helper->setRootCategory((Shop::getContext() == Shop::CONTEXT_SHOP ? Category::getRootCategory()->id_category : 0))
                ->setUseCheckBox(true);
        } else {
            if (Shop::getContext() == Shop::CONTEXT_SHOP) {
                $root_category = Category::getRootCategory();
                $root_category = array('id_category' => $root_category->id_category, 'name' => $root_category->name);
            } else {
                $root_category = array('id_category' => '0', 'name' => $this->trans('Root', array(), 'Modules.Facetedsearch.Admin'));
            }

            $tree_categories_helper = new Helper();
        }

        $module_url = Tools::getProtocol(Tools::usingSecureMode()).$_SERVER['HTTP_HOST'].$this->getPathUri();

        if (method_exists($this->context->controller, 'addJquery')) {
            $this->context->controller->addJS($this->_path.'js/ps_facetedsearchadmin.js');

            if (version_compare(_PS_VERSION_, '1.6.0.3', '>=') === true) {
                $this->context->controller->addjqueryPlugin('sortable');
            } elseif (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                $this->context->controller->addJS(_PS_JS_DIR_.'jquery/plugins/jquery.sortable.js');
            } else {
                $this->context->controller->addJS($this->_path.'js/jquery.sortable.js');
            }
        }

        $this->context->controller->addCSS($this->_path.'css/ps_facetedsearch_admin.css');

        if (Tools::getValue('add_new_filters_template')) {
            $this->context->smarty->assign(array(
                'current_url' => $this->context->link->getAdminLink('AdminModules').'&configure=ps_facetedsearch&tab_module=front_office_features&module_name=ps_facetedsearch',
                'uri' => $this->getPathUri(),
                'id_layered_filter' => 0,
                'template_name' => sprintf($this->trans('My template - %s', array(), 'Modules.Facetedsearch.Admin'), date('Y-m-d')),
                'attribute_groups' => $attribute_groups,
                'features' => $features,
                'total_filters' => 6 + count($attribute_groups) + count($features),
            ));

            if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                $this->context->smarty->assign('categories_tree', $tree_categories_helper->render());
            } else {
                $this->context->smarty->assign('categories_tree', $tree_categories_helper->renderCategoryTree(
                    $root_category,
                    array(),
                    'categoryBox'
                ));
            }

            return $this->display(__FILE__, 'views/templates/admin/add.tpl');
        } elseif (Tools::getValue('edit_filters_template')) {
            $template = Db::getInstance()->getRow(
                '
				SELECT *
				FROM `'._DB_PREFIX_.'layered_filter`
				WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter')
            );

            $filters = Tools::unSerialize($template['filters']);

            if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
                $tree_categories_helper->setSelectedCategories($filters['categories']);
                $this->context->smarty->assign('categories_tree', $tree_categories_helper->render());
            } else {
                $this->context->smarty->assign('categories_tree', $tree_categories_helper->renderCategoryTree(
                    $root_category,
                    $filters['categories'],
                    'categoryBox'
                ));
            }

            $select_shops = $filters['shop_list'];
            unset($filters['categories']);
            unset($filters['shop_list']);

            $this->context->smarty->assign(array(
                'current_url' => $this->context->link->getAdminLink('AdminModules').'&configure=ps_facetedsearch&tab_module=front_office_features&module_name=ps_facetedsearch',
                'uri' => $this->getPathUri(),
                'id_layered_filter' => (int) Tools::getValue('id_layered_filter'),
                'template_name' => $template['name'],
                'attribute_groups' => $attribute_groups,
                'features' => $features,
                'filters' => Tools::jsonEncode($filters),
                'total_filters' => 6 + count($attribute_groups) + count($features),
            ));

            return $this->display(__FILE__, 'views/templates/admin/add.tpl');
        } else {
            $this->context->smarty->assign(array(
                'message' => $message,
                'uri' => $this->getPathUri(),
                'PS_LAYERED_INDEXED' => Configuration::getGlobalValue('PS_LAYERED_INDEXED'),
                'current_url' => Tools::safeOutput(preg_replace('/&deleteFilterTemplate=[0-9]*&id_layered_filter=[0-9]*/', '', $_SERVER['REQUEST_URI'])),
                'id_lang' => Context::getContext()->cookie->id_lang,
                'token' => substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10),
                'base_folder' => urlencode(_PS_ADMIN_DIR_),
                'price_indexer_url' => $module_url.'ps_facetedsearch-price-indexer.php'.'?token='.substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10),
                'full_price_indexer_url' => $module_url.'ps_facetedsearch-price-indexer.php'.'?token='.substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10).'&full=1',
                'attribute_indexer_url' => $module_url.'ps_facetedsearch-attribute-indexer.php'.'?token='.substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10),
                'filters_templates' => Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('SELECT * FROM '._DB_PREFIX_.'layered_filter ORDER BY date_add DESC'),
                'show_quantities' => Configuration::get('PS_LAYERED_SHOW_QTIES'),
                'full_tree' => $this->ps_layered_full_tree,
                'category_depth' => Configuration::get('PS_LAYERED_FILTER_CATEGORY_DEPTH'),
                'price_use_tax' => (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX'),
                'limit_warning' => $this->displayLimitPostWarning(21 + count($attribute_groups) * 3 + count($features) * 3),
                'price_use_rounding' => (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING'),
            ));

            return $this->display(__FILE__, 'views/templates/admin/view.tpl');
        }
    }

    public function displayLimitPostWarning($count)
    {
        $return = array();
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

    public function getProductByFilters(
        $products_per_page,
        $page,
        $order_by,
        $order_way,
        $id_lang,
        $selected_filters = array()
    ) {
        $products_per_page = (int)$products_per_page;

        if (!Validate::isOrderBy($order_by)) {
            $order_by = 'cp.position';
        }

        if (!Validate::isOrderWay($order_way)) {
            $order_way = 'ASC';
        }

        $order_clause = $order_by . ' ' . $order_way;

        $home_category = Configuration::get('PS_HOME_CATEGORY');
        /* If the current category isn't defined or if it's homepage, we have nothing to display */
        $id_parent = (int)Tools::getValue('id_category', Tools::getValue('id_category_layered', $home_category));

        $alias_where = 'p';
        if (version_compare(_PS_VERSION_, '1.5', '>')) {
            $alias_where = 'product_shop';
        }

        $query_filters_where = ' AND ' . $alias_where . '.`active` = 1 AND ' . $alias_where . '.`visibility` IN ("both", "catalog")';
        $query_filters_from = '';

        $parent = new Category((int)$id_parent);

        foreach ($selected_filters as $key => $filter_values) {
            if (!count($filter_values)) {
                continue;
            }

            preg_match('/^(.*[^_0-9])/', $key, $res);
            $key = $res[1];

            switch ($key) {
                case 'id_feature':
                    $sub_queries = array();
                    foreach ($filter_values as $filter_value) {
                        $filter_value_array = explode('_', $filter_value);
                        if (!isset($sub_queries[$filter_value_array[0]])) {
                            $sub_queries[$filter_value_array[0]] = array();
                        }
                        $sub_queries[$filter_value_array[0]][] = 'fp.`id_feature_value` = ' . (int)$filter_value_array[1];
                    }
                    foreach ($sub_queries as $sub_query) {
                        $query_filters_where .= ' AND p.id_product IN (SELECT `id_product` FROM `' . _DB_PREFIX_ . 'feature_product` fp WHERE ';
                        $query_filters_where .= implode(' OR ', $sub_query) . ') ';
                    }
                    break;

                case 'id_attribute_group':
                    $sub_queries = array();

                    foreach ($filter_values as $filter_value) {
                        $filter_value_array = explode('_', $filter_value);
                        if (!isset($sub_queries[$filter_value_array[0]])) {
                            $sub_queries[$filter_value_array[0]] = array();
                        }
                        $sub_queries[$filter_value_array[0]][] = 'pac.`id_attribute` = ' . (int)$filter_value_array[1];
                    }
                    foreach ($sub_queries as $sub_query) {
                        $query_filters_where .= ' AND p.id_product IN (SELECT pa.`id_product`
                        FROM `' . _DB_PREFIX_ . 'product_attribute_combination` pac
                        LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa
                        ON (pa.`id_product_attribute` = pac.`id_product_attribute`)' .
                            Shop::addSqlAssociation('product_attribute', 'pa') . '
                        WHERE ' . implode(' OR ', $sub_query) . ') ';
                    }
                    break;

                case 'category':
                    $query_filters_where .= ' AND p.id_product IN (SELECT id_product FROM ' . _DB_PREFIX_ . 'category_product cp WHERE ';
                    foreach ($selected_filters['category'] as $id_category) {
                        $query_filters_where .= 'cp.`id_category` = ' . (int)$id_category . ' OR ';
                    }
                    $query_filters_where = rtrim($query_filters_where, 'OR ') . ')';
                    break;

                case 'quantity':
                    if (count($selected_filters['quantity']) == 2) {
                        break;
                    }

                    $query_filters_where .= ' AND sa.quantity ' . (!$selected_filters['quantity'][0] ? '<=' : '>') . ' 0 ';
                    $query_filters_from .= 'LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa ON (sa.id_product = p.id_product ' . StockAvailable::addSqlShopRestriction(null, null, 'sa') . ') ';
                    break;

                case 'manufacturer':
                    $selected_filters['manufacturer'] = array_map('intval', $selected_filters['manufacturer']);
                    $query_filters_where .= ' AND p.id_manufacturer IN (' . implode($selected_filters['manufacturer'], ',') . ')';
                    break;

                case 'condition':
                    if (count($selected_filters['condition']) == 3) {
                        break;
                    }
                    $query_filters_where .= ' AND ' . $alias_where . '.condition IN (';
                    foreach ($selected_filters['condition'] as $cond) {
                        $query_filters_where .= '\'' . pSQL($cond) . '\',';
                    }
                    $query_filters_where = rtrim($query_filters_where, ',') . ')';
                    break;

                case 'weight':
                    if ($selected_filters['weight'][0] != 0 || $selected_filters['weight'][1] != 0) {
                        $query_filters_where .= ' AND p.`weight` BETWEEN ' . (float)($selected_filters['weight'][0] - 0.001) . ' AND ' . (float)($selected_filters['weight'][1] + 0.001);
                    }
                    break;

                case 'price':
                    if (isset($selected_filters['price'])) {
                        if ($selected_filters['price'][0] !== '' || $selected_filters['price'][1] !== '') {
                            $price_filter = array();
                            $price_filter['min'] = (float)($selected_filters['price'][0]);
                            $price_filter['max'] = (float)($selected_filters['price'][1]);
                        }
                    } else {
                        $price_filter = false;
                    }
                    break;
            }
        }

        $context = Context::getContext();
        $id_currency = (int)$context->currency->id;

        $price_filter_query_in = ''; // All products with price range between price filters limits
        $price_filter_query_out = ''; // All products with a price filters limit on it price range
        if (isset($price_filter) && $price_filter) {
            $price_filter_query_in = 'INNER JOIN `' . _DB_PREFIX_ . 'layered_price_index` psi
            ON
            (
                psi.price_min <= ' . (int)$price_filter['max'] . '
                AND psi.price_max >= ' . (int)$price_filter['min'] . '
                AND psi.`id_product` = p.`id_product`
                AND psi.`id_shop` = ' . (int)$context->shop->id . '
                AND psi.`id_currency` = ' . $id_currency . '
            )';

            $price_filter_query_out = 'INNER JOIN `' . _DB_PREFIX_ . 'layered_price_index` psi
            ON
                ((psi.price_min < ' . (int)$price_filter['min'] . ' AND psi.price_max > ' . (int)$price_filter['min'] . ')
                OR
                (psi.price_max > ' . (int)$price_filter['max'] . ' AND psi.price_min < ' . (int)$price_filter['max'] . '))
                AND psi.`id_product` = p.`id_product`
                AND psi.`id_shop` = ' . (int)$context->shop->id . '
                AND psi.`id_currency` = ' . $id_currency;
        }

        $query_filters_from .= Shop::addSqlAssociation('product', 'p');
        $extraWhereQuery = '';

        if (!empty($selected_filters['category'])) {
            $categories = array_map('intval', $selected_filters['category']);
        }

        if (isset($price_filter) && $price_filter) {
            static $ps_layered_filter_price_usetax = null;
            static $ps_layered_filter_price_rounding = null;

            if ($ps_layered_filter_price_usetax === null) {
                $ps_layered_filter_price_usetax = Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX');
            }

            if ($ps_layered_filter_price_rounding === null) {
                $ps_layered_filter_price_rounding = Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING');
            }

            if (empty($selected_filters['category'])) {
                $all_products_out = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                    SELECT p.`id_product` id_product
                    FROM `' . _DB_PREFIX_ . 'product` p JOIN ' . _DB_PREFIX_ . 'category_product cp USING (id_product)
                    INNER JOIN ' . _DB_PREFIX_ . 'category c ON (c.id_category = cp.id_category AND
                        ' . ($this->ps_layered_full_tree ? 'c.nleft >= ' . (int)$parent->nleft . '
                        AND c.nright <= ' . (int)$parent->nright : 'c.id_category = ' . (int)$id_parent) . '
                        AND c.active = 1)
                    ' . $price_filter_query_out . '
                    ' . $query_filters_from . '
                    WHERE 1 ' . $query_filters_where . ' GROUP BY cp.id_product');
            } else {
                $all_products_out = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                    SELECT p.`id_product` id_product
                    FROM `' . _DB_PREFIX_ . 'product` p JOIN ' . _DB_PREFIX_ . 'category_product cp USING (id_product)
                    ' . $price_filter_query_out . '
                    ' . $query_filters_from . '
                    WHERE cp.`id_category` IN (' . implode(',', $categories) . ') ' . $query_filters_where . ' GROUP BY cp.id_product');
            }

            /* for this case, price could be out of range, so we need to compute the real price */
            foreach ($all_products_out as $product) {
                $price = Product::getPriceStatic($product['id_product'], $ps_layered_filter_price_usetax);
                if ($ps_layered_filter_price_rounding) {
                    $price = (int)$price;
                }
                if ($price < $price_filter['min'] || $price > $price_filter['max']) {
                    // out of range price, exclude the product
                    $product_id_delete_list[] = (int)$product['id_product'];
                }
            }
            if (!empty($product_id_delete_list)) {
                $extraWhereQuery = ' AND p.id_product NOT IN (' . implode(',', $product_id_delete_list) . ') ';
            }
        }
        if (empty($selected_filters['category'])) {
            $catFilterRestrictionDerivedTable = ' ((SELECT cp.id_product, MIN(cp.position) position FROM ' . _DB_PREFIX_ . 'category c
                                                         STRAIGHT_JOIN ' . _DB_PREFIX_ . 'category_product cp ON (c.id_category = cp.id_category AND
                                                         c.id_category = ' . (int)$id_parent . '
                                                         AND c.active = 1)
                                                         STRAIGHT_JOIN `' . _DB_PREFIX_ . 'product` p ON (p.id_product=cp.id_product)
                                                         ' . $price_filter_query_in . '
                                                         ' . $query_filters_from . '
                                                         WHERE 1 ' . $query_filters_where . $extraWhereQuery . '
                                                         GROUP BY cp.id_product)';
            if ($this->ps_layered_full_tree) {
                // add other products in subcategories, but not present in the main cat!
                $catFilterRestrictionDerivedTable .= ' UNION ALL (SELECT cp.id_product, MIN(cp.position) position FROM ' . _DB_PREFIX_ . 'category c
                                                         STRAIGHT_JOIN ' . _DB_PREFIX_ . 'category_product cp ON (c.id_category = cp.id_category AND
                                                         c.id_category != ' . (int)$id_parent . '
                                                         AND c.nleft >= ' . (int)$parent->nleft . '
                                                         AND c.nright <= ' . (int)$parent->nright.'
                                                         AND c.active = 1)
                                                         STRAIGHT_JOIN `' . _DB_PREFIX_ . 'product` p ON (p.id_product=cp.id_product)
                                                         ' . $price_filter_query_in . '
                                                         ' . $query_filters_from . '
                                                         WHERE NOT EXISTS(SELECT * FROM ' . _DB_PREFIX_ . 'category_product cpe 
                                                                            WHERE cp.id_product=cpe.id_product AND cpe.id_category = ' . (int)$id_parent . ')
                                                         ' . $query_filters_where . $extraWhereQuery . '
                                                         GROUP BY cp.id_product)';
            }
            $catFilterRestrictionDerivedTable .= ')';
        } else {
            $catFilterRestrictionDerivedTable = ' (SELECT cp.id_product, MIN(cp.position) position FROM ' . _DB_PREFIX_ . 'category_product cp
                                                         STRAIGHT_JOIN `' . _DB_PREFIX_ . 'product` p ON (p.id_product=cp.id_product)
                                                         ' . $price_filter_query_in . '
                                                         ' . $query_filters_from . '
                                                         WHERE cp.`id_category` IN (' . implode(',', $categories) . ') ' . $query_filters_where . $extraWhereQuery . '
                                                         GROUP BY cp.id_product)';
        }

        $this->nbr_products = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT COUNT(*) FROM ' . $catFilterRestrictionDerivedTable . ' ps');


        if ($this->nbr_products == 0) {
            $products = array();
        } else {
            $nb_day_new_product = (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20);

            if (version_compare(_PS_VERSION_, '1.6.1', '>=') === true) {
                $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                    SELECT
                        p.*,
                        ' . ($alias_where == 'p' ? '' : 'product_shop.*,') . '
                        ' . $alias_where . '.id_category_default,
                        pl.*,
                        image_shop.`id_image` id_image,
                        il.legend,
                        m.name manufacturer_name,
                        ' . (Combination::isFeatureActive() ? 'product_attribute_shop.id_product_attribute id_product_attribute,' : '') . '
                        DATEDIFF(' . $alias_where . '.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00", INTERVAL ' . (int)$nb_day_new_product . ' DAY)) > 0 AS new,
                        stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity' . (Combination::isFeatureActive() ? ', product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity' : '') . '
                    FROM '.$catFilterRestrictionDerivedTable.' cp
                    LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                    ' . Shop::addSqlAssociation('product', 'p') .
                    (Combination::isFeatureActive() ?
                        ' LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop
                        ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id . ')' : '') . '
                    LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pl.id_product = p.id_product' . Shop::addSqlRestrictionOnLang('pl') . ' AND pl.id_lang = ' . (int)$id_lang . ')
                    LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
                        ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int)$context->shop->id . ')
                    LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ')
                    LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (m.id_manufacturer = p.id_manufacturer)
                    ' . Product::sqlStock('p', 0) . '
                    WHERE ' . $alias_where . '.`active` = 1 AND ' . $alias_where . '.`visibility` IN ("both", "catalog")
                    ORDER BY ' . $order_clause . ' , cp.id_product' .
                    ' LIMIT ' . (((int)$page - 1) * $products_per_page . ',' . $products_per_page));
            } else {
                $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                    SELECT
                        p.*,
                        ' . ($alias_where == 'p' ? '' : 'product_shop.*,') . '
                        ' . $alias_where . '.id_category_default,
                        pl.*,
                        MAX(image_shop.`id_image`) id_image,
                        il.legend,
                        m.name manufacturer_name,
                        ' . (Combination::isFeatureActive() ? 'MAX(product_attribute_shop.id_product_attribute) id_product_attribute,' : '') . '
                        DATEDIFF(' . $alias_where . '.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00", INTERVAL ' . (int)$nb_day_new_product . ' DAY)) > 0 AS new,
                        stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity' . (Combination::isFeatureActive() ? ', MAX(product_attribute_shop.minimal_quantity) AS product_attribute_minimal_quantity' : '') . '
                    FROM '.$catFilterRestrictionDerivedTable.' cp
                    LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = cp.`id_product`
                    ' . Shop::addSqlAssociation('product', 'p') .
                    (Combination::isFeatureActive() ?
                        'LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON (p.`id_product` = pa.`id_product`)
                    ' . Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int)$context->shop->id) : '') . '
                    LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pl.id_product = p.id_product' . Shop::addSqlRestrictionOnLang('pl') . ' AND pl.id_lang = ' . (int)$id_lang . ')
                    LEFT JOIN `' . _DB_PREFIX_ . 'image` i  ON (i.`id_product` = p.`id_product`)' .
                    Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int)$id_lang . ')
                    LEFT JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (m.id_manufacturer = p.id_manufacturer)
                    ' . Product::sqlStock('p', 0) . '
                    WHERE ' . $alias_where . '.`active` = 1 AND ' . $alias_where . '.`visibility` IN ("both", "catalog")
                    GROUP BY product_shop.id_product
                    ORDER BY ' . $order_clause . ' , cp.id_product' .
                    ' LIMIT ' . (((int)$page - 1) * $products_per_page . ',' . $products_per_page));
            }
        }

        if ($order_by == 'p.price') {
            Tools::orderbyPrice($products, $order_way);
        }

        return array(
            'products' => $products,
            'count' => $this->nbr_products,
        );
    }

    private static function query($sql_query)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->query($sql_query);
    }

    public function getFilterBlock(
        $selected_filters = array(),
        $compute_range_filters = true
    ) {
        global $cookie;

        // Remove all empty selected filters
        foreach ($selected_filters as $key => $value) {
            switch ($key) {
                case 'price':
                case 'weight':
                    if ($value[0] === '' && $value[1] === '') {
                        unset($selected_filters[$key]);
                    }
                    break;
                default:
                    if ($value == '' || $value == array()) {
                        unset($selected_filters[$key]);
                    }
                    break;
            }
        }

        static $latest_selected_filters = null;
        static $productCache = array();
        $context = Context::getContext();

        $id_lang = $context->language->id;
        $currency = $context->currency;
        $id_shop = (int) $context->shop->id;
        $alias = 'product_shop';

        $id_parent = (int) Tools::getValue('id_category', Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY')));

        $parent = new Category((int) $id_parent, $id_lang);

        /* Get the filters for the current category */
        $filters = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT type, id_value, filter_show_limit, filter_type FROM '._DB_PREFIX_.'layered_category
			WHERE id_category = '.(int) $id_parent.'
				AND id_shop = '.$id_shop.'
			GROUP BY `type`, id_value ORDER BY position ASC'
        );

        $catRestrictionDerivedTable = '(SELECT DISTINCT cp.id_product, p.id_manufacturer, product_shop.condition, p.weight FROM '._DB_PREFIX_.'category c
                                             STRAIGHT_JOIN '._DB_PREFIX_.'category_product cp ON (c.id_category = cp.id_category AND
                                             '.($this->ps_layered_full_tree ? 'c.nleft >= '.(int) $parent->nleft.'
                                             AND c.nright <= '.(int) $parent->nright : 'c.id_category = '.(int) $id_parent).'
                                             AND c.active = 1)
                                             STRAIGHT_JOIN '._DB_PREFIX_.'product_shop product_shop ON (product_shop.id_product = cp.id_product
                                             AND product_shop.id_shop = '.(int) $context->shop->id.')
                                             STRAIGHT_JOIN '._DB_PREFIX_.'product p ON (p.id_product=cp.id_product)
                                             WHERE product_shop.`active` = 1 AND product_shop.`visibility` IN ("both", "catalog"))';

        $filter_blocks = array();
        foreach ($filters as $filter) {
            $cacheKey = $filter['type'] . '-' . $filter['id_value'];
            if ($latest_selected_filters == $selected_filters && isset($productCache[$cacheKey])) {
                $products = $productCache[$cacheKey];
            } else {
                $sql_query = array('select' => '', 'from' => '', 'join' => '', 'where' => '', 'group' => '');
                switch ($filter['type']) {
                    case 'price':
                        $sql_query['select'] = 'SELECT p.`id_product`, psi.price_min, psi.price_max ';
                        // price slider is not filter dependent
                        $sql_query['from'] = '
                        FROM ' . $catRestrictionDerivedTable . ' p';
                        $sql_query['join'] = 'INNER JOIN `' . _DB_PREFIX_ . 'layered_price_index` psi
                                    ON (psi.id_product = p.id_product AND psi.id_currency = ' . (int)$context->currency->id . ' AND psi.id_shop=' . (int)$context->shop->id . ')';
                        $sql_query['where'] = 'WHERE 1';
                        break;
                    case 'weight':
                        $sql_query['select'] = 'SELECT p.`id_product`, p.`weight` ';
                        // price slider is not filter dependent
                        $sql_query['from'] = '
                        FROM ' . $catRestrictionDerivedTable . ' p';
                        $sql_query['where'] = 'WHERE 1';
                        break;
                    case 'condition':
                        $sql_query['select'] = 'SELECT DISTINCT p.`id_product`, product_shop.`condition` ';
                        $sql_query['from'] = '
                        FROM ' . $catRestrictionDerivedTable . ' p';
                        $sql_query['where'] = 'WHERE 1';
                        $sql_query['from'] .= Shop::addSqlAssociation('product', 'p');
                        break;
                    case 'quantity':
                        $sql_query['select'] = 'SELECT DISTINCT p.`id_product`, sa.`quantity`, sa.`out_of_stock` ';

                        $sql_query['from'] = '
                        FROM ' . $catRestrictionDerivedTable . ' p';

                        $sql_query['join'] .= 'LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` sa
                            ON (sa.id_product = p.id_product AND sa.id_product_attribute=0 ' . StockAvailable::addSqlShopRestriction(
                                null,
                                null,
                                'sa'
                            ) . ') ';
                        $sql_query['where'] = 'WHERE 1';
                        break;

                    case 'manufacturer':
                        $sql_query['select'] = 'SELECT COUNT(DISTINCT p.id_product) nbr, m.id_manufacturer, m.name ';
                        $sql_query['from'] = '
                        FROM ' . $catRestrictionDerivedTable . ' p
                        INNER JOIN ' . _DB_PREFIX_ . 'manufacturer m ON (m.id_manufacturer = p.id_manufacturer) ';
                        $sql_query['where'] = 'WHERE 1';
                        $sql_query['group'] = ' GROUP BY p.id_manufacturer ORDER BY m.name';
                        break;
                    case 'id_attribute_group':// attribute group
                        $sql_query['select'] = '
                        SELECT COUNT(DISTINCT lpa.id_product) nbr, lpa.id_attribute_group,
                        a.color, al.name attribute_name, agl.public_name attribute_group_name , lpa.id_attribute, ag.is_color_group,
                        liagl.url_name name_url_name, liagl.meta_title name_meta_title, lial.url_name value_url_name, lial.meta_title value_meta_title';
                        $sql_query['from'] = '
                        FROM ' . _DB_PREFIX_ . 'layered_product_attribute lpa
                        INNER JOIN ' . _DB_PREFIX_ . 'attribute a
                        ON a.id_attribute = lpa.id_attribute
                        INNER JOIN ' . _DB_PREFIX_ . 'attribute_lang al
                        ON al.id_attribute = a.id_attribute
                        AND al.id_lang = ' . (int)$id_lang . '
                        INNER JOIN ' . $catRestrictionDerivedTable . ' p
                        ON p.id_product = lpa.id_product
                        INNER JOIN ' . _DB_PREFIX_ . 'attribute_group ag
                        ON ag.id_attribute_group = lpa.id_attribute_group
                        INNER JOIN ' . _DB_PREFIX_ . 'attribute_group_lang agl
                        ON agl.id_attribute_group = lpa.id_attribute_group
                        AND agl.id_lang = ' . (int)$id_lang . '
                        LEFT JOIN ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value liagl
                        ON (liagl.id_attribute_group = lpa.id_attribute_group AND liagl.id_lang = ' . (int)$id_lang . ')
                        LEFT JOIN ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value lial
                        ON (lial.id_attribute = lpa.id_attribute AND lial.id_lang = ' . (int)$id_lang . ') ';

                        $sql_query['where'] = 'WHERE lpa.id_attribute_group = ' . (int)$filter['id_value'];
                        $sql_query['where'] .= ' AND lpa.`id_shop` = ' . (int)$context->shop->id;
                        $sql_query['group'] = '
                        GROUP BY lpa.id_attribute
                        ORDER BY ag.`position` ASC, a.`position` ASC';
                        break;

                    case 'id_feature':

                        $id_lang = (int)$id_lang;

                        $sql_query['select'] = 'SELECT fl.name feature_name, fp.id_feature, fv.id_feature_value, fvl.value,
                        COUNT(DISTINCT p.id_product) nbr,
                        lifl.url_name name_url_name, lifl.meta_title name_meta_title, lifvl.url_name value_url_name, lifvl.meta_title value_meta_title ';
                        $sql_query['from'] = '
                        FROM ' . _DB_PREFIX_ . 'feature_product fp
                        INNER JOIN ' . $catRestrictionDerivedTable . ' p
                        ON p.id_product = fp.id_product
                        LEFT JOIN ' . _DB_PREFIX_ . 'feature_lang fl ON (fl.id_feature = fp.id_feature AND fl.id_lang = ' . $id_lang . ')
                        INNER JOIN ' . _DB_PREFIX_ . 'feature_value fv ON (fv.id_feature_value = fp.id_feature_value AND (fv.custom IS NULL OR fv.custom = 0))
                        LEFT JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fvl.id_feature_value = fp.id_feature_value AND fvl.id_lang = ' . $id_lang . ')
                        LEFT JOIN ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value lifl
                        ON (lifl.id_feature = fp.id_feature AND lifl.id_lang = ' . $id_lang . ')
                        LEFT JOIN ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value lifvl
                        ON (lifvl.id_feature_value = fp.id_feature_value AND lifvl.id_lang = ' . $id_lang . ') ';
                        $sql_query['where'] = 'WHERE fp.id_feature = ' . (int)$filter['id_value'];
                        $sql_query['group'] = 'GROUP BY fv.id_feature_value ';
                        break;

                    case 'category':
                        if (Group::isFeatureActive()) {
                            $this->user_groups = ($this->context->customer->isLogged() ? $this->context->customer->getGroups() : array(
                                Configuration::get(
                                    'PS_UNIDENTIFIED_GROUP'
                                )
                            ));
                        }

                        $depth = Configuration::get('PS_LAYERED_FILTER_CATEGORY_DEPTH');
                        if ($depth === false) {
                            $depth = 1;
                        }

                        $sql_query['select'] = '
                        SELECT c.id_category, c.id_parent, cl.name, (SELECT count(DISTINCT p.id_product) # ';
                        $sql_query['from'] = '
                        FROM ' . _DB_PREFIX_ . 'category_product cp
                        LEFT JOIN ' . _DB_PREFIX_ . 'product p ON (p.id_product = cp.id_product) ';
                        $sql_query['where'] = '
                        WHERE cp.id_category = c.id_category
                        AND ' . $alias . '.active = 1 AND ' . $alias . '.`visibility` IN ("both", "catalog")';
                        $sql_query['group'] = ') count_products
                        FROM ' . _DB_PREFIX_ . 'category c
                        LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cl.id_category = c.id_category AND cl.`id_shop` = ' . (int)Context::getContext()->shop->id . ' and cl.id_lang = ' . (int)$id_lang . ') ';

                        if (Group::isFeatureActive()) {
                            $sql_query['group'] .= 'RIGHT JOIN ' . _DB_PREFIX_ . 'category_group cg ON (cg.id_category = c.id_category AND cg.`id_group` IN (' . implode(
                                    ', ',
                                    $this->user_groups
                                ) . ')) ';
                        }

                        $sql_query['group'] .= 'WHERE c.nleft > ' . (int)$parent->nleft . '
                        AND c.nright < ' . (int)$parent->nright . '
                        ' . ($depth ? 'AND c.level_depth <= ' . ($parent->level_depth + (int)$depth) : '') . '
                        AND c.active = 1
                        GROUP BY c.id_category ORDER BY c.nleft, c.position';

                        $sql_query['from'] .= Shop::addSqlAssociation('product', 'p');
                }

                /*
                 * Loop over the filters again to add their restricting clauses to the sql
                 * query being built.
                 */

                foreach ($filters as $filter_tmp) {
                    $method_name = 'get' . ucfirst($filter_tmp['type']) . 'FilterSubQuery';
                    if (method_exists('Ps_Facetedsearch', $method_name)) {
                        $no_subquery_necessary = ($filter['type'] == $filter_tmp['type'] && $filter['id_value'] == $filter_tmp['id_value'] && ($filter['id_value'] || $filter['type'] === 'category' || $filter['type'] === 'condition' || $filter['type'] === 'quantity'));

                        if ($no_subquery_necessary) {
                            // Do not apply the same filter twice, i.e. when the primary filter
                            // and the sub filter have the same type and same id_value.
                            $sub_query_filter = array();
                        } else {
                            // The next part is hard to follow, but here's what I think this
                            // bit of code does:

                            // It checks whether some filters in the current facet
                            // (our current iterator, $filter_tmp), which
                            // is part of the "template" for this category, were selected by the
                            // user.

                            // If so, it formats the current facet
                            // in yet another strange way that is appropriate
                            // for calling get***FilterSubQuery.

                            // For instance, if inside $selected_filters I have:

                            // [id_attribute_group] => Array
                            //   (
                            //      [8] => 3_8
                            //      [11] => 3_11
                            //   )

                            // And $filter_tmp is:
                            // Array
                            // (
                            //   [type] => id_attribute_group
                            //   [id_value] => 3
                            //   [filter_show_limit] => 0
                            //   [filter_type] => 0
                            //  )

                            // Then $selected_filters_cleaned will be:
                            // Array
                            // (
                            //   [0] => 8
                            //   [1] => 11
                            // )

                            // The strategy employed is different whether we're dealing with
                            // a facet with an "id_value" (this is the most complex case involving
                            // the usual underscore-encoded values deserialization witchcraft)
                            // such as "id_attribute_group" or with a facet without id_value.
                            // In the latter case we're in luck because we can just use the
                            // facet in $selected_filters directly.

                            if (!is_null($filter_tmp['id_value'])) {
                                $selected_filters_cleaned = $this->cleanFilterByIdValue(
                                    @$selected_filters[$filter_tmp['type']],
                                    $filter_tmp['id_value']
                                );
                            } else {
                                $selected_filters_cleaned = @$selected_filters[$filter_tmp['type']];
                            }
                            $ignore_join = ($filter['type'] == $filter_tmp['type']);
                            // Prepare the new bits of SQL query.
                            // $ignore_join is set to true when the sub-facet
                            // is of the same "type" as the main facet. This way
                            // the method ($method_name) knows that the tables it needs are already
                            // there and don't need to be joined again.
                            $sub_query_filter = self::$method_name(
                                $selected_filters_cleaned,
                                $ignore_join
                            );
                        }
                        // Now we "merge" the query from the subfilter with the main query
                        foreach ($sub_query_filter as $key => $value) {
                            $sql_query[$key] .= $value;
                        }
                    }
                }

                $products = false;
                if (!empty($sql_query['from'])) {
                    $assembled_sql_query = implode(
                        "\n",
                        array(
                            $sql_query['select'],
                            $sql_query['from'],
                            $sql_query['join'],
                            $sql_query['where'],
                            $sql_query['group'],
                        )
                    );
                    $products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($assembled_sql_query);
                }

                // price & weight have slidebar, so it's ok to not complete recompute the product list
                if (!empty($selected_filters['price']) && $filter['type'] != 'price' && $filter['type'] != 'weight') {
                    $products = self::filterProductsByPrice(@$selected_filters['price'], $products);
                }
                $productCache[$cacheKey] = $products;
            }

            switch ($filter['type']) {
                case 'price':
                    if ($this->showPriceFilter()) {
                        $price_array = array(
                            'type_lite' => 'price',
                            'type' => 'price',
                            'id_key' => 0,
                            'name' => $this->trans('Price', array(), 'Modules.Facetedsearch.Shop'),
                            'slider' => true,
                            'max' => '0',
                            'min' => null,
                            'unit' => $currency->sign,
                            'format' => $currency->format,
                            'filter_show_limit' => $filter['filter_show_limit'],
                            'filter_type' => $filter['filter_type'],
                            'list_of_values' => array(),
                        );
                        if ($compute_range_filters && isset($products) && $products) {
                            $rangeAggregator = new Ps_FacetedsearchRangeAggregator();
                            $aggregatedRanges = $rangeAggregator->aggregateRanges(
                                $products,
                                'price_min',
                                'price_max'
                            );
                            $price_array['min'] = $aggregatedRanges['min'];
                            $price_array['max'] = $aggregatedRanges['max'];

                            $mergedRanges = $rangeAggregator->mergeRanges(
                                $aggregatedRanges['ranges'],
                                10
                            );

                            $price_array['list_of_values'] = array_map(function (array $range) {
                                return array(
                                    0 => $range['min'],
                                    1 => $range['max'],
                                    'nbr' => $range['count'],
                                );
                            }, $mergedRanges);

                            $price_array['values'] = array($price_array['min'], $price_array['max']);
                        }
                        $filter_blocks[] = $price_array;
                    }
                    break;

                case 'weight':
                    $weight_array = array(
                        'type_lite' => 'weight',
                        'type' => 'weight',
                        'id_key' => 0,
                        'name' => $this->trans('Weight', array(), 'Modules.Facetedsearch.Shop'),
                        'slider' => true,
                        'max' => '0',
                        'min' => null,
                        'unit' => Configuration::get('PS_WEIGHT_UNIT'),
                        'format' => 5, // Ex: xxxxx kg
                        'filter_show_limit' => $filter['filter_show_limit'],
                        'filter_type' => $filter['filter_type'],
                        'list_of_values' => array(),
                    );
                    if ($compute_range_filters && isset($products) && $products) {
                        $rangeAggregator = new Ps_FacetedsearchRangeAggregator();
                        $aggregatedRanges = $rangeAggregator->getRangesFromList(
                            $products,
                            'weight'
                        );
                        $weight_array['min'] = $aggregatedRanges['min'];
                        $weight_array['max'] = $aggregatedRanges['max'];

                        $mergedRanges = $rangeAggregator->mergeRanges(
                            $aggregatedRanges['ranges'],
                            10
                        );

                        $weight_array['list_of_values'] = array_map(function (array $range) {
                            return array(
                                0 => $range['min'],
                                1 => $range['max'],
                                'nbr' => $range['count'],
                            );
                        }, $mergedRanges);

                        if (empty($weight_array['list_of_values']) && isset($selected_filters['weight'])) {
                            // in case we don't have a list of values,
                            // add the original one.
                            // This may happen when e.g. all products
                            // weigh 0.
                            $weight_array['list_of_values'] = array(
                                array(
                                    0 => $selected_filters['weight'][0],
                                    1 => $selected_filters['weight'][1],
                                    'nbr' => count($products),
                                ),
                            );
                        }

                        $weight_array['values'] = array($weight_array['min'], $weight_array['max']);
                    }
                    $filter_blocks[] = $weight_array;
                    break;

                case 'condition':
                    $condition_array = array(
                        'new' => array('name' => $this->trans('New', array(), 'Modules.Facetedsearch.Shop'), 'nbr' => 0),
                        'used' => array('name' => $this->trans('Used', array(), 'Modules.Facetedsearch.Shop'), 'nbr' => 0),
                        'refurbished' => array('name' => $this->trans('Refurbished', array(), 'Modules.Facetedsearch.Shop'),
                            'nbr' => 0,),
                    );
                    if (isset($products) && $products) {
                        foreach ($products as $product) {
                            if (isset($selected_filters['condition']) && in_array($product['condition'], $selected_filters['condition'])) {
                                $condition_array[$product['condition']]['checked'] = true;
                            }
                        }
                    }
                    foreach ($condition_array as $key => $condition) {
                        if (isset($selected_filters['condition']) && in_array($key, $selected_filters['condition'])) {
                            $condition_array[$key]['checked'] = true;
                        }
                    }
                    if (isset($products) && $products) {
                        foreach ($products as $product) {
                            if (isset($condition_array[$product['condition']])) {
                                ++$condition_array[$product['condition']]['nbr'];
                            }
                        }
                    }
                    $filter_blocks[] = array(
                        'type_lite' => 'condition',
                        'type' => 'condition',
                        'id_key' => 0,
                        'name' => $this->trans('Condition', array(), 'Modules.Facetedsearch.Shop'),
                        'values' => $condition_array,
                        'filter_show_limit' => $filter['filter_show_limit'],
                        'filter_type' => $filter['filter_type'],
                    );
                    break;

                case 'quantity':
                    $quantity_array = array(
                        0 => array('name' => $this->trans('Not available', array(), 'Modules.Facetedsearch.Shop'), 'nbr' => 0),
                        1 => array('name' => $this->trans('In stock', array(), 'Modules.Facetedsearch.Shop'), 'nbr' => 0),
                    );
                    foreach ($quantity_array as $key => $quantity) {
                        if (isset($selected_filters['quantity']) && in_array($key, $selected_filters['quantity'])) {
                            $quantity_array[$key]['checked'] = true;
                        }
                    }
                    if (isset($products) && $products) {
                        foreach ($products as $product) {
                            //If oosp move all not available quantity to available quantity
                            if ((int)$product['quantity'] > 0 || Product::isAvailableWhenOutOfStock($product['out_of_stock'])) {
                                ++$quantity_array[1]['nbr'];
                            } else {
                                ++$quantity_array[0]['nbr'];
                            }
                        }
                    }

                    $filter_blocks[] = array(
                        'type_lite' => 'quantity',
                        'type' => 'quantity',
                        'id_key' => 0,
                        'name' => $this->trans('Availability', array(), 'Modules.Facetedsearch.Shop'),
                        'values' => $quantity_array,
                        'filter_show_limit' => $filter['filter_show_limit'],
                        'filter_type' => $filter['filter_type'],
                    );

                    break;

                case 'manufacturer':
                    if (isset($products) && $products) {
                        $manufaturers_array = array();
                        foreach ($products as $manufacturer) {
                            if (!isset($manufaturers_array[$manufacturer['id_manufacturer']])) {
                                $manufaturers_array[$manufacturer['id_manufacturer']] = array('name' => $manufacturer['name'], 'nbr' => $manufacturer['nbr']);
                            }
                            if (isset($selected_filters['manufacturer']) && in_array((int)$manufacturer['id_manufacturer'], $selected_filters['manufacturer'])) {
                                $manufaturers_array[$manufacturer['id_manufacturer']]['checked'] = true;
                            }
                        }
                        $filter_blocks[] = array(
                            'type_lite' => 'manufacturer',
                            'type' => 'manufacturer',
                            'id_key' => 0,
                            'name' => $this->trans('Brand', array(), 'Modules.Facetedsearch.Shop'),
                            'values' => $manufaturers_array,
                            'filter_show_limit' => $filter['filter_show_limit'],
                            'filter_type' => $filter['filter_type'],
                        );
                    }
                    break;

                case 'id_attribute_group':
                    $attributes_array = array();
                    if (isset($products) && $products) {
                        foreach ($products as $attributes) {
                            if (!isset($attributes_array[$attributes['id_attribute_group']])) {
                                $attributes_array[$attributes['id_attribute_group']] = array(
                                    'type_lite' => 'id_attribute_group',
                                    'type' => 'id_attribute_group',
                                    'id_key' => (int)$attributes['id_attribute_group'],
                                    'name' => $attributes['attribute_group_name'],
                                    'is_color_group' => (bool)$attributes['is_color_group'],
                                    'values' => array(),
                                    'url_name' => $attributes['name_url_name'],
                                    'meta_title' => $attributes['name_meta_title'],
                                    'filter_show_limit' => $filter['filter_show_limit'],
                                    'filter_type' => $filter['filter_type'],
                                );
                            }

                            if (!isset($attributes_array[$attributes['id_attribute_group']]['values'][$attributes['id_attribute']])) {
                                $attributes_array[$attributes['id_attribute_group']]['values'][$attributes['id_attribute']] = array(
                                    'color' => $attributes['color'],
                                    'name' => $attributes['attribute_name'],
                                    'nbr' => (int)$attributes['nbr'],
                                    'url_name' => $attributes['value_url_name'],
                                    'meta_title' => $attributes['value_meta_title'],
                                );
                            }

                            if (isset($selected_filters['id_attribute_group'][$attributes['id_attribute']])) {
                                $attributes_array[$attributes['id_attribute_group']]['values'][$attributes['id_attribute']]['checked'] = true;
                            }
                        }

                        $filter_blocks = array_merge($filter_blocks, $attributes_array);
                    }
                    break;
                case 'id_feature':
                    $feature_array = array();
                    if (isset($products) && $products) {
                        foreach ($products as $feature) {
                            if (!isset($feature_array[$feature['id_feature']])) {
                                $feature_array[$feature['id_feature']] = array(
                                    'type_lite' => 'id_feature',
                                    'type' => 'id_feature',
                                    'id_key' => (int)$feature['id_feature'],
                                    'values' => array(),
                                    'name' => $feature['feature_name'],
                                    'url_name' => $feature['name_url_name'],
                                    'meta_title' => $feature['name_meta_title'],
                                    'filter_show_limit' => $filter['filter_show_limit'],
                                    'filter_type' => $filter['filter_type'],
                                );
                            }

                            if (!isset($feature_array[$feature['id_feature']]['values'][$feature['id_feature_value']])) {
                                $feature_array[$feature['id_feature']]['values'][$feature['id_feature_value']] = array(
                                    'nbr' => (int)$feature['nbr'],
                                    'name' => $feature['value'],
                                    'url_name' => $feature['value_url_name'],
                                    'meta_title' => $feature['value_meta_title'],
                                );
                            }

                            if (isset($selected_filters['id_feature'][$feature['id_feature_value']])) {
                                $feature_array[$feature['id_feature']]['values'][$feature['id_feature_value']]['checked'] = true;
                            }
                        }

                        //Natural sort
                        foreach ($feature_array as $key => $value) {
                            $temp = array();
                            foreach ($feature_array[$key]['values'] as $keyint => $valueint) {
                                $temp[$keyint] = $valueint['name'];
                            }

                            natcasesort($temp);
                            $temp2 = array();

                            foreach ($temp as $keytemp => $valuetemp) {
                                $temp2[$keytemp] = $feature_array[$key]['values'][$keytemp];
                            }

                            $feature_array[$key]['values'] = $temp2;
                        }

                        $filter_blocks = array_merge($filter_blocks, $feature_array);
                    }
                    break;

                case 'category':
                    $tmp_array = array();
                    if (isset($products) && $products) {
                        $categories_with_products_count = 0;
                        foreach ($products as $category) {
                            $tmp_array[$category['id_category']] = array(
                                'name' => $category['name'],
                                'nbr' => (int)$category['count_products'],
                            );

                            if ((int)$category['count_products']) {
                                ++$categories_with_products_count;
                            }

                            if (isset($selected_filters['category']) && in_array($category['id_category'], $selected_filters['category'])) {
                                $tmp_array[$category['id_category']]['checked'] = true;
                            }
                        }
                        if ($categories_with_products_count) {
                            $filter_blocks[] = array(
                                'type_lite' => 'category',
                                'type' => 'category',
                                'id_key' => 0, 'name' => $this->trans('Categories', array(), 'Modules.Facetedsearch.Shop'),
                                'values' => $tmp_array,
                                'filter_show_limit' => $filter['filter_show_limit'],
                                'filter_type' => $filter['filter_type'],
                            );
                        }
                    }
                    break;
            }
        }

        $latest_selected_filters = $selected_filters;

        return array(
            'filters' => $filter_blocks,
        );
    }

    public function cleanFilterByIdValue($attributes, $id_value)
    {
        $selected_filters = array();
        if (is_array($attributes)) {
            foreach ($attributes as $attribute) {
                $attribute_data = explode('_', $attribute);
                if ($attribute_data[0] == $id_value) {
                    $selected_filters[] = $attribute_data[1];
                }
            }
        }

        return $selected_filters;
    }

    private static function getPriceFilterSubQuery($filter_value, $ignore_join = false)
    {
        $id_currency = (int) Context::getContext()->currency->id;

        if ($ignore_join && $filter_value) {
            return array('where' => ' AND psi.price_min >= '.(int) $filter_value[0].' AND psi.price_max <= '.(int) $filter_value[1]);
        } elseif ($filter_value) {
            $price_filter_query = '
			INNER JOIN `'._DB_PREFIX_.'layered_price_index` psi ON (psi.id_product = p.id_product AND psi.id_currency = '.(int) $id_currency.'
			AND psi.price_min <= '.(int) $filter_value[1].' AND psi.price_max >= '.(int) $filter_value[0].' AND psi.id_shop='.(int) Context::getContext()->shop->id.') ';

            return array('join' => $price_filter_query);
        }

        return array();
    }

    private static function filterProductsByPrice($filter_value, $product_collection)
    {
        static $ps_layered_filter_price_usetax = null;
        static $ps_layered_filter_price_rounding = null;

        if (empty($filter_value)) {
            return $product_collection;
        }

        if ($ps_layered_filter_price_usetax === null) {
            $ps_layered_filter_price_usetax = Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX');
        }

        if ($ps_layered_filter_price_rounding === null) {
            $ps_layered_filter_price_rounding = Configuration::get('PS_LAYERED_FILTER_PRICE_ROUNDING');
        }

        foreach ($product_collection as $key => $product) {
            if (isset($filter_value) && $filter_value && isset($product['price_min']) && isset($product['id_product'])
                && (($product['price_min'] < (int) $filter_value[0] && $product['price_max'] > (int) $filter_value[0])
                    || ($product['price_max'] > (int) $filter_value[1] && $product['price_min'] < (int) $filter_value[1]))) {
                $price = Product::getPriceStatic($product['id_product'], $ps_layered_filter_price_usetax);
                if ($ps_layered_filter_price_rounding) {
                    $price = (int) $price;
                }
                if ($price < $filter_value[0] || $price > $filter_value[1]) {
                    unset($product_collection[$key]);
                }
            }
        }

        return $product_collection;
    }

    private static function getWeightFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (isset($filter_value) && $filter_value) {
            if ($filter_value[0] != 0 || $filter_value[1] != 0) {
                return array('where' => ' AND p.`weight` BETWEEN '.(float) ($filter_value[0] - 0.001).' AND '.(float) ($filter_value[1] + 0.001).' ');
            }
        }

        return array();
    }

    private static function getId_featureFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value)) {
            return array();
        }
        $query_filters = ' AND EXISTS (SELECT * FROM '._DB_PREFIX_.'feature_product fp WHERE fp.id_product = p.id_product AND ';
        foreach ($filter_value as $filter_val) {
            $query_filters .= 'fp.`id_feature_value` = '.(int) $filter_val.' OR ';
        }
        $query_filters = rtrim($query_filters, 'OR ').') ';

        return array('where' => $query_filters);
    }
    private static function getId_attribute_groupFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value)) {
            return array();
        }
        $query_filters = '
		AND EXISTS (SELECT *
		FROM `'._DB_PREFIX_.'product_attribute_combination` pac
		LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa ON (pa.`id_product_attribute` = pac.`id_product_attribute`)
		WHERE pa.id_product = p.id_product AND ';

        foreach ($filter_value as $filter_val) {
            $query_filters .= 'pac.`id_attribute` = '.(int) $filter_val.' OR ';
        }
        $query_filters = rtrim($query_filters, 'OR ').') ';

        return array('where' => $query_filters);
    }

    private static function getCategoryFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value)) {
            return array();
        }
        $query_filters_where = ' AND EXISTS (SELECT * FROM '._DB_PREFIX_.'category_product cp WHERE id_product = p.id_product AND ';
        foreach ($filter_value as $id_category) {
            $query_filters_where .= 'cp.`id_category` = '.(int) $id_category.' OR ';
        }
        $query_filters_where = rtrim($query_filters_where, 'OR ').') ';

        return array('where' => $query_filters_where);
    }

    private static function getQuantityFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value) || count($filter_value) == 2) {
            return array();
        }

        $query_filters_join = '';

        $query_filters = ' AND sav.quantity '.(!$filter_value[0] ? '<=' : '>').' 0 ';
        $query_filters_join = 'LEFT JOIN `'._DB_PREFIX_.'stock_available` sav ON (sav.id_product = p.id_product AND sav.id_shop = '.(int) Context::getContext()->shop->id.') ';

        return array('where' => $query_filters, 'join' => $query_filters_join);
    }

    private static function getManufacturerFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value)) {
            $query_filters = '';
        } else {
            array_walk($filter_value, create_function('&$id_manufacturer', '$id_manufacturer = (int)$id_manufacturer;'));
            $query_filters = ' AND p.id_manufacturer IN ('.implode($filter_value, ',').')';
        }
        if ($ignore_join) {
            return array('where' => $query_filters);
        } else {
            return array('where' => $query_filters, 'join' => 'LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.id_manufacturer = p.id_manufacturer) ');
        }
    }

    private static function getConditionFilterSubQuery($filter_value, $ignore_join = false)
    {
        if (empty($filter_value) || count($filter_value) == 3) {
            return array();
        }

        $query_filters = ' AND p.condition IN (';

        foreach ($filter_value as $cond) {
            $query_filters .= '\''.Db::getInstance()->escape($cond).'\',';
        }
        $query_filters = rtrim($query_filters, ',').') ';

        return array('where' => $query_filters);
    }

    public function rebuildLayeredStructure()
    {
        @set_time_limit(0);

        /* Set memory limit to 128M only if current is lower */
        $memory_limit = Tools::getMemoryLimit();
        if ($memory_limit != -1 && $memory_limit < 128*1024*1024) {
            @ini_set('memory_limit', '128M');
        }

        /* Delete and re-create the layered categories table */
        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_category');
        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'layered_category` (
		`id_layered_category` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
		`id_shop` INT(11) UNSIGNED NOT NULL,
		`id_category` INT(10) UNSIGNED NOT NULL,
		`id_value` INT(10) UNSIGNED NULL DEFAULT \'0\',
		`type` ENUM(\'category\',\'id_feature\',\'id_attribute_group\',\'quantity\',\'condition\',\'manufacturer\',\'weight\',\'price\') NOT NULL,
		`position` INT(10) UNSIGNED NOT NULL,
		`filter_type` int(10) UNSIGNED NOT NULL DEFAULT 0,
		`filter_show_limit` int(10) UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY (`id_layered_category`),
		KEY `id_category` (`id_category`,`type`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;'); /* MyISAM + latin1 = Smaller/faster */

        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'layered_filter` (
		`id_layered_filter` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`name` VARCHAR(64) NOT NULL,
		`filters` LONGTEXT NULL,
		`n_categories` INT(10) UNSIGNED NOT NULL,
		`date_add` DATETIME NOT NULL
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');

        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'layered_filter_shop` (
		`id_layered_filter` INT(10) UNSIGNED NOT NULL,
		`id_shop` INT(11) UNSIGNED NOT NULL,
		PRIMARY KEY (`id_layered_filter`, `id_shop`),
		KEY `id_shop` (`id_shop`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;');
    }

    public function rebuildLayeredCache($products_ids = array(), $categories_ids = array(), $rebuildLayeredCategories = true)
    {
        @set_time_limit(0);

        $filter_data = array('categories' => array());

        /* Set memory limit to 128M only if current is lower */
        $memory_limit = Tools::getMemoryLimit();
        if ($memory_limit != -1 && $memory_limit < 128*1024*1024) {
            @ini_set('memory_limit', '128M');
        }

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $n_categories = array();
        $done_categories = array();

        $alias = 'product_shop';
        $join_product = Shop::addSqlAssociation('product', 'p');
        $join_product_attribute = Shop::addSqlAssociation('product_attribute', 'pa');

        $attribute_groups = self::query('
		SELECT a.id_attribute, a.id_attribute_group
		FROM '._DB_PREFIX_.'attribute a
		LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.id_attribute = a.id_attribute)
		LEFT JOIN '._DB_PREFIX_.'product_attribute pa ON (pa.id_product_attribute = pac.id_product_attribute)
		LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = pa.id_product)
		'.$join_product.$join_product_attribute.'
		LEFT JOIN '._DB_PREFIX_.'category_product cp ON (cp.id_product = p.id_product)
		LEFT JOIN '._DB_PREFIX_.'category c ON (c.id_category = cp.id_category)
		WHERE c.active = 1'.
            (count($categories_ids) ? ' AND cp.id_category IN ('.implode(',', array_map('intval', $categories_ids)).')' : '').'
		AND '.$alias.'.active = 1 AND '.$alias.'.`visibility` IN ("both", "catalog")
		'.(count($products_ids) ? 'AND p.id_product IN ('.implode(',', array_map('intval', $products_ids)).')' : ''));

        $attribute_groups_by_id = array();
        while ($row = $db->nextRow($attribute_groups)) {
            $attribute_groups_by_id[(int) $row['id_attribute']] = (int) $row['id_attribute_group'];
        }

        $features = self::query('
		SELECT fv.id_feature_value, fv.id_feature
		FROM '._DB_PREFIX_.'feature_value fv
		LEFT JOIN '._DB_PREFIX_.'feature_product fp ON (fp.id_feature_value = fv.id_feature_value)
		LEFT JOIN '._DB_PREFIX_.'product p ON (p.id_product = fp.id_product)
		'.$join_product.'
		LEFT JOIN '._DB_PREFIX_.'category_product cp ON (cp.id_product = p.id_product)
		LEFT JOIN '._DB_PREFIX_.'category c ON (c.id_category = cp.id_category)
		WHERE (fv.custom IS NULL OR fv.custom = 0) AND c.active = 1'.(count($categories_ids) ? ' AND cp.id_category IN ('.implode(',', array_map('intval', $categories_ids)).')' : '').'
		AND '.$alias.'.active = 1 AND '.$alias.'.`visibility` IN ("both", "catalog") '.(count($products_ids) ? 'AND p.id_product IN ('.implode(',', array_map('intval', $products_ids)).')' : ''));

        $features_by_id = array();
        while ($row = $db->nextRow($features)) {
            $features_by_id[(int) $row['id_feature_value']] = (int) $row['id_feature'];
        }

        $result = self::query('
		SELECT p.id_product,
		GROUP_CONCAT(DISTINCT fv.id_feature_value) features,
		GROUP_CONCAT(DISTINCT cp.id_category) categories,
		GROUP_CONCAT(DISTINCT pac.id_attribute) attributes
		FROM '._DB_PREFIX_.'product p
		LEFT JOIN '._DB_PREFIX_.'category_product cp ON (cp.id_product = p.id_product)
		LEFT JOIN '._DB_PREFIX_.'category c ON (c.id_category = cp.id_category)
		LEFT JOIN '._DB_PREFIX_.'feature_product fp ON (fp.id_product = p.id_product)
		LEFT JOIN '._DB_PREFIX_.'feature_value fv ON (fv.id_feature_value = fp.id_feature_value)
		LEFT JOIN '._DB_PREFIX_.'product_attribute pa ON (pa.id_product = p.id_product)
		'.$join_product.$join_product_attribute.'
		LEFT JOIN '._DB_PREFIX_.'product_attribute_combination pac ON (pac.id_product_attribute = pa.id_product_attribute)
		WHERE c.active = 1'.(count($categories_ids) ? ' AND cp.id_category IN ('.implode(',', array_map('intval', $categories_ids)).')' : '').'
		AND '.$alias.'.active = 1 AND '.$alias.'.`visibility` IN ("both", "catalog")
		'.(count($products_ids) ? 'AND p.id_product IN ('.implode(',', array_map('intval', $products_ids)).')' : '').
            ' AND (fv.custom IS NULL OR fv.custom = 0)
		GROUP BY p.id_product');

        $shop_list = Shop::getShops(false, null, true);

        $to_insert = false;
        while ($product = $db->nextRow($result)) {
            $a = $c = $f = array();
            if (!empty($product['attributes'])) {
                $a = array_flip(explode(',', $product['attributes']));
            }
            if (!empty($product['categories'])) {
                $c = array_flip(explode(',', $product['categories']));
            }
            if (!empty($product['features'])) {
                $f = array_flip(explode(',', $product['features']));
            }

            $filter_data['shop_list'] = $shop_list;

            foreach ($c as $id_category => $category) {
                if (!in_array($id_category, $filter_data['categories'])) {
                    $filter_data['categories'][] = $id_category;
                }

                if (!isset($n_categories[(int) $id_category])) {
                    $n_categories[(int) $id_category] = 1;
                }
                if (!isset($done_categories[(int) $id_category]['cat'])) {
                    $filter_data['layered_selection_subcategories'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['cat'] = true;
                    $to_insert = true;
                }
                if (is_array($attribute_groups_by_id) && count($attribute_groups_by_id) > 0) {
                    foreach ($a as $k_attribute => $attribute) {
                        if (!isset($done_categories[(int) $id_category]['a'.(int) $attribute_groups_by_id[(int) $k_attribute]])) {
                            $filter_data['layered_selection_ag_'.(int) $attribute_groups_by_id[(int) $k_attribute]] = array('filter_type' => 0, 'filter_show_limit' => 0);
                            $done_categories[(int) $id_category]['a'.(int) $attribute_groups_by_id[(int) $k_attribute]] = true;
                            $to_insert = true;
                        }
                    }
                }
                if (is_array($attribute_groups_by_id) && count($attribute_groups_by_id) > 0) {
                    foreach ($f as $k_feature => $feature) {
                        if (!isset($done_categories[(int) $id_category]['f'.(int) $features_by_id[(int) $k_feature]])) {
                            $filter_data['layered_selection_feat_'.(int) $features_by_id[(int) $k_feature]] = array('filter_type' => 0, 'filter_show_limit' => 0);
                            $done_categories[(int) $id_category]['f'.(int) $features_by_id[(int) $k_feature]] = true;
                            $to_insert = true;
                        }
                    }
                }
                if (!isset($done_categories[(int) $id_category]['q'])) {
                    $filter_data['layered_selection_stock'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['q'] = true;
                    $to_insert = true;
                }
                if (!isset($done_categories[(int) $id_category]['m'])) {
                    $filter_data['layered_selection_manufacturer'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['m'] = true;
                    $to_insert = true;
                }
                if (!isset($done_categories[(int) $id_category]['c'])) {
                    $filter_data['layered_selection_condition'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['c'] = true;
                    $to_insert = true;
                }
                if (!isset($done_categories[(int) $id_category]['w'])) {
                    $filter_data['layered_selection_weight_slider'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['w'] = true;
                    $to_insert = true;
                }
                if (!isset($done_categories[(int) $id_category]['p'])) {
                    $filter_data['layered_selection_price_slider'] = array('filter_type' => 0, 'filter_show_limit' => 0);
                    $done_categories[(int) $id_category]['p'] = true;
                    $to_insert = true;
                }
            }
        }
        if ($to_insert) {
            Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'layered_filter(name, filters, n_categories, date_add)
				VALUES (\''.sprintf($this->trans('My template %s', array(), 'Modules.Facetedsearch.Admin'), date('Y-m-d')).'\', \''.pSQL(serialize($filter_data)).'\', '.count($filter_data['categories']).', NOW())');

            $last_id = Db::getInstance()->Insert_ID();
            Db::getInstance()->execute('DELETE FROM '._DB_PREFIX_.'layered_filter_shop WHERE `id_layered_filter` = '.$last_id);
            foreach ($shop_list as $id_shop) {
                Db::getInstance()->execute('INSERT INTO '._DB_PREFIX_.'layered_filter_shop (`id_layered_filter`, `id_shop`)
					VALUES('.$last_id.', '.(int) $id_shop.')');
            }

            if ($rebuildLayeredCategories) {
                $this->buildLayeredCategories();
            }
        }
    }

    public function buildLayeredCategories()
    {
        // Get all filter template
        $res = Db::getInstance()->executeS('SELECT * FROM '._DB_PREFIX_.'layered_filter ORDER BY date_add DESC');
        $categories = array();
        // Remove all from layered_category
        Db::getInstance()->execute('TRUNCATE '._DB_PREFIX_.'layered_category');

        if (!count($res)) { // No filters templates defined, nothing else to do
            return true;
        }

        $sqlInsertPrefix = 'INSERT INTO '._DB_PREFIX_.'layered_category (id_category, id_shop, id_value, type, position, filter_show_limit, filter_type) VALUES ';
        $sqlInsert = '';
        $nbSqlValuesToInsert = 0;
        foreach ($res as $filter_template) {
            $data = Tools::unSerialize($filter_template['filters']);
            foreach ($data['shop_list'] as $id_shop) {
                if (!isset($categories[$id_shop])) {
                    $categories[$id_shop] = array();
                }

                foreach ($data['categories'] as  $id_category) {
                    $n = 0;
                    if (!in_array($id_category, $categories[$id_shop])) {
                        // Last definition, erase previous categories defined

                        $categories[$id_shop][] = $id_category;

                        foreach ($data as $key => $value) {
                            if (substr($key, 0, 17) == 'layered_selection') {
                                $type = $value['filter_type'];
                                $limit = $value['filter_show_limit'];
                                ++$n;

                                if ($key == 'layered_selection_stock') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'quantity\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_subcategories') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'category\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_condition') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'condition\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_weight_slider') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'weight\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_price_slider') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'price\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_manufacturer') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'manufacturer\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif (substr($key, 0, 21) == 'layered_selection_ag_') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', '.(int) str_replace('layered_selection_ag_', '', $key).',
										\'id_attribute_group\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif (substr($key, 0, 23) == 'layered_selection_feat_') {
                                    $sqlInsert .= '('.(int) $id_category.', '.(int) $id_shop.', '.(int) str_replace('layered_selection_feat_', '', $key).',
										\'id_feature\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                }

                                $nbSqlValuesToInsert++;
                                if ($nbSqlValuesToInsert >= 100) {
                                    Db::getInstance()->execute($sqlInsertPrefix.rtrim($sqlInsert, ','));
                                    $sqlInsert = '';
                                    $nbSqlValuesToInsert = 0;
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($nbSqlValuesToInsert) {
            Db::getInstance()->execute($sqlInsertPrefix.rtrim($sqlInsert, ','));
        }
    }

    protected function showPriceFilter()
    {
        return Group::getCurrent()->show_prices;
    }
}

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
use NativeModuleFacetedSearchBundle\Ps_FacetedsearchProductSearchProvider;

require_once implode(DIRECTORY_SEPARATOR, array(
    __DIR__, 'vendor', 'autoload.php',
));

class Ps_Facetedsearch extends Module implements WidgetInterface
{
    private $nbr_products;
    private $ps_layered_full_tree;

    public function __construct()
    {
        $this->name = 'ps_facetedsearch';
        $this->tab = 'front_office_features';
        $this->version = '2.0.0';
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
                $this->rebuildLayeredCache();
            }

            self::installPriceIndexTable();
            $this->installIndexableAttributeTable();
            $this->installProductAttributeTable();
            $this->installProductTable();

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
            Db::getInstance()->execute('
				DELETE FROM '._DB_PREFIX_.'layered_product_attribute
				WHERE id_product = '.(int) $id_product
            );
        }

        Db::getInstance()->execute('
			INSERT INTO `'._DB_PREFIX_.'layered_product_attribute` (`id_attribute`, `id_product`, `id_attribute_group`, `id_shop`)
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

    private static function indexPrices($cursor = 0, $full = false, $ajax = false, $smart = false)
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

        $indexedProducts = 0;
        $length = 100;
        if (function_exists('memory_get_peak_usage')) {
            do {
                $lastCursor = $cursor;
                $cursor = (int) self::indexPricesUnbreakable((int) $cursor, $full, $smart, $length);
                $time_elapsed = microtime(true) - $start_time;
                $indexedProducts += $length;
            } while ($cursor != $lastCursor && Tools::getMemoryLimit() > memory_get_peak_usage() && $time_elapsed < $max_executiontime);
        } else {
            do {
                $lastCursor = $cursor;
                $cursor = (int) self::indexPricesUnbreakable((int) $cursor, $full, $smart, $length);
                $time_elapsed = microtime(true) - $start_time;
                $indexedProducts += $length;
            } while ($cursor != $lastCursor && $time_elapsed < $max_executiontime);
        }
        if (($nb_products > 0 && !$full || $cursor != $lastCursor && $full) && !$ajax) {
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
        if ($ajax && $nb_products > 0 && $cursor != $lastCursor && $full) {
            return '{"cursor": '.$cursor.', "count": '.($indexedProducts).'}';
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

    /**
     * @param $cursor int last indexed id_product
     * @param bool $full
     * @param bool $smart
     * @param int $length nb of products to index
     * @return int
     */
    private static function indexPricesUnbreakable($cursor, $full = false, $smart = false, $length = 100)
    {
        if (is_null($cursor)) {
            $cursor = 0;
        }

        if ($full) {
            $query = '
				SELECT p.`id_product`
				FROM `'._DB_PREFIX_.'product` p
				INNER JOIN `'._DB_PREFIX_.'product_shop` ps
					ON (ps.`id_product` = p.`id_product` AND ps.`active` = 1 AND ps.`visibility` IN ("both", "catalog"))
				WHERE p.id_product>'.(int)$cursor.'
				GROUP BY p.`id_product`
				ORDER BY p.`id_product` LIMIT 0,'.(int) $length;
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

        $lastIdProduct = 0;
        foreach (Db::getInstance()->executeS($query) as $product) {
            self::indexProductPrices((int) $product['id_product'], ($smart && $full));
            $lastIdProduct = $product['id_product'];
        }

        return (int) $lastIdProduct;
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
                $price = Product::priceCalculation($id_shop, (int) $id_product, null, null, null, null,
                    $currency['id_currency'], null, null, false, 6, false, true, true,
                    $specific_price_output, true);

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
                    $price = Product::priceCalculation((($specific_price['id_shop'] == 0) ? null : (int) $specific_price['id_shop']), (int) $id_product,
                        null, (($specific_price['id_country'] == 0) ? null : $specific_price['id_country']), null, null,
                        $currency['id_currency'], (($specific_price['id_group'] == 0) ? null : $specific_price['id_group']),
                        $specific_price['from_quantity'], false, 6, false, true, true, $specific_price_output, true);

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
                    $price = Product::priceCalculation(null, (int) $id_product, null, null, null, null, (int) $currency['id_currency'], (int) $group['id_group'],
                        null, false, 6, false, true, true, $specific_price_output, true);

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
                    Db::getInstance()->execute('
						DELETE FROM '._DB_PREFIX_.'layered_filter
						WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter')
                    );
                    $this->buildLayeredCategories();
                }

                if (Tools::getValue('scope') == 1) {
                    Db::getInstance()->execute('TRUNCATE TABLE '._DB_PREFIX_.'layered_filter');
                    $categories = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
						SELECT id_category
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

                Db::getInstance()->execute('
					DELETE FROM '._DB_PREFIX_.'layered_filter_shop
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
                            Db::getInstance()->execute('
							INSERT INTO '._DB_PREFIX_.'layered_filter_shop (`id_layered_filter`, `id_shop`)
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
            $layered_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
				SELECT filters
				FROM '._DB_PREFIX_.'layered_filter
				WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter')
            );

            if ($layered_values) {
                Db::getInstance()->execute('
					DELETE FROM '._DB_PREFIX_.'layered_filter
					WHERE id_layered_filter = '.(int) Tools::getValue('id_layered_filter').' LIMIT 1'
                );
                $this->buildLayeredCategories();
                $message = $this->displayConfirmation($this->trans('Filter template deleted, categories updated (reverted to default Filter template).', array(), 'Modules.Facetedsearch.Admin'));
            } else {
                $message = $this->displayError($this->trans('Filter template not found', array(), 'Modules.Facetedsearch.Admin'));
            }
        }

        $category_box = array();
        $attribute_groups = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT ag.id_attribute_group, ag.is_color_group, agl.name, COUNT(DISTINCT(a.id_attribute)) n
			FROM '._DB_PREFIX_.'attribute_group ag
			LEFT JOIN '._DB_PREFIX_.'attribute_group_lang agl ON (agl.id_attribute_group = ag.id_attribute_group)
			LEFT JOIN '._DB_PREFIX_.'attribute a ON (a.id_attribute_group = ag.id_attribute_group)
			WHERE agl.id_lang = '.(int) $cookie->id_lang.'
			GROUP BY ag.id_attribute_group'
        );

        $features = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT fl.id_feature, fl.name, COUNT(DISTINCT(fv.id_feature_value)) n
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
                    $root_category, array(), 'categoryBox'));
            }

            return $this->display(__FILE__, 'views/templates/admin/add.tpl');
        } elseif (Tools::getValue('edit_filters_template')) {
            $template = Db::getInstance()->getRow('
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
                    $root_category, $filters['categories'], 'categoryBox'));
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

    private static function query($sql_query)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->query($sql_query);
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

    public function installProductTable()
    {
        @set_time_limit(0);

        Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'layered_product');
        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'layered_product` (
		`id_layered_product` int(10) unsigned NOT NULL auto_increment,
		`id_product` int(10) unsigned NOT NULL,
		`id_shop` INT(11) UNSIGNED NOT NULL DEFAULT \'1\',
        `id_product_attribute` int(10) unsigned NOT NULL,
        `id_attribute` int(10) unsigned NOT NULL,
        `id_feature` int(10) unsigned NOT NULL,
        `id_feature_value` int(10) unsigned NOT NULL,
        `id_category` INT(10) UNSIGNED NOT NULL,
        `quantity` int(10) unsigned NOT NULL DEFAULT 0,
        `id_manufacturer` int(10) unsigned DEFAULT NULL,
        `condition` ENUM(\'new\', \'used\', \'refurbished\') NOT NULL DEFAULT \'new\',
        `weight` DECIMAL(20,6) NOT NULL DEFAULT \'0\',
        `price` DECIMAL(20, 6) NOT NULL,
        `has_specific_price` tinyint(1) NOT NULL DEFAULT 0,
        `nleft` int(10) unsigned NOT NULL DEFAULT \'0\',
        `nright` int(10) unsigned NOT NULL DEFAULT \'0\',
        `position` int(10) unsigned NOT NULL DEFAULT \'0\',
        `id_currency` int(10) unsigned NOT NULL DEFAULT \'0\',
    	`out_of_stock` tinyint(1) NOT NULL DEFAULT 0,
    	`id_group` int(10) unsigned NOT NULL,
    	`level_depth` tinyint(3) unsigned NOT NULL DEFAULT \'0\'
		PRIMARY KEY (`id_layered_product`),
		KEY (id_shop, id_category, id_group),
		KEY (id_shop, nleft),
		KEY (id_shop, nright),
		KEY (id_shop, price),
		KEY (id_shop, weight),
		KEY (id_shop, id_feature_value))');

        $ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');

        $alwaysAvailable = false;
        $ps_order_out_of_stock = false;
        if (!$ps_stock_management) {
            $alwaysAvailable = true;
        } else {
            $ps_order_out_of_stock = Configuration::get('PS_ORDER_OUT_OF_STOCK');
        }

        Db::getInstance()->execute('INSERT INTO `'._DB_PREFIX_.'layered_product` 
                    (
                    id_product, 
                    id_shop,
                    id_product_attribute,
                    id_attribute, 
                    id_feature, 
                    id_feature_value, 
                    id_category, 
                    `position`, 
                    quantity, 
                    id_manufacturer, 
                    condition, 
                    weight, 
                    price, 
                    has_specific_price, 
                    nleft, 
                    nright, 
                    out_of_stock,
                    id_group,
                    level_depth
                    )
                    SELECT 
                    p.id_product,
                    ps.id_shop,
                    pa.id_product_attribute,
                    pac.id_attribute,
                    fp.id_feature,
                    fp.id_feature_value,
                    cp.id_category,
                    cp.position,
                    p.quantity,
                    p.id_manufacturer,
                    p.condition,
                    p.weight,
                    p.price,
                    EXISTS(SELECT * FROM `'._DB_PREFIX_.'specific_price` sp WHERE id_product=p.id_product)
                    c.nleft,
                    c.nright,
                    '.($alwaysAvailable?1:'IF(sa.out_of_stock = 2, '.$ps_order_out_of_stock.', sa.out_of_stock)').',
                    cg.id_group,
                    c.level_depth,
                    FROM 
                    `'._DB_PREFIX_.'product` p
                    LEFT JOIN
                    `'._DB_PREFIX_.'product_shop` ps
                     ON (p.id_product = ps.id_product)
                    LEFT JOIN
                    `'._DB_PREFIX_.'product_attribute` pa
                     ON (p.id_product = pa.id_product)         
                    LEFT JOIN
                    `'._DB_PREFIX_.'product_attribute_combination` pac
                     ON (pa.id_product_attribute = pac.id_product_attribute)                                     
                    LEFT JOIN                    
                     `'._DB_PREFIX_.'category_product` cp
                     ON (p.id_product = cp.id_product)
                    LEFT JOIN
                     `'._DB_PREFIX_.'category` c
                     ON (cp.id_category = c.id_category AND c.active=1)                     
                    LEFT JOIN
                     `'._DB_PREFIX_.'feature_product` fp
                     ON (p.id_product = fp.id_product)
                    LEFT JOIN
                     `'._DB_PREFIX_.'category_group` cg
                    LEFT JOIN
                     `'._DB_PREFIX_.'stock_available` sa                     
                     ON (p.id_product=sa.id_product AND pa.id_product_attribute = sa.id_product_attribute)
                    WHERE 
                    active=1 AND `visibility` IN ("both", "catalog")');
    }
    
    public function rebuildLayeredStructure()
    {
        @set_time_limit(0);

        /* Set memory limit to 128M only if current is lower */
        $memory_limit = @ini_get('memory_limit');
        if (substr($memory_limit, -1) != 'G' && ((substr($memory_limit, -1) == 'M' && substr($memory_limit, 0, -1) < 128) || is_numeric($memory_limit) && (intval($memory_limit) < 131072))) {
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
		KEY `id_category_shop` (`id_category`,`id_shop`, `type`, id_value, `position`),
		KEY `id_category` (`id_category`,`type`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;');

        Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'layered_filter` (
		`id_layered_filter` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`name` VARCHAR(64) NOT NULL,
		`filters` TEXT NULL,
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

    public function rebuildLayeredCache($products_ids = array(), $categories_ids = array())
    {
        @set_time_limit(0);

        $filter_data = array('categories' => array());

        /* Set memory limit to 128M only if current is lower */
        $memory_limit = @ini_get('memory_limit');
        if (substr($memory_limit, -1) != 'G' && ((substr($memory_limit, -1) == 'M' && substr($memory_limit, 0, -1) < 128) || is_numeric($memory_limit) && (intval($memory_limit) < 131072))) {
            @ini_set('memory_limit', '128M');
        }

        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
        $n_categories = array();
        $done_categories = array();
        $alias = 'p';
        $join_product_attribute = $join_product = '';

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

            $this->buildLayeredCategories();
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

        $sql_to_insert = 'INSERT INTO '._DB_PREFIX_.'layered_category (id_category, id_shop, id_value, type, position, filter_show_limit, filter_type) VALUES ';
        $values = false;

        foreach ($res as $filter_template) {
            $data = Tools::unSerialize($filter_template['filters']);
            foreach ($data['shop_list'] as $id_shop) {
                if (!isset($categories[$id_shop])) {
                    $categories[$id_shop] = array();
                }

                foreach ($data['categories'] as  $id_category) {
                    $n = 0;
                    if (!in_array($id_category, $categories[$id_shop])) {
                        // Last definition, erase preivious categories defined

                        $categories[$id_shop][] = $id_category;

                        foreach ($data as $key => $value) {
                            if (substr($key, 0, 17) == 'layered_selection') {
                                $values = true;
                                $type = $value['filter_type'];
                                $limit = $value['filter_show_limit'];
                                ++$n;

                                if ($key == 'layered_selection_stock') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'quantity\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_subcategories') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'category\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_condition') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'condition\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_weight_slider') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'weight\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_price_slider') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'price\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif ($key == 'layered_selection_manufacturer') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', NULL,\'manufacturer\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif (substr($key, 0, 21) == 'layered_selection_ag_') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', '.(int) str_replace('layered_selection_ag_', '', $key).',
										\'id_attribute_group\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                } elseif (substr($key, 0, 23) == 'layered_selection_feat_') {
                                    $sql_to_insert .= '('.(int) $id_category.', '.(int) $id_shop.', '.(int) str_replace('layered_selection_feat_', '', $key).',
										\'id_feature\','.(int) $n.', '.(int) $limit.', '.(int) $type.'),';
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($values) {
            Db::getInstance()->execute(rtrim($sql_to_insert, ','));
        }
    }

}

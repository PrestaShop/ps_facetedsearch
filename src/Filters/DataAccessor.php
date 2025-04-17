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

namespace PrestaShop\Module\FacetedSearch\Filters;

use Combination;
use Db;
use Shop;

/**
 * Data accessor for features and attributes
 */
class DataAccessor
{
    /**
     * @var array
     */
    private $attributesGroup = [];

    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $features = [];

    /**
     * @var array
     */
    private $featureValues = [];

    /**
     * @var Db
     */
    private $database;

    public function __construct(Db $database)
    {
        $this->database = $database;
    }

    /**
     * Get all attributes for a given language and attribute group.
     *
     * @param int $idLang
     *
     * @return array Attributes
     */
    public function getAttributes($idLang, $idAttributeGroup)
    {
        if (!Combination::isFeatureActive()) {
            return [];
        }

        if (!isset($this->attributes[$idLang][$idAttributeGroup])) {
            $this->attributes[$idLang] = [$idAttributeGroup => []];
            $tempAttributes = $this->database->executeS(
                'SELECT DISTINCT a.`id_attribute`, ' .
                'a.`color`, ' .
                'al.`name`, ' .
                'agl.`id_attribute_group`, ' .
                'IF(lialv.`url_name` IS NULL OR lialv.`url_name` = "", NULL, lialv.`url_name`) AS url_name, ' .
                'IF(lialv.`meta_title` IS NULL OR lialv.`meta_title` = "", NULL, lialv.`meta_title`) AS meta_title ' .
                'FROM `' . _DB_PREFIX_ . 'attribute_group` ag ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ' .
                'ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int) $idLang . ') ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'attribute` a ' .
                'ON a.`id_attribute_group` = ag.`id_attribute_group` ' .
                'INNER JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ' .
                'ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int) $idLang . ')' .
                Shop::addSqlAssociation('attribute_group', 'ag') . ' ' .
                Shop::addSqlAssociation('attribute', 'a') . ' ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value` lialv ' .
                'ON (a.`id_attribute` = lialv.`id_attribute` AND lialv.`id_lang` = ' . (int) $idLang . ') ' .
                'WHERE ag.id_attribute_group = ' . (int) $idAttributeGroup . ' ' .
                'ORDER BY agl.`name` ASC, a.`position` ASC'
            );

            foreach ($tempAttributes as $attribute) {
                $this->attributes[$idLang][$idAttributeGroup][$attribute['id_attribute']] = $attribute;
            }
        }

        return $this->attributes[$idLang][$idAttributeGroup];
    }

    /**
     * Get all attributes groups for a given language.
     *
     * @param int $idLang Language id
     *
     * @return array Attributes groups
     */
    public function getAttributesGroups($idLang)
    {
        if (!Combination::isFeatureActive()) {
            return [];
        }

        if (!isset($this->attributesGroup[$idLang])) {
            $this->attributesGroup[$idLang] = [];
            $tempAttributesGroup = $this->database->executeS(
                'SELECT ag.id_attribute_group, ' .
                'agl.public_name as attribute_group_name, ' .
                'is_color_group, ' .
                'IF(liaglv.`url_name` IS NULL OR liaglv.`url_name` = "", NULL, liaglv.`url_name`) AS url_name, ' .
                'IF(liaglv.`meta_title` IS NULL OR liaglv.`meta_title` = "", NULL, liaglv.`meta_title`) AS meta_title, ' .
                'IFNULL(liag.indexable, TRUE) AS indexable ' .
                'FROM `' . _DB_PREFIX_ . 'attribute_group` ag ' .
                Shop::addSqlAssociation('attribute_group', 'ag') . ' ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'attribute_group_lang` agl ' .
                'ON (ag.`id_attribute_group` = agl.`id_attribute_group` AND agl.`id_lang` = ' . (int) $idLang . ') ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_attribute_group` liag ' .
                'ON (ag.`id_attribute_group` = liag.`id_attribute_group`) ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value` AS liaglv ' .
                'ON (ag.`id_attribute_group` = liaglv.`id_attribute_group` AND agl.`id_lang` = ' . (int) $idLang . ') ' .
                'GROUP BY ag.id_attribute_group ORDER BY ag.`position` ASC'
            );

            foreach ($tempAttributesGroup as $attributeGroup) {
                $this->attributesGroup[$idLang][$attributeGroup['id_attribute_group']] = $attributeGroup;
            }
        }

        return $this->attributesGroup[$idLang];
    }

    /**
     * Get features with their associated layered information.
     *
     * @param int $idLang
     *
     * @return array Features
     */
    public function getFeatures($idLang)
    {
        if (!isset($this->features[$idLang])) {
            $this->features[$idLang] = [];
            $tempFeatures = $this->database->executeS(
                'SELECT DISTINCT f.id_feature, f.*, fl.*, ' .
                'IF(liflv.`url_name` IS NULL OR liflv.`url_name` = "", NULL, liflv.`url_name`) AS url_name, ' .
                'IF(liflv.`meta_title` IS NULL OR liflv.`meta_title` = "", NULL, liflv.`meta_title`) AS meta_title, ' .
                'lif.indexable ' .
                'FROM `' . _DB_PREFIX_ . 'feature` f ' .
                '' . Shop::addSqlAssociation('feature', 'f') . ' ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'feature_lang` fl ON (f.`id_feature` = fl.`id_feature` AND fl.`id_lang` = ' . (int) $idLang . ') ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_feature` lif ' .
                'ON (f.`id_feature` = lif.`id_feature`) ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value` liflv ' .
                'ON (f.`id_feature` = liflv.`id_feature` AND liflv.`id_lang` = ' . (int) $idLang . ') ' .
                'ORDER BY f.`position` ASC'
            );

            foreach ($tempFeatures as $feature) {
                $this->features[$idLang][$feature['id_feature']] = $feature;
            }
        }

        return $this->features[$idLang];
    }

    /**
     * Get feature values for given feature, with their associated layered information.
     *
     * @param int $idFeature
     * @param int $idLang
     *
     * @return array Feature values
     */
    public function getFeatureValues($idFeature, $idLang)
    {
        if (!isset($this->featureValues[$idLang][$idFeature])) {
            $this->featureValues[$idLang] = [$idFeature => []];
            $tempFeatureValues = $this->database->executeS(
                'SELECT v.*, vl.*, ' .
                'IF(lifvlv.`url_name` IS NULL OR lifvlv.`url_name` = "", NULL, lifvlv.`url_name`) AS url_name, ' .
                'IF(lifvlv.`meta_title` IS NULL OR lifvlv.`meta_title` = "", NULL, lifvlv.`meta_title`) AS meta_title ' .
                'FROM `' . _DB_PREFIX_ . 'feature_value` v ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'feature_value_lang` vl ' .
                'ON (v.`id_feature_value` = vl.`id_feature_value` AND vl.`id_lang` = ' . (int) $idLang . ') ' .
                'LEFT JOIN `' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value` lifvlv ' .
                'ON (v.`id_feature_value` = lifvlv.`id_feature_value` AND lifvlv.`id_lang` = ' . (int) $idLang . ') ' .
                'WHERE v.`id_feature` = ' . (int) $idFeature . ' ' .
                'ORDER BY vl.`value` ASC'
            );

            foreach ($tempFeatureValues as $feature) {
                $this->featureValues[$idLang][$idFeature][$feature['id_feature_value']] = $feature;
            }
        }

        return $this->featureValues[$idLang][$idFeature];
    }
}

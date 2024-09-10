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

use Configuration;
use Context;
use Db;
use FrontController;
use Group;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;
use Search;
use Shop;
use Tools;

/**
 * PrestaShop core does not provide a reasonable (fast) way to get product pool to to search
 * without extra performance overhead we don't need. This class contains fast backports of
 * Search::find method of every major for this purpose.
 *
 * This class will be removed when we are able to get product pool from Search class directly.
 */
class CoreSearchBackport
{
    /**
     * Returns a pool of product IDs to use when filtering products on search controller.
     *
     * @param ProductSearchQuery $query
     *
     * @return array Pool of product IDs
     */
    public function getProductPool(ProductSearchQuery $query)
    {
        // Get search expression from query
        $expression = Tools::replaceAccentedChars(urldecode($query->getSearchString()));

        // No changes in 8.0 to 8.1
        if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
            return $this->get80($expression);
        } elseif (version_compare(_PS_VERSION_, '1.7.8.0', '>=')) {
            return $this->get178($expression);
        } elseif (version_compare(_PS_VERSION_, '1.7.7.0', '>=')) {
            return $this->get177($expression);
        } else {
            return $this->get176($expression);
        }
    }

    /**
     * Backported from 1.7.6.9
     *
     * @param string $expr
     *
     * @return array Pool of product IDs
     */
    public function get176($expr)
    {
        $context = Context::getContext();
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $intersect_array = [];
        $words = Search::extractKeyWords($expr, $context->language->id, false, $context->language->iso_code);

        foreach ($words as $key => $word) {
            if (!empty($word) && strlen($word) >= (int) Configuration::get('PS_SEARCH_MINWORDLEN')) {
                $sql_param_search = Search::getSearchParamFromWord($word);

                $intersect_array[] = 'SELECT DISTINCT si.id_product
        FROM ' . _DB_PREFIX_ . 'search_word sw
        LEFT JOIN ' . _DB_PREFIX_ . 'search_index si ON sw.id_word = si.id_word
        WHERE sw.id_lang = ' . (int) $context->language->id . '
          AND sw.id_shop = ' . $context->shop->id . '
          AND sw.word LIKE
        \'' . $sql_param_search . '\'';
            } else {
                unset($words[$key]);
            }
        }

        if (!count($words)) {
            return [];
        }

        $sql_groups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sql_groups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP'));
        }

        $results = $db->executeS('
      SELECT DISTINCT cp.`id_product`
      FROM `' . _DB_PREFIX_ . 'category_product` cp
      ' . (Group::isFeatureActive() ? 'INNER JOIN `' . _DB_PREFIX_ . 'category_group` cg ON cp.`id_category` = cg.`id_category`' : '') . '
      INNER JOIN `' . _DB_PREFIX_ . 'category` c ON cp.`id_category` = c.`id_category`
      INNER JOIN `' . _DB_PREFIX_ . 'product` p ON cp.`id_product` = p.`id_product`
      ' . Shop::addSqlAssociation('product', 'p', false) . '
      WHERE c.`active` = 1
      AND product_shop.`active` = 1
      AND product_shop.`visibility` IN ("both", "search")
      AND product_shop.indexed = 1
      ' . $sql_groups, true, false);

        $eligible_products = [];
        foreach ($results as $row) {
            $eligible_products[] = $row['id_product'];
        }

        $eligible_products2 = [];
        foreach ($intersect_array as $query) {
            foreach ($db->executeS($query, true, false) as $row) {
                $eligible_products2[] = $row['id_product'];
            }
        }

        return array_unique(array_intersect($eligible_products, array_unique($eligible_products2)));
    }

    /**
     * Backported from 1.7.7.8
     *
     * @param string $expr
     *
     * @return array Pool of product IDs
     */
    public function get177($expr)
    {
        $context = Context::getContext();
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $fuzzyLoop = 0;
        $eligibleProducts2 = null;
        $words = Search::extractKeyWords($expr, $context->language->id, false, $context->language->iso_code);
        $fuzzyMaxLoop = (int) Configuration::get('PS_SEARCH_FUZZY_MAX_LOOP');
        $psFuzzySearch = (int) Configuration::get('PS_SEARCH_FUZZY');
        $psSearchMinWordLength = (int) Configuration::get('PS_SEARCH_MINWORDLEN');

        foreach ($words as $key => $word) {
            if (empty($word) || strlen($word) < $psSearchMinWordLength) {
                unset($words[$key]);
                continue;
            }

            $sql_param_search = Search::getSearchParamFromWord($word);
            $sql = 'SELECT DISTINCT si.id_product ' .
        'FROM ' . _DB_PREFIX_ . 'search_word sw ' .
        'LEFT JOIN ' . _DB_PREFIX_ . 'search_index si ON sw.id_word = si.id_word ' .
        'LEFT JOIN ' . _DB_PREFIX_ . 'product_shop product_shop ON (product_shop.`id_product` = si.`id_product`) ' .
        'WHERE sw.id_lang = ' . (int) $context->language->id . ' ' .
        'AND sw.id_shop = ' . $context->shop->id . ' ' .
        'AND product_shop.`active` = 1 ' .
        'AND product_shop.`visibility` IN ("both", "search") ' .
        'AND product_shop.indexed = 1 ' .
        'AND sw.word LIKE ';

            while (!($result = $db->executeS($sql . "'" . $sql_param_search . "';", true, false))) {
                if (
          !$psFuzzySearch
          || $fuzzyLoop++ > $fuzzyMaxLoop
          || !($sql_param_search = Search::findClosestWeightestWord($context, $word))
        ) {
                    break;
                }
            }

            if (!$result) {
                unset($words[$key]);
                continue;
            }

            $productIds = array_column($result, 'id_product');
            if ($eligibleProducts2 === null) {
                $eligibleProducts2 = $productIds;
            } else {
                $eligibleProducts2 = array_intersect($eligibleProducts2, $productIds);
            }
        }

        if (!count($words)) {
            return [];
        }

        $sqlGroups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sqlGroups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Group::getCurrent()->id);
        }

        $results = $db->executeS(
      'SELECT DISTINCT cp.`id_product` ' .
        'FROM `' . _DB_PREFIX_ . 'category_product` cp ' .
        (Group::isFeatureActive() ? 'INNER JOIN `' . _DB_PREFIX_ . 'category_group` cg ON cp.`id_category` = cg.`id_category`' : '') . ' ' .
        'INNER JOIN `' . _DB_PREFIX_ . 'category` c ON cp.`id_category` = c.`id_category` ' .
        'INNER JOIN `' . _DB_PREFIX_ . 'product` p ON cp.`id_product` = p.`id_product` ' .
        Shop::addSqlAssociation('product', 'p', false) . ' ' .
        'WHERE c.`active` = 1 ' .
        'AND product_shop.`active` = 1 ' .
        'AND product_shop.`visibility` IN ("both", "search") ' .
        'AND product_shop.indexed = 1 ' . $sqlGroups,
      true,
      false
    );

        $eligibleProducts = array_column($results, 'id_product');

        return array_unique(array_intersect($eligibleProducts, array_unique($eligibleProducts2)));
    }

    /**
     * Backported from 1.7.8.8
     *
     * @param string $expr
     *
     * @return array Pool of product IDs
     */
    public function get178($expr)
    {
        $context = Context::getContext();
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $fuzzyLoop = 0;
        $eligibleProducts2 = null;
        $words = Search::extractKeyWords($expr, $context->language->id, false, $context->language->iso_code);
        $fuzzyMaxLoop = (int) Configuration::get('PS_SEARCH_FUZZY_MAX_LOOP');
        $psFuzzySearch = (int) Configuration::get('PS_SEARCH_FUZZY');
        $psSearchMinWordLength = (int) Configuration::get('PS_SEARCH_MINWORDLEN');

        foreach ($words as $key => $word) {
            if (empty($word) || strlen($word) < $psSearchMinWordLength) {
                unset($words[$key]);
                continue;
            }

            $sql_param_search = Search::getSearchParamFromWord($word);
            $sql = 'SELECT DISTINCT si.id_product ' .
        'FROM ' . _DB_PREFIX_ . 'search_word sw ' .
        'LEFT JOIN ' . _DB_PREFIX_ . 'search_index si ON sw.id_word = si.id_word ' .
        'LEFT JOIN ' . _DB_PREFIX_ . 'product_shop product_shop ON (product_shop.`id_product` = si.`id_product`) ' .
        'WHERE sw.id_lang = ' . (int) $context->language->id . ' ' .
        'AND sw.id_shop = ' . $context->shop->id . ' ' .
        'AND product_shop.`active` = 1 ' .
        'AND product_shop.`visibility` IN ("both", "search") ' .
        'AND product_shop.indexed = 1 ' .
        'AND sw.word LIKE ';

            while (!($result = $db->executeS($sql . "'" . $sql_param_search . "';", true, false))) {
                if (
          !$psFuzzySearch
          || $fuzzyLoop++ > $fuzzyMaxLoop
          || !($sql_param_search = Search::findClosestWeightestWord($context, $word))
        ) {
                    break;
                }
            }

            if (!$result) {
                unset($words[$key]);
                continue;
            }

            $productIds = array_column($result, 'id_product');
            if ($eligibleProducts2 === null) {
                $eligibleProducts2 = $productIds;
            } else {
                $eligibleProducts2 = array_intersect($eligibleProducts2, $productIds);
            }
        }

        if (!count($words) || !count($eligibleProducts2)) {
            return [];
        }

        $sqlGroups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sqlGroups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Group::getCurrent()->id);
        }

        $results = $db->executeS(
      'SELECT DISTINCT cp.`id_product` ' .
        'FROM `' . _DB_PREFIX_ . 'category_product` cp ' .
        (Group::isFeatureActive() ? 'INNER JOIN `' . _DB_PREFIX_ . 'category_group` cg ON cp.`id_category` = cg.`id_category`' : '') . ' ' .
        'INNER JOIN `' . _DB_PREFIX_ . 'category` c ON cp.`id_category` = c.`id_category` ' .
        'INNER JOIN `' . _DB_PREFIX_ . 'product` p ON cp.`id_product` = p.`id_product` ' .
        Shop::addSqlAssociation('product', 'p', false) . ' ' .
        'WHERE c.`active` = 1 ' .
        'AND product_shop.`active` = 1 ' .
        'AND product_shop.`visibility` IN ("both", "search") ' .
        'AND product_shop.indexed = 1 ' .
        'AND cp.id_product IN (' . implode(',', $eligibleProducts2) . ')' . $sqlGroups,
      true,
      false
    );

        return array_column($results, 'id_product');
    }

    /**
     * Backported 8.0.1
     *
     * @param string $expr
     *
     * @return array Pool of product IDs
     */
    public function get80($expr)
    {
        $context = Context::getContext();
        $db = Db::getInstance(_PS_USE_SQL_SLAVE_);

        $scoreArray = [];
        $fuzzyLoop = 0;
        $wordCnt = 0;
        $eligibleProducts2Full = [];
        $expressions = explode(';', $expr);
        $fuzzyMaxLoop = (int) Configuration::get('PS_SEARCH_FUZZY_MAX_LOOP');
        $psFuzzySearch = (int) Configuration::get('PS_SEARCH_FUZZY');
        $psSearchMinWordLength = (int) Configuration::get('PS_SEARCH_MINWORDLEN');
        foreach ($expressions as $expression) {
            $eligibleProducts2 = null;
            $words = Search::extractKeyWords($expression, $context->language->id, false, $context->language->iso_code);
            foreach ($words as $key => $word) {
                if (empty($word) || strlen($word) < $psSearchMinWordLength) {
                    unset($words[$key]);
                    continue;
                }

                $sql_param_search = Search::getSearchParamFromWord($word);
                $sql = 'SELECT DISTINCT si.id_product ' .
                'FROM ' . _DB_PREFIX_ . 'search_word sw ' .
                'LEFT JOIN ' . _DB_PREFIX_ . 'search_index si ON sw.id_word = si.id_word ' .
                'LEFT JOIN ' . _DB_PREFIX_ . 'product_shop product_shop ON (product_shop.`id_product` = si.`id_product`) ' .
                'WHERE sw.id_lang = ' . (int) $context->language->id . ' ' .
                'AND sw.id_shop = ' . $context->shop->id . ' ' .
                'AND product_shop.`active` = 1 ' .
                'AND product_shop.`visibility` IN ("both", "search") ' .
                'AND product_shop.indexed = 1 ' .
                'AND sw.word LIKE ';

                while (!($result = $db->executeS($sql . "'" . $sql_param_search . "';", true, false))) {
                    if (
            !$psFuzzySearch
            || $fuzzyLoop++ > $fuzzyMaxLoop
            || !($sql_param_search = Search::findClosestWeightestWord($context, $word))
          ) {
                        break;
                    }
                }

                if (!$result) {
                    unset($words[$key]);
                    continue;
                }

                $productIds = array_column($result, 'id_product');
                if ($eligibleProducts2 === null) {
                    $eligibleProducts2 = $productIds;
                } else {
                    $eligibleProducts2 = array_intersect($eligibleProducts2, $productIds);
                }

                $scoreArray[] = 'sw.word LIKE \'' . $sql_param_search . '\'';
            }
            $wordCnt += count($words);
            if ($eligibleProducts2) {
                $eligibleProducts2Full = array_merge($eligibleProducts2Full, $eligibleProducts2);
            }
        }

        $eligibleProducts2Full = array_unique($eligibleProducts2Full);

        if (!$wordCnt || !count($eligibleProducts2Full)) {
            return [];
        }

        $sqlScore = '';
        if (!empty($scoreArray) && is_array($scoreArray)) {
            $sqlScore = ',( ' .
                'SELECT SUM(weight) ' .
                'FROM ' . _DB_PREFIX_ . 'search_word sw ' .
                'LEFT JOIN ' . _DB_PREFIX_ . 'search_index si ON sw.id_word = si.id_word ' .
                'WHERE sw.id_lang = ' . (int) $context->language->id . ' ' .
                'AND sw.id_shop = ' . $context->shop->id . ' ' .
                'AND si.id_product = p.id_product ' .
                'AND (' . implode(' OR ', $scoreArray) . ') ' .
                ') position';
        }

        $sqlGroups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sqlGroups = 'AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Group::getCurrent()->id);
        }

        $results = $db->executeS(
            'SELECT DISTINCT cp.`id_product` ' . $sqlScore . ' ' .
            'FROM `' . _DB_PREFIX_ . 'category_product` cp ' .
            (Group::isFeatureActive() ? 'INNER JOIN `' . _DB_PREFIX_ . 'category_group` cg ON cp.`id_category` = cg.`id_category`' : '') . ' ' .
            'INNER JOIN `' . _DB_PREFIX_ . 'category` c ON cp.`id_category` = c.`id_category` ' .
            'INNER JOIN `' . _DB_PREFIX_ . 'product` p ON cp.`id_product` = p.`id_product` ' .
            Shop::addSqlAssociation('product', 'p', false) . ' ' .
            'WHERE c.`active` = 1 ' .
            'AND product_shop.`active` = 1 ' .
            'AND product_shop.`visibility` IN ("both", "search") ' .
            'AND product_shop.indexed = 1 ' .
            'AND cp.id_product IN (' . implode(',', $eligibleProducts2Full) . ')' . $sqlGroups . '
            ORDER BY position DESC, p.id_product ASC',
        true,
        false
        );

        return array_column($results, 'id_product');
    }
}

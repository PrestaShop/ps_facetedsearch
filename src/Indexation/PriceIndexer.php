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

namespace PrestaShop\Module\FacetedSearch\Indexation;

use Configuration;
use Db;
use Product;
use Shop;

class PriceIndexer
{
    /**
     * @var array|null
     */
    private static $shops;

    /**
     * @var array
     */
    private static $countriesByShop = [];

    /**
     * @var array
     */
    private static $groupsByShop = [];

    /**
     * @var array
     */
    private static $currenciesByShop = [];

    /**
     * Index product prices using the smallest country and group matrix required by each shop and currency.
     *
     * @param int $idProduct
     * @param bool $smart Delete existing rows before reindexing
     */
    public function indexProductPrices($idProduct, $smart = true)
    {
        // Index every shop independently so currency conversion and relevant customer dimensions never leak between shops.
        foreach ($this->getShops() as $idShop) {
            if ($smart) {
                Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'layered_price_index` WHERE `id_product` = ' . (int) $idProduct . ' AND `id_shop` = ' . (int) $idShop);
            }

            // Load and cache the dimensions available in this shop.
            $countries = $this->getCountriesByShop($idShop);
            $groups = $this->getGroupsByShop($idShop);
            $currencies = $this->getCurrenciesByShop($idShop);
            if (empty($countries) || empty($groups) || empty($currencies)) {
                continue;
            }

            // Load product-specific data and shop configuration once for every currency, country and group.
            $specificPriceQuantities = $this->getSpecificPriceQuantities($idProduct, $idShop);
            $useTax = (bool) Configuration::get('PS_LAYERED_FILTER_PRICE_USETAX', null, null, $idShop);
            $values = [];

            // Build and compact a separate country/group matrix for each converted currency.
            foreach ($currencies as $currency) {
                $prices = $this->getPriceMatrix(
                    $idProduct,
                    $idShop,
                    (int) $currency['id_currency'],
                    $countries,
                    $groups,
                    $specificPriceQuantities,
                    $useTax
                );

                // Convert the matrix into rows using zero for every dimension that does not change the calculated range.
                foreach ($this->compactPriceMatrix($prices) as $indexedPrice) {
                    $values[] = '(' . (int) $idProduct . ', '
                        . (int) $currency['id_currency'] . ', '
                        . (int) $idShop . ', '
                        . (float) $indexedPrice['price_min'] . ', '
                        . (float) $indexedPrice['price_max'] . ', '
                        . (int) $indexedPrice['id_country'] . ', '
                        . (int) $indexedPrice['id_group'] . ')';
                }
            }

            // Insert all compacted prices for the shop in one query.
            if (!empty($values)) {
                Db::getInstance()->execute(
                    'INSERT INTO `' . _DB_PREFIX_ . 'layered_price_index` (id_product, id_currency, id_shop, price_min, price_max, id_country, id_group)
                     VALUES ' . implode(',', $values) . '
                     ON DUPLICATE KEY UPDATE id_product = id_product'
                );
            }
        }
    }

    /**
     * Return all shop identifiers and cache them for subsequent products.
     *
     * @return array
     */
    private function getShops()
    {
        if (self::$shops === null) {
            self::$shops = Shop::getShops(false, null, true);
        }

        return self::$shops;
    }

    /**
     * Return active countries associated with a shop.
     *
     * @param int $idShop
     *
     * @return array
     */
    private function getCountriesByShop($idShop)
    {
        if (!isset(self::$countriesByShop[$idShop])) {
            // Load only active countries explicitly associated with the indexed shop.
            self::$countriesByShop[$idShop] = Db::getInstance()->executeS(
                'SELECT c.id_country
                FROM `' . _DB_PREFIX_ . 'country` c
                INNER JOIN `' . _DB_PREFIX_ . 'country_shop` cs ON (cs.id_country = c.id_country)
                WHERE c.active = 1 AND cs.id_shop = ' . (int) $idShop
            ) ?: [];
        }

        return self::$countriesByShop[$idShop];
    }

    /**
     * Return customer groups associated with a shop.
     *
     * @param int $idShop
     *
     * @return array
     */
    private function getGroupsByShop($idShop)
    {
        if (!isset(self::$groupsByShop[$idShop])) {
            // Load every group explicitly associated with the indexed shop because groups have no active flag.
            self::$groupsByShop[$idShop] = Db::getInstance()->executeS(
                'SELECT g.id_group
                FROM `' . _DB_PREFIX_ . 'group` g
                INNER JOIN `' . _DB_PREFIX_ . 'group_shop` gs ON (gs.id_group = g.id_group)
                WHERE gs.id_shop = ' . (int) $idShop
            ) ?: [];
        }

        return self::$groupsByShop[$idShop];
    }

    /**
     * Return active, non-deleted currencies associated with a shop.
     *
     * @param int $idShop
     *
     * @return array
     */
    private function getCurrenciesByShop($idShop)
    {
        if (!isset(self::$currenciesByShop[$idShop])) {
            // Load only active, non-deleted currencies explicitly associated with the indexed shop.
            self::$currenciesByShop[$idShop] = Db::getInstance()->executeS(
                'SELECT c.id_currency
                FROM `' . _DB_PREFIX_ . 'currency` c
                INNER JOIN `' . _DB_PREFIX_ . 'currency_shop` cs ON (cs.id_currency = c.id_currency)
                WHERE c.active = 1 AND c.deleted = 0 AND cs.id_shop = ' . (int) $idShop
            ) ?: [];
        }

        return self::$currenciesByShop[$idShop];
    }

    /**
     * Return all specific-price quantity thresholds that can affect a product in a shop.
     *
     * @param int $idProduct
     * @param int $idShop
     *
     * @return array
     */
    private function getSpecificPriceQuantities($idProduct, $idShop)
    {
        $specificPrices = Db::getInstance()->executeS(
            'SELECT DISTINCT from_quantity
            FROM `' . _DB_PREFIX_ . 'specific_price`
            WHERE id_product = ' . (int) $idProduct . ' AND id_shop IN (0,' . (int) $idShop . ')'
        );

        // Return scalar quantities so the calculation loop remains independent of the query representation.
        return array_map(function ($specificPrice) {
            return (int) $specificPrice['from_quantity'];
        }, $specificPrices ?: []);
    }

    /**
     * Calculate the final price range for every country and group in one shop currency.
     *
     * @param int $idProduct
     * @param int $idShop
     * @param int $idCurrency
     * @param array $countries
     * @param array $groups
     * @param array $specificPriceQuantities
     * @param bool $useTax
     *
     * @return array
     */
    private function getPriceMatrix($idProduct, $idShop, $idCurrency, array $countries, array $groups, array $specificPriceQuantities, $useTax)
    {
        $prices = [];

        // Calculate each country and group combination through the core pricing logic so taxes and hooks affect dependency detection.
        foreach ($countries as $country) {
            $idCountry = (int) $country['id_country'];
            foreach ($groups as $group) {
                $idGroup = (int) $group['id_group'];
                $prices[$idCountry][$idGroup] = $this->calculatePriceRange($idProduct, $idShop, $idCurrency, $idCountry, $idGroup, $specificPriceQuantities, $useTax);
            }
        }

        return $prices;
    }

    /**
     * Calculate minimum and maximum converted prices for one country and group.
     *
     * @param int $idProduct
     * @param int $idShop
     * @param int $idCurrency
     * @param int $idCountry
     * @param int $idGroup
     * @param array $quantities
     * @param bool $useTax
     *
     * @return array
     */
    private function calculatePriceRange($idProduct, $idShop, $idCurrency, $idCountry, $idGroup, array $quantities, $useTax)
    {
        // Calculate the regular price without a specific-price reduction while retaining configured taxes, group reductions and hooks.
        $specificPriceOutput = null;
        $price = Product::priceCalculation(
            $idShop,
            $idProduct,
            null,
            $idCountry,
            0,
            '',
            $idCurrency,
            $idGroup,
            0,
            $useTax,
            6,
            false,
            false,
            true,
            $specificPriceOutput,
            true
        );
        $minPrice = $price;
        $maxPrice = $price;

        // Calculate every specific-price quantity candidate for the same country and group.
        foreach ($quantities as $quantity) {
            $price = Product::priceCalculation(
                $idShop,
                $idProduct,
                null,
                $idCountry,
                0,
                '',
                $idCurrency,
                $idGroup,
                $quantity,
                $useTax,
                6,
                false,
                true,
                true,
                $specificPriceOutput,
                true
            );

            if ($price > $maxPrice) {
                $maxPrice = $price;
            }

            if ($price == 0) {
                continue;
            }

            if ($minPrice === null || $price < $minPrice) {
                $minPrice = $price;
            }
        }

        return [
            'price_min' => (float) $minPrice,
            'price_max' => (float) $maxPrice,
        ];
    }

    /**
     * Replace country and group identifiers with zero when their values do not affect the price range.
     *
     * @param array $prices
     *
     * @return array
     */
    private function compactPriceMatrix(array $prices)
    {
        $firstCountryId = key($prices);
        $firstGroupId = key($prices[$firstCountryId]);
        $dependsOnCountry = false;
        $dependsOnGroup = false;

        // Compare each country with the first country while keeping the group fixed.
        foreach ($prices as $idCountry => $groupPrices) {
            foreach ($groupPrices as $idGroup => $price) {
                if ($price['price_min'] != $prices[$firstCountryId][$idGroup]['price_min'] || $price['price_max'] != $prices[$firstCountryId][$idGroup]['price_max']) {
                    $dependsOnCountry = true;
                }

                if ($price['price_min'] != $groupPrices[$firstGroupId]['price_min'] || $price['price_max'] != $groupPrices[$firstGroupId]['price_max']) {
                    $dependsOnGroup = true;
                }
            }
        }

        // Store one representative value for every dimension that does not affect the calculated range.
        $indexedPrices = [];
        foreach ($prices as $idCountry => $groupPrices) {
            foreach ($groupPrices as $idGroup => $price) {
                $indexCountryId = $dependsOnCountry ? $idCountry : 0;
                $indexGroupId = $dependsOnGroup ? $idGroup : 0;
                $indexedPrices[$indexCountryId . '-' . $indexGroupId] = [
                    'id_country' => $indexCountryId,
                    'id_group' => $indexGroupId,
                    'price_min' => $price['price_min'],
                    'price_max' => $price['price_max'],
                ];
            }
        }

        return array_values($indexedPrices);
    }
}

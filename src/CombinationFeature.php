<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\FacetedSearch;

use Context;
use Db;
use PrestaShop\PrestaShop\Adapter\ContainerFinder;
use Throwable;

/**
 * Tells whether the faceted search must also take combination (product_attribute) feature values
 * into account, in addition to the product ones.
 *
 * This is only available from PrestaShop 9.3 (the version that introduced feature values at
 * combination level) and must additionally be turned on through the "combination_feature_values"
 * feature flag.
 */
class CombinationFeature
{
    /**
     * Name of the core feature flag guarding combination feature values.
     */
    public const FEATURE_FLAG = 'combination_feature_values';

    /**
     * Minimum PrestaShop version exposing combination feature values.
     */
    public const MIN_PS_VERSION = '9.3.0';

    /**
     * Level of a feature (value) set on products only.
     */
    public const LEVEL_PRODUCT = 'product';

    /**
     * Level of a feature (value) set on combinations only.
     */
    public const LEVEL_COMBINATION = 'combination';

    /**
     * @var bool|null
     */
    private static $enabled;

    /**
     * @var array<int, array{product: bool, combination: bool}>
     */
    private static $featureLevels = [];

    /**
     * @var array<int, array{product: bool, combination: bool}>
     */
    private static $featureValueLevels = [];

    /**
     * @return bool
     */
    public static function isFilteringEnabled()
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        self::$enabled = false;

        // Combination feature values simply do not exist before PrestaShop 9.3.
        if (version_compare(_PS_VERSION_, self::MIN_PS_VERSION, '<')) {
            return self::$enabled;
        }

        try {
            /** @var \Psr\Container\ContainerInterface $container */
            $container = (new ContainerFinder(Context::getContext()))->getContainer();
            $checker = $container->get('PrestaShop\\PrestaShop\\Core\\FeatureFlag\\FeatureFlagStateCheckerInterface');
            self::$enabled = $checker !== null && $checker->isEnabled(self::FEATURE_FLAG);
        } catch (Throwable $e) {
            // If the container or the checker is not reachable, stay on the historical behavior.
            self::$enabled = false;
        }

        return self::$enabled;
    }

    /**
     * Resets the memoized state, mostly useful for tests.
     */
    public static function resetCache()
    {
        self::$enabled = null;
        self::$featureLevels = [];
        self::$featureValueLevels = [];
    }

    /**
     * Tell, for each feature, whether it has values set on products and on combinations.
     * Cached for the request, every facet query asks for the same features.
     *
     * @param int[] $idFeatures
     *
     * @return array<int, array{product: bool, combination: bool}>
     */
    public static function getFeatureLevels(array $idFeatures)
    {
        return self::getLevels('feature', 'id_feature', self::$featureLevels, $idFeatures);
    }

    /**
     * Tell, for each feature value, whether it is set on products and on combinations.
     * Cached for the request, every facet query asks for the same values.
     *
     * @param int[] $idFeatureValues
     *
     * @return array<int, array{product: bool, combination: bool}>
     */
    public static function getFeatureValueLevels(array $idFeatureValues)
    {
        return self::getLevels('feature_value', 'id_feature_value', self::$featureValueLevels, $idFeatureValues);
    }

    /**
     * The level shared by all the given features (values), or null when some of them are set on
     * combinations and some on products, or one on both.
     *
     * @param array<int, array{product: bool, combination: bool}> $levels
     *
     * @return string|null
     */
    public static function resolveLevel(array $levels)
    {
        if (!in_array(true, array_column($levels, 'combination'), true)) {
            return self::LEVEL_PRODUCT;
        }
        if (!in_array(true, array_column($levels, 'product'), true)) {
            return self::LEVEL_COMBINATION;
        }

        return null;
    }

    /**
     * Split features into groups that can each be counted from the table of their level.
     *
     * @param int[] $idFeatures
     *
     * @return array<int, int[]>
     */
    public static function groupFeaturesByLevel(array $idFeatures)
    {
        $groups = [];
        foreach (self::getFeatureLevels($idFeatures) as $idFeature => $level) {
            $groups[(string) self::resolveLevel([$level])][] = $idFeature;
        }

        return array_values($groups);
    }

    /**
     * @param string $table
     * @param string $column
     * @param array<int, array{product: bool, combination: bool}> $cache
     * @param int[] $ids
     *
     * @return array<int, array{product: bool, combination: bool}>
     */
    private static function getLevels($table, $column, array &$cache, array $ids)
    {
        $ids = array_map('intval', $ids);
        $missingIds = array_diff($ids, array_keys($cache));
        if (!empty($missingIds)) {
            $rows = Db::getInstance()->executeS(
                'SELECT t.' . $column . ' AS id,'
                . ' EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'feature_product fp'
                . ' WHERE fp.' . $column . ' = t.' . $column . ') AS on_product,'
                . ' EXISTS (SELECT 1 FROM ' . _DB_PREFIX_ . 'feature_product_attribute fpa'
                . ' WHERE fpa.' . $column . ' = t.' . $column . ') AS on_combination'
                . ' FROM ' . _DB_PREFIX_ . $table . ' t'
                . ' WHERE t.' . $column . ' IN (' . implode(', ', $missingIds) . ')'
            );
            foreach ($missingIds as $id) {
                $cache[$id] = ['product' => false, 'combination' => false];
            }
            foreach ($rows ?: [] as $row) {
                $cache[(int) $row['id']] = [
                    'product' => (bool) $row['on_product'],
                    'combination' => (bool) $row['on_combination'],
                ];
            }
        }

        return array_intersect_key($cache, array_flip($ids));
    }
}

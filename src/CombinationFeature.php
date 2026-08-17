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
     * @var bool|null
     */
    private static $enabled;

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
    }
}

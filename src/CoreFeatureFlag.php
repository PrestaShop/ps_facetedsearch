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
 * Reads the state of a core feature flag. The flags the module relies on only exist from PrestaShop 9.3,
 * and a flag that cannot be read is considered disabled.
 */
class CoreFeatureFlag
{
    public const MIN_PS_VERSION = '9.3.0';

    /**
     * @var array<string, bool>
     */
    private static $states = [];

    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isEnabled($name)
    {
        if (!isset(self::$states[$name])) {
            self::$states[$name] = self::readState($name);
        }

        return self::$states[$name];
    }

    /**
     * Resets the memoized states, mostly useful for tests.
     */
    public static function resetCache()
    {
        self::$states = [];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private static function readState($name)
    {
        if (version_compare(_PS_VERSION_, self::MIN_PS_VERSION, '<')) {
            return false;
        }

        try {
            /** @var \Psr\Container\ContainerInterface $container */
            $container = (new ContainerFinder(Context::getContext()))->getContainer();
            $checker = $container->get('PrestaShop\\PrestaShop\\Core\\FeatureFlag\\FeatureFlagStateCheckerInterface');

            return $checker !== null && $checker->isEnabled($name);
        } catch (Throwable $e) {
            return false;
        }
    }
}

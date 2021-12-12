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

namespace PrestaShop\Module\FacetedSearch;

use Ps_Facetedsearch;

/**
 * Class works with Hook\AbstractHook instances in order to reduce ps_facetedsearch.php size.
 *
 * The dispatch method is called from the __call method in the module class.
 */
class HookDispatcher
{
    const CLASSES = [
        Hook\Attribute::class,
        Hook\AttributeGroup::class,
        Hook\Category::class,
        Hook\Design::class,
        Hook\Feature::class,
        Hook\FeatureValue::class,
        Hook\Product::class,
        Hook\ProductSearch::class,
        Hook\SpecificPrice::class,
        Hook\ProductComment::class,
    ];

    /**
     * List of available hooks
     *
     * @var string[]
     */
    private $availableHooks = [];

    /**
     * Hook classes
     *
     * @var Hook\AbstractHook[]
     */
    private $hooks = [];

    /**
     * Module
     *
     * @var Ps_Facetedsearch
     */
    private $module;

    /**
     * Init hooks
     *
     * @param Ps_Facetedsearch $module
     */
    public function __construct(Ps_Facetedsearch $module)
    {
        $this->module = $module;

        foreach (self::CLASSES as $hookClass) {
            $hook = new $hookClass($this->module);
            $this->availableHooks = array_merge($this->availableHooks, $hook->getAvailableHooks());
            $this->hooks[] = $hook;
        }
    }

    /**
     * Get available hooks
     *
     * @return string[]
     */
    public function getAvailableHooks()
    {
        return $this->availableHooks;
    }

    /**
     * Find hook and dispatch it
     *
     * @param string $hookName
     * @param array $params
     *
     * @return mixed
     */
    public function dispatch($hookName, array $params = [])
    {
        $hookName = preg_replace('~^hook~', '', $hookName);

        foreach ($this->hooks as $hook) {
            if (method_exists($hook, $hookName)) {
                return call_user_func([$hook, $hookName], $params);
            }
        }

        // No hook found, render it as a widget
        return $this->module->renderWidget($hookName, $params);
    }
}

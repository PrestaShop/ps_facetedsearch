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

namespace PrestaShop\Module\FacetedSearch\Hook;

class SpecificPrice extends AbstractHook
{
    /**
     * @var array
     */
    protected $productsBefore = null;

    const AVAILABLE_HOOKS = [
        'actionObjectSpecificPriceRuleUpdateBefore',
        'actionAdminSpecificPriceRuleControllerSaveAfter',
    ];

    /**
     * Before saving a specific price rule
     *
     * @param array $params
     */
    public function actionObjectSpecificPriceRuleUpdateBefore(array $params)
    {
        if (empty($params['object']->id)) {
            return;
        }

        /** @var \SpecificPriceRule */
        $specificPrice = $params['object'];
        $this->productsBefore = $specificPrice->getAffectedProducts();
    }

    /**
     * After saving a specific price rule
     *
     * @param array $params
     */
    public function actionAdminSpecificPriceRuleControllerSaveAfter(array $params)
    {
        if (empty($params['return']->id) || empty($this->productsBefore)) {
            return;
        }

        /** @var \SpecificPriceRule */
        $specificPrice = $params['return'];
        $affectedProducts = array_merge($this->productsBefore, $specificPrice->getAffectedProducts());
        foreach ($affectedProducts as $product) {
            $this->module->indexProductPrices($product['id_product']);
            $this->module->indexAttributes($product['id_product']);
        }

        $this->module->invalidateLayeredFilterBlockCache();
    }
}

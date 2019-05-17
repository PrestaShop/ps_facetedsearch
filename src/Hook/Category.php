<?php
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\FacetedSearch\Hook;

use Tools;

class Category extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'actionCategoryAdd',
        'actionCategoryDelete',
        'actionCategoryUpdate',
    ];

    /**
     * Category addition
     *
     * @param array $params
     */
    public function actionCategoryAdd(array $params)
    {
        $this->module->rebuildLayeredCache([], [(int) $params['category']->id]);
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Category update
     *
     * @param array $params
     */
    public function actionCategoryUpdate(array $params)
    {
        /*
         * The category status might (active, inactive) have changed,
         * we have to update the layered cache table structure
         */
        if (isset($params['category']) && !$params['category']->active) {
            $this->cleanAndRebuildCategoryFilters($params);
        }
    }

    /**
     * Category deletion
     *
     * @param array $params
     */
    public function actionCategoryDelete(array $params)
    {
        $this->cleanAndRebuildCategoryFilters($params);
    }

    /**
     * Clean and rebuild category filters
     *
     * @param array $params
     */
    private function cleanAndRebuildCategoryFilters(array $params)
    {
        $layeredFilterList = $this->database->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'layered_filter'
        );

        foreach ($layeredFilterList as $layeredFilter) {
            $data = Tools::unSerialize($layeredFilter['filters']);

            if (in_array((int) $params['category']->id, $data['categories'])) {
                unset($data['categories'][array_search((int) $params['category']->id, $data['categories'])]);
                $this->database->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'layered_filter`
                    SET `filters` = \'' . pSQL(serialize($data)) . '\'
                    WHERE `id_layered_filter` = ' . (int) $layeredFilter['id_layered_filter']
                );
            }
        }

        $this->module->invalidateLayeredFilterBlockCache();
        $this->module->buildLayeredCategories();
    }
}

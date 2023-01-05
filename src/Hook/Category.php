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

use Configuration;
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
        $this->addCategoryToDefaultFilter((int) $params['category']->id);

        // Flush filter block cache in all cases, so a new category shows up
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
         * we have to update the layered cache table structure.
         */
        if (isset($params['category']) && !$params['category']->active) {
            $this->removeCategoryFromFilterTemplates((int) $params['category']->id);
        }
    }

    /**
     * Category deletion
     *
     * @param array $params
     */
    public function actionCategoryDelete(array $params)
    {
        $this->removeCategoryFromFilterTemplates((int) $params['category']->id);
    }

    /**
     * Clean and rebuild category filters
     *
     * @param int $idCategory
     */
    private function removeCategoryFromFilterTemplates(int $idCategory)
    {
        $layeredFilterList = $this->database->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'layered_filter'
        );

        foreach ($layeredFilterList as $layeredFilter) {
            $data = Tools::unSerialize($layeredFilter['filters']);

            if (in_array((int) $idCategory, $data['categories'])) {
                unset($data['categories'][array_search((int) $idCategory, $data['categories'])]);
                $this->database->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'layered_filter`
                    SET `filters` = \'' . pSQL(serialize($data)) . '\',
                    n_categories = ' . (int) count($data['categories']) . ' 
                    WHERE `id_layered_filter` = ' . (int) $layeredFilter['id_layered_filter']
                );
            }
        }

        $this->module->invalidateLayeredFilterBlockCache();
        $this->module->buildLayeredCategories();
    }

    /**
     * Checks if module is configured to automatically add some filter to new categories.
     * If so, it adds the new category.
     *
     * @param int $idCategory ID of category being created
     */
    public function addCategoryToDefaultFilter(int $idCategory)
    {
        // Get default template
        $defaultFilterTemplateId = (int) Configuration::get('PS_LAYERED_DEFAULT_CATEGORY_TEMPLATE');
        if (empty($defaultFilterTemplateId)) {
            return;
        }

        // Try to get it's data
        $template = $this->module->getFilterTemplate($defaultFilterTemplateId);
        if (empty($template)) {
            return;
        }

        // Unserialize filters, add our category
        $filters = Tools::unSerialize($template['filters']);
        $filters['categories'][] = $idCategory;

        // Update it in database
        $sql = 'UPDATE ' . _DB_PREFIX_ . 'layered_filter ' .
        'SET filters = "' . pSQL(serialize($filters)) . '", ' .
        'n_categories = ' . (int) count($filters['categories']) . ' ' .
        'WHERE id_layered_filter = ' . $defaultFilterTemplateId;
        $this->database->execute($sql);

        $this->module->buildLayeredCategories();
    }
}

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

use Language;
use Tools;

class FeatureValue extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'actionFeatureValueSave',
        'afterDeleteFeatureValue',
        'afterSaveFeatureValue',
        'displayFeatureValueForm',
        'postProcessFeatureValue',
    ];

    /**
     * After save feature value
     *
     * @param array $params
     */
    public function afterSaveFeatureValue(array $params)
    {
        if (empty($params['id_feature_value'])) {
            return;
        }

        //Removing all indexed language data for this attribute value id
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value
            WHERE `id_feature_value` = ' . (int) $params['id_feature_value']
        );

        foreach (Language::getLanguages(false) as $language) {
            $seoUrl = Tools::getValue('url_name_' . (int) $language['id_lang']);

            if (empty($seoUrl)) {
                $seoUrl = Tools::getValue('name_' . (int) $language['id_lang']);
            }

            $this->database->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value
                (`id_feature_value`, `id_lang`, `url_name`, `meta_title`)
                VALUES (
                ' . (int) $params['id_feature_value'] . ', ' . (int) $language['id_lang'] . ',
                \'' . pSQL(Tools::link_rewrite($seoUrl)) . '\',
                \'' . pSQL(Tools::getValue('meta_title_' . (int) $language['id_lang']), true) . '\')'
            );
        }
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * After delete Feature value
     *
     * @param array $params
     */
    public function afterDeleteFeatureValue(array $params)
    {
        if (empty($params['id_feature_value'])) {
            return;
        }

        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value
            WHERE `id_feature_value` = ' . (int) $params['id_feature_value']
        );
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Post process feature value
     *
     * @param array $params
     */
    public function postProcessFeatureValue(array $params)
    {
        $this->module->checkLinksRewrite($params);
    }

    /**
     * Display feature value form
     *
     * @param array $params
     *
     * @return string
     */
    public function displayFeatureValueForm(array $params)
    {
        $values = [];

        if ($result = $this->database->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang`
            FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_value_lang_value
            WHERE `id_feature_value` = ' . (int) $params['id_feature_value']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = ['url_name' => $data['url_name'], 'meta_title' => $data['meta_title']];
            }
        }

        $this->context->smarty->assign([
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
        ]);

        return $this->module->render('feature_value_form.tpl');
    }

    /**
     * After save feature
     *
     * @param array $params
     */
    public function actionFeatureValueSave(array $params)
    {
        if (empty($params['id_feature']) || Tools::getValue('layered_indexable') === false) {
            return;
        }

        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature
            WHERE `id_feature` = ' . (int) $params['id_feature']
        );
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value
            WHERE `id_feature` = ' . (int) $params['id_feature']
        );

        $this->database->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_feature
            (`id_feature`, `indexable`)
            VALUES (' . (int) $params['id_feature'] . ', ' . (int) Tools::getValue('layered_indexable') . ')'
        );

        foreach (Language::getLanguages(false) as $language) {
            $seoUrl = Tools::getValue('url_name_' . (int) $language['id_lang']);

            if (empty($seoUrl)) {
                $seoUrl = Tools::getValue('name_' . (int) $language['id_lang']);
            }

            $this->database->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value
                (`id_feature`, `id_lang`, `url_name`, `meta_title`)
                VALUES (
                ' . (int) $params['id_feature'] . ', ' . (int) $language['id_lang'] . ',
                \'' . pSQL(Tools::link_rewrite($seoUrl)) . '\',
                \'' . pSQL(Tools::getValue('meta_title_' . (int) $language['id_lang']), true) . '\')'
            );
        }

        $this->module->invalidateLayeredFilterBlockCache();
    }
}

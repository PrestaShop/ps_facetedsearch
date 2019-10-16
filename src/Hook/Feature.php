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

use Configuration;
use Language;
use PrestaShopDatabaseException;
use PrestaShop\Module\FacetedSearch\Form\Feature\FormDataProvider;
use PrestaShop\Module\FacetedSearch\Form\Feature\FormModifier;
use Ps_Facetedsearch;
use Tools;

class Feature extends AbstractHook
{
    /**
     * @var FormModifier
     */
    private $formModifier;

    /**
     * @var FormDataProvider
     */
    private $dataProvider;

    public function __construct(Ps_Facetedsearch $module)
    {
        parent::__construct($module);

        $this->formModifier = new FormModifier($module->getContext());
        $this->dataProvider = new FormDataProvider($module->getDatabase());
    }

    const AVAILABLE_HOOKS = [
        'actionFeatureSave',
        'actionFeatureDelete',
        'displayFeatureForm',
        'displayFeaturePostProcess',
        'actionFeatureFormBuilderModifier',
        'actionAfterCreateFeatureFormHandler',
        'actionAfterUpdateFeatureFormHandler',
    ];

    /**
     * Hook for modifying feature form formBuilder
     *
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     */
    public function actionFeatureFormBuilderModifier(array $params)
    {
        $this->formModifier->modify($params['form_builder'], $this->dataProvider->getData($params));
    }

    /**
     * Hook after create feature.
     *
     * @since PrestaShop 1.7.7.0
     *
     * @param array $params
     */
    public function actionAfterCreateFeatureFormHandler(array $params)
    {
        $this->save($params['id'], $params['form_data']);
    }

    /**
     * Hook after update feature.
     *
     * @since PrestaShop 1.7.7.0
     *
     * @param array $params
     */
    public function actionAfterUpdateFeatureFormHandler(array $params)
    {
        $this->save($params['id'], $params['form_data']);
    }

    /**
     * Hook after delete a feature
     *
     * @param array $params
     */
    public function actionFeatureDelete(array $params)
    {
        if (empty($params['id_feature'])) {
            return;
        }

        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature
            WHERE `id_feature` = ' . (int) $params['id_feature']
        );
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Hook post process feature
     *
     * @param array $params
     */
    public function displayFeaturePostProcess(array $params)
    {
        $this->module->checkLinksRewrite($params);
    }

    /**
     * Hook feature form
     *
     * @param array $params
     */
    public function displayFeatureForm(array $params)
    {
        if (version_compare(_PS_VERSION_, '1.7.7.0') >= 0) {
            return;
        }

        $values = [];
        $isIndexable = $this->database->getValue(
            'SELECT `indexable` ' .
            'FROM ' . _DB_PREFIX_ . 'layered_indexable_feature ' .
            'WHERE `id_feature` = ' . (int) $params['id_feature']
        );

        // Request failed, force $isIndexable
        if ($isIndexable === false) {
            $isIndexable = true;
        }
        $result = $this->database->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang` ' .
            'FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value ' .
            'WHERE `id_feature` = ' . (int) $params['id_feature']
        );
        if ($result) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = [
                    'url_name' => $data['url_name'],
                    'meta_title' => $data['meta_title'],
                ];
            }
        }

        $this->context->smarty->assign([
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
            'is_indexable' => (bool) $isIndexable,
        ]);

        return $this->module->render('feature_form.tpl');
    }

    /**
     * After save feature
     *
     * @param array $params
     */
    public function actionFeatureSave(array $params)
    {
        if (empty($params['id_feature']) || Tools::getValue('layered_indexable') === false) {
            return;
        }

        $featureId = (int) $params['id_feature'];
        $formData = [
            'layered_indexable' => Tools::getValue('layered_indexable'),
        ];

        foreach (Language::getLanguages(false) as $language) {
            $langId = (int) $language['id_lang'];
            $seoUrl = Tools::getValue('url_name_' . $langId);
            $metaTitle = Tools::getValue('meta_title_' . $langId);

            if (empty($seoUrl) && empty($metaTitle)) {
                continue;
            }

            $formData['meta_title'][$langId] = $metaTitle;
            $formData['url_name'][$langId] = $seoUrl;
        }

        $this->save($featureId, $formData);
    }

    /**
     * Saves feature form.
     *
     * @param int $featureId
     * @param array $formData
     *
     * @since PrestaShop 1.7.7
     */
    private function save($featureId, array $formData)
    {
        $this->cleanLayeredIndexableTables($featureId);

        $this->database->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_feature
            (`id_feature`, `indexable`)
            VALUES (' . (int) $featureId . ', ' . (int) $formData['layered_indexable'] . ')'
        );

        $defaultLangId = (int) Configuration::get('PS_LANG_DEFAULT');
        $query = 'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value ' .
               '(`id_feature`, `id_lang`, `url_name`, `meta_title`) ' .
               'VALUES (%d, %d, \'%s\', \'%s\')';

        foreach (Language::getLanguages(false) as $language) {
            $langId = (int) $language['id_lang'];
            $metaTitle = pSQL($formData['meta_title'][$langId]);
            $seoUrl = $formData['url_name'][$langId];
            $name = $formData['name'][$langId] ?: $formData['name'][$defaultLangId];

            if (!empty($seoUrl)) {
                $seoUrl = pSQL(Tools::link_rewrite($seoUrl));
            }

            $this->database->execute(
                sprintf(
                    $query,
                    $featureId,
                    $langId,
                    $seoUrl,
                    $metaTitle
                )
            );
        }

        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Deletes from layered_indexable_feature and layered_indexable_feature_lang_value by feature id
     *
     * @param int $featureId
     */
    private function cleanLayeredIndexableTables($featureId)
    {
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature
            WHERE `id_feature` = ' . $featureId
        );
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value
            WHERE `id_feature` = ' . $featureId
        );
    }
}

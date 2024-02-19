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

use Language;
use PrestaShop\Module\FacetedSearch\Form\Attribute\FormDataProvider;
use PrestaShop\Module\FacetedSearch\Form\Attribute\FormModifier;
use Tools;

class Attribute extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'actionAttributeGroupDelete',
        'actionAttributeSave',
        'displayAttributeForm',
        'actionAttributePostProcess',
        // Hooks for migrated page
        'actionAttributeFormBuilderModifier',
        'actionAttributeFormDataProviderData',
        'actionAfterCreateAttributeFormHandler',
        'actionAfterUpdateAttributeFormHandler',
    ];

    /**
     * Hook for modifying attribute form formBuilder
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAttributeFormBuilderModifier(array $params)
    {
        $formModifier = new FormModifier($this->context->getTranslator());
        $formModifier->modify($params['form_builder']);
    }

    /**
     * Hook that provides extra data in the form.
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAttributeFormDataProviderData(array $params)
    {
        $formDataProvider = new FormDataProvider($this->database);
        $attributeData = $formDataProvider->getData($params);
        // Update data field in params which is passed by reference
        $params['data'] = array_merge($params['data'], $attributeData);
    }

    /**
     * Hook after creation form is handled in migrated page.
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAfterCreateAttributeFormHandler(array $params): void
    {
        $this->save(array_merge(['id_attribute' => $params['id']], $params['form_data']));
    }

    /**
     * Hook after edition form is handled in migrated page.
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAfterUpdateAttributeFormHandler(array $params): void
    {
        $this->save(array_merge(['id_attribute' => $params['id']], $params['form_data']));
    }

    /**
     * After save attribute
     *
     * @param array $params
     */
    public function actionAttributeSave(array $params)
    {
        if (empty($params['id_attribute'])) {
            return;
        }

        $formData = [
            'id_attribute' => (int) $params['id_attribute'],
        ];
        foreach (Language::getLanguages(false) as $language) {
            $langId = (int) $language['id_lang'];
            $seoUrl = Tools::getValue('url_name_' . $langId);
            if (!empty($seoUrl)) {
                $formData['url_name'][$langId] = $seoUrl;
            }
            $metaTitle = Tools::getValue('meta_title_' . $langId);
            if (!empty($metaTitle)) {
                $formData['meta_title'][$langId] = $metaTitle;
            }
        }
        $this->save($formData);
    }

    /**
     * After delete attribute
     *
     * @param array $params
     */
    public function actionAttributeGroupDelete(array $params)
    {
        if (empty($params['id_attribute'])) {
            return;
        }

        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value
            WHERE `id_attribute` = ' . (int) $params['id_attribute']
        );
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Post process attribute
     *
     * @param array $params
     */
    public function actionAttributePostProcess(array $params)
    {
        $this->module->checkLinksRewrite($params);
    }

    /**
     * Attribute form
     *
     * @param array $params
     */
    public function displayAttributeForm(array $params)
    {
        $values = [];

        if ($result = $this->database->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang`
            FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value
            WHERE `id_attribute` = ' . (int) $params['id_attribute']
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

        return $this->module->render('attribute_form.tpl');
    }

    /**
     * This is the common save method, the calling methods just need to format the form data appropriately
     * depending on the page being migrated or not.
     *
     * @param array $formData
     */
    private function save(array $formData): void
    {
        if (empty($formData['id_attribute'])) {
            return;
        }

        $attributeId = (int) $formData['id_attribute'];
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value
            WHERE `id_attribute` = ' . $attributeId
        );

        $landIds = array_unique(array_merge(array_keys($formData['meta_title'] ?? []), array_keys($formData['url_name'] ?? [])));
        foreach ($landIds as $langId) {
            $seoUrl = $formData['url_name'][$langId] ?? null;
            $metaTitle = $formData['meta_title'][$langId] ?? null;
            if (empty($seoUrl) && empty($metaTitle)) {
                continue;
            }

            $this->database->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_attribute_lang_value
                (`id_attribute`, `id_lang`, `url_name`, `meta_title`)
                VALUES (
                ' . $attributeId . ', ' . $langId . ',
                \'' . pSQL(Tools::str2url($seoUrl)) . '\',
                \'' . pSQL($metaTitle, true) . '\')'
            );
        }
        $this->module->invalidateLayeredFilterBlockCache();
    }
}

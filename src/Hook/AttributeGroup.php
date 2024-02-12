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
use PrestaShop\Module\FacetedSearch\Form\AttributeGroup\FormDataProvider;
use PrestaShop\Module\FacetedSearch\Form\AttributeGroup\FormModifier;
use Tools;

class AttributeGroup extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'actionAttributeGroupDelete',
        'actionAttributeGroupSave',
        'displayAttributeGroupForm',
        'displayAttributeGroupPostProcess',
        // Hooks for migrated page
        'actionAttributeGroupFormBuilderModifier',
        'actionAttributeGroupFormDataProviderData',
        'actionAfterCreateAttributeGroupFormHandler',
        'actionAfterUpdateAttributeGroupFormHandler',
    ];

    /**
     * Hook for modifying attribute group form formBuilder
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAttributeGroupFormBuilderModifier(array $params)
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
    public function actionAttributeGroupFormDataProviderData(array $params)
    {
        $formDataProvider = new FormDataProvider($this->database);
        $attributeGroupData = $formDataProvider->getData($params);
        // Update data field in params which is passed by reference
        $params['data'] = array_merge($params['data'], $attributeGroupData);
    }

    /**
     * Hook after creation form is handled in migrated page.
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAfterCreateAttributeGroupFormHandler(array $params): void
    {
        $this->save(array_merge(['id_attribute_group' => $params['id']], $params['form_data']));
    }

    /**
     * Hook after edition form is handled in migrated page.
     *
     * @since PrestaShop 9.0.0
     *
     * @param array $params
     */
    public function actionAfterUpdateAttributeGroupFormHandler(array $params): void
    {
        $this->save(array_merge(['id_attribute_group' => $params['id']], $params['form_data']));
    }

    /**
     * After save Attributes group
     *
     * @param array $params
     */
    public function actionAttributeGroupSave(array $params)
    {
        if (empty($params['id_attribute_group']) || Tools::getValue('layered_indexable') === false) {
            return;
        }

        $formData = [
            'id_attribute_group' => (int) $params['id_attribute_group'],
            'is_indexable' => (int) Tools::getValue('layered_indexable'),
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
     * After delete attribute group
     *
     * @param array $params
     */
    public function actionAttributeGroupDelete(array $params)
    {
        if (empty($params['id_attribute_group'])) {
            return;
        }

        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group
            WHERE `id_attribute_group` = ' . (int) $params['id_attribute_group']
        );
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value
            WHERE `id_attribute_group` = ' . (int) $params['id_attribute_group']
        );
        $this->module->invalidateLayeredFilterBlockCache();
    }

    /**
     * Post process attribute group
     *
     * @param array $params
     */
    public function displayAttributeGroupPostProcess(array $params)
    {
        $this->module->checkLinksRewrite($params);
    }

    /**
     * Attribute group form
     *
     * @param array $params
     *
     * @return string
     */
    public function displayAttributeGroupForm(array $params)
    {
        $values = [];
        $isIndexable = $this->database->getValue(
            'SELECT `indexable`
            FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group
            WHERE `id_attribute_group` = ' . (int) $params['id_attribute_group']
        );

        if ($result = $this->database->executeS(
            'SELECT `url_name`, `meta_title`, `id_lang` FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value
            WHERE `id_attribute_group` = ' . (int) $params['id_attribute_group']
        )) {
            foreach ($result as $data) {
                $values[$data['id_lang']] = ['url_name' => $data['url_name'], 'meta_title' => $data['meta_title']];
            }
        }

        $this->context->smarty->assign([
            'languages' => Language::getLanguages(false),
            'default_form_language' => (int) $this->context->controller->default_form_language,
            'values' => $values,
            'is_indexable' => (bool) $isIndexable,
        ]);

        return $this->module->render('attribute_group_form.tpl');
    }

    /**
     * This is the common save method, the calling methods just need to format the form data appropriately
     * depending on the page being migrated or not.
     *
     * @param array $formData
     */
    private function save(array $formData): void
    {
        if (empty($formData['id_attribute_group'])) {
            return;
        }

        $attributeGroupId = $formData['id_attribute_group'];

        // First clean all existing data
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group
            WHERE `id_attribute_group` = ' . $attributeGroupId
        );
        $this->database->execute(
            'DELETE FROM ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value
            WHERE `id_attribute_group` = ' . $attributeGroupId
        );

        $this->database->execute(
            'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_attribute_group (`id_attribute_group`, `indexable`)
VALUES (' . $attributeGroupId . ', ' . (int) $formData['is_indexable'] . ')'
        );

        $landIds = array_unique(array_merge(array_keys($formData['meta_title'] ?? []), array_keys($formData['url_name'] ?? [])));
        foreach ($landIds as $langId) {
            $seoUrl = $formData['url_name'][$langId] ?? null;
            $metaTitle = $formData['meta_title'][$langId] ?? null;
            if (empty($seoUrl) && empty($metaTitle)) {
                continue;
            }

            $this->database->execute(
                'INSERT INTO ' . _DB_PREFIX_ . 'layered_indexable_attribute_group_lang_value
                (`id_attribute_group`, `id_lang`, `url_name`, `meta_title`)
                VALUES (
                ' . $attributeGroupId . ', ' . $langId . ',
                \'' . pSQL(Tools::str2url($seoUrl)) . '\',
                \'' . pSQL($metaTitle, true) . '\')'
            );
        }
        $this->module->invalidateLayeredFilterBlockCache();
    }
}

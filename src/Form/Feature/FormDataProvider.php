<?php
/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShop\Module\FacetedSearch\Form\Feature;

use Db;
use PrestaShopDatabaseException;

/**
 * Provides form data
 */
class FormDataProvider
{
    /**
     * @var Db
     */
    private $database;

    public function __construct(Db $database)
    {
        $this->database = $database;
    }

    /**
     * Fills form data
     *
     * @param array $params
     *
     * @return array
     *
     * @throws PrestaShopDatabaseException
     */
    public function getData(array $params)
    {
        $defaultUrl = [];
        $defaultMetaTitle = [];
        $isIndexable = true;

        // if params contains id, gets data for edit form
        if (!empty($params['id'])) {
            $featureId = (int) $params['id'];

            // returns false if request failed.
            $queryIndexable = $this->database->getValue(
                'SELECT `indexable` ' .
                'FROM ' . _DB_PREFIX_ . 'layered_indexable_feature ' .
                'WHERE `id_feature` = ' . $featureId
            );

            $isIndexable = (bool) $queryIndexable;
            $result = $this->database->executeS(
                'SELECT `url_name`, `meta_title`, `id_lang` ' .
                'FROM ' . _DB_PREFIX_ . 'layered_indexable_feature_lang_value ' .
                'WHERE `id_feature` = ' . $featureId
            );

            if (!empty($result) && is_array($result)) {
                foreach ($result as $data) {
                    $defaultUrl[$data['id_lang']] = $data['url_name'];
                    $defaultMetaTitle[$data['id_lang']] = $data['meta_title'];
                }
            }
        }

        return [
            'url' => $defaultUrl,
            'meta_title' => $defaultMetaTitle,
            'is_indexable' => $isIndexable,
        ];
    }
}

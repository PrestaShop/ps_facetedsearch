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

namespace PrestaShop\Module\FacetedSearch\Filters;

use Db;
use PrestaShop\PrestaShop\Core\Product\Search\ProductSearchQuery;

/**
 * Class responsible for providing filters configured for current search query
 */
class Provider
{
    /**
     * @var array
     */
    private $filters = [];

    /**
     * @var Db
     */
    private $database;

    public function __construct(Db $database)
    {
        $this->database = $database;
    }

    /**
     * Get filters for current search query
     *
     * @param ProductSearchQuery $query
     * @param int $idShop
     *
     * @return array Filters
     */
    public function getFiltersForQuery(ProductSearchQuery $query, int $idShop)
    {
        if (empty($this->filters)) {
            $this->filters = $this->database->executeS(
            'SELECT type, id_value, filter_show_limit, filter_type FROM ' . _DB_PREFIX_ . 'layered_category
            WHERE controller = \'' . $query->getQueryType() . '\'
            AND id_category = ' . ($query->getQueryType() == 'category' ? (int) $query->getIdCategory() : 0) . '
            AND id_shop = ' . $idShop . '
            GROUP BY `type`, id_value ORDER BY position ASC'
            );
        }

        return $this->filters;
    }
}

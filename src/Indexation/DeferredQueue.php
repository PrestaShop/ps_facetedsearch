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

namespace PrestaShop\Module\FacetedSearch\Indexation;

use AdminController;
use Configuration;
use Context;
use Db;
use PrestaShopLogger;
use Ps_Facetedsearch;
use Shop;
use Throwable;

class DeferredQueue
{
    const TABLE_NAME = 'faceted_deferred_queue';
    const DEFAULT_LIMIT = 50;
    const MAX_ATTEMPTS = 3;
    const LOCK_TIMEOUT_MINUTES = 15;

    /**
     * @var Db
     */
    private $database;

    public function __construct(Db $database = null)
    {
        $this->database = $database ?: Db::getInstance();
    }

    /**
     * Check if deferred indexation is enabled by configuration
     *
     * @return bool
     */
    public function isDeferredActive()
    {
        return (bool) Configuration::get('PS_LAYERED_INDEX_DEFERRED');
    }

    /**
     * Determine if current execution context should defer indexation
     * (Back office requests, bulk imports, webservice requests)
     *
     * @return bool
     */
    public function shouldDefer()
    {
        if (!$this->isDeferredActive()) {
            return false;
        }

        if (defined('_PS_ADMIN_DIR_') || defined('_PS_WEBSERVICE_SERVER_')) {
            return true;
        }

        $context = Context::getContext();
        if (isset($context->controller) && $context->controller instanceof AdminController) {
            return true;
        }

        return false;
    }

    /**
     * Install database queue table
     *
     * @return bool
     */
    public function install()
    {
        return (bool) $this->database->execute(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '` (
                `id_product` INT(10) UNSIGNED NOT NULL,
                `id_shop` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `locked_at` DATETIME NULL DEFAULT NULL,
                `attempts` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_product`, `id_shop`),
                KEY `locked_at` (`locked_at`),
                KEY `attempts` (`attempts`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        );
    }

    /**
     * Uninstall database queue table
     *
     * @return bool
     */
    public function uninstall()
    {
        return (bool) $this->database->execute(
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_NAME . '`'
        );
    }

    /**
     * Enqueue a single product for deferred indexing
     *
     * @param int $idProduct
     * @param int $idShop
     *
     * @return bool
     */
    public function enqueue($idProduct, $idShop = 0)
    {
        $idProduct = (int) $idProduct;
        $idShop = (int) $idShop;

        if ($idProduct <= 0) {
            return false;
        }

        return (bool) $this->database->execute(
            'INSERT INTO `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                (`id_product`, `id_shop`, `created_at`, `locked_at`, `attempts`)
            VALUES
                (' . $idProduct . ', ' . $idShop . ', NOW(), NULL, 0)
            ON DUPLICATE KEY UPDATE `attempts` = 0, `locked_at` = NULL'
        );
    }

    /**
     * Enqueue multiple products in batches
     *
     * @param array $idProducts
     * @param int $idShop
     *
     * @return bool
     */
    public function enqueueMultiple(array $idProducts, $idShop = 0)
    {
        $idProducts = array_filter(array_map('intval', $idProducts));
        if (empty($idProducts)) {
            return false;
        }

        $idShop = (int) $idShop;
        $chunks = array_chunk(array_unique($idProducts), 500);

        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $idProduct) {
                $values[] = '(' . $idProduct . ', ' . $idShop . ', NOW(), NULL, 0)';
            }

            $success = $this->database->execute(
                'INSERT INTO `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                    (`id_product`, `id_shop`, `created_at`, `locked_at`, `attempts`)
                VALUES ' . implode(', ', $values) . '
                ON DUPLICATE KEY UPDATE `attempts` = 0, `locked_at` = NULL'
            );

            if (!$success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Release stale locks from previous timeouts or crashed cron workers
     *
     * @param int $timeoutMinutes
     *
     * @return bool
     */
    public function releaseStaleLocks($timeoutMinutes = self::LOCK_TIMEOUT_MINUTES)
    {
        $timeoutMinutes = max(1, (int) $timeoutMinutes);

        return (bool) $this->database->execute(
            'UPDATE `' . _DB_PREFIX_ . self::TABLE_NAME . '`
            SET `locked_at` = NULL
            WHERE `locked_at` IS NOT NULL
                AND `locked_at` < DATE_SUB(NOW(), INTERVAL ' . $timeoutMinutes . ' MINUTE)
                AND `attempts` < ' . self::MAX_ATTEMPTS
        );
    }

    /**
     * Process queued items
     *
     * @param Ps_Facetedsearch $module
     * @param int $limit
     *
     * @return int Count of processed products
     */
    public function process(Ps_Facetedsearch $module, $limit = self::DEFAULT_LIMIT)
    {
        $limit = max(1, (int) $limit);
        $this->releaseStaleLocks();

        $rows = $this->database->executeS(
            'SELECT `id_product`, `id_shop`
            FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '`
            WHERE `locked_at` IS NULL
                AND `attempts` < ' . self::MAX_ATTEMPTS . '
            ORDER BY `created_at` ASC
            LIMIT ' . $limit
        );

        if (empty($rows)) {
            return 0;
        }

        $processed = 0;
        $invalidateCache = false;

        Shop::setContext(Shop::CONTEXT_ALL);

        foreach ($rows as $row) {
            $idProduct = (int) $row['id_product'];
            $idShop = (int) $row['id_shop'];

            $this->database->execute(
                'UPDATE `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                SET `locked_at` = NOW(), `attempts` = `attempts` + 1
                WHERE `id_product` = ' . $idProduct . ' AND `id_shop` = ' . $idShop
            );

            try {
                $module->indexProductPrices($idProduct);
                $module->indexAttributes($idProduct);

                $this->database->execute(
                    'DELETE FROM `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                    WHERE `id_product` = ' . $idProduct . ' AND `id_shop` = ' . $idShop
                );

                ++$processed;
                $invalidateCache = true;
            } catch (Throwable $exception) {
                $this->database->execute(
                    'UPDATE `' . _DB_PREFIX_ . self::TABLE_NAME . '`
                    SET `locked_at` = NULL
                    WHERE `id_product` = ' . $idProduct . ' AND `id_shop` = ' . $idShop
                );

                PrestaShopLogger::addLog(
                    'ps_facetedsearch deferred queue processing failed for product ' . $idProduct . ': ' . $exception->getMessage(),
                    3
                );
            }
        }

        if ($invalidateCache) {
            $module->invalidateLayeredFilterBlockCache();
        }

        return $processed;
    }
}

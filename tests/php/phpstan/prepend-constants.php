<?php
/**
 * Defines constants required by PrestaShop config (e.g. defines_uri.inc.php) when running
 * PHPStan outside a full request. Loaded via auto_prepend_file in CI for 9.x jobs.
 */
if (!defined('_THEME_NAME_')) {
    define('_THEME_NAME_', 'classic');
}
if (!defined('__PS_BASE_URI__')) {
    define('__PS_BASE_URI__', '/');
}

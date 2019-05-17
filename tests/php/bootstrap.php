<?php

// FacetedSearch autoloader
require __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/FacetedSearch/MockProxy.php';

define('_DB_PREFIX_', 'ps_');

// Fake pSQL function
function pSQL($string, $htmlOK = false)
{
    return $string;
}

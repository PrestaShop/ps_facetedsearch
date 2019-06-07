<?php

// FacetedSearch autoloader
require __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/FacetedSearch/MockProxy.php';
require_once __DIR__ . '/FacetedSearch/Interface/WidgetInterface.php';
require_once __DIR__ . '/FacetedSearch/Interface/FacetsRendererInterface.php';
require_once __DIR__ . '/FacetedSearch/Interface/ProductSearchProviderInterface.php';

define('_DB_PREFIX_', 'ps_');
define('_PS_VERSION_', 'FacetedSearchVersion');

// Fake pSQL function
function pSQL($string, $htmlOK = false)
{
    return $string;
}

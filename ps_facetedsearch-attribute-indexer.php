<?php

include dirname(__FILE__).'/../../config/config.inc.php';
include dirname(__FILE__).'/ps_facetedsearch.php';

if (substr(Tools::encrypt('ps_facetedsearch/index'), 0, 10) != Tools::getValue('token') || !Module::isInstalled('ps_facetedsearch')) {
    die('Bad token');
}

$psFacetedsearch = new Ps_Facetedsearch();
echo $psFacetedsearch->indexAttribute();

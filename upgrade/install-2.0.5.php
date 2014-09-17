<?php

if (!defined('_PS_VERSION_'))
	exit;

function upgrade_module_2_0_5($object)
{
	return Configuration::updateValue('PS_LAYERED_FILTER_PRICE_ROUNDING', 1);
}

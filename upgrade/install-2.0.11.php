<?php

if (!defined('_PS_VERSION_'))
	exit;

function upgrade_module_2_0_11($object)
{
	$query = 'ALTER TABLE `'._DB_PREFIX_.'layered_product_attribute` DROP PRIMARY KEY';
	$return = Db::getInstance()->execute($query);

	$query = 'ALTER TABLE `'._DB_PREFIX_.'layered_product_attribute` ADD PRIMARY KEY (`id_attribute`, `id_product`, `id_shop`)';
	$return &= Db::getInstance()->execute($query);

	$query = 'ALTER TABLE `'._DB_PREFIX_.'layered_product_attribute` DROP KEY `id_attribute_group`';
	$return &= Db::getInstance()->execute($query);

	$query = 'ALTER TABLE `'._DB_PREFIX_.'layered_product_attribute` ADD UNIQUE KEY `id_attribute_group` (`id_attribute_group`,`id_attribute`,`id_product`,`id_shop`)';
	$return &= Db::getInstance()->execute($query);

	return $return;
}

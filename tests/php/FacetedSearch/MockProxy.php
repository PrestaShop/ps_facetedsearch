<?php
/**
 * 2007-2019 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
class MockProxy
{
    protected static $mock;

    /**
     * Set static expectations
     *
     * @param mixed $mock
     */
    public static function setStaticExpectations($mock)
    {
        static::$mock = $mock;
    }

    /**
     * Any static calls we get are passed along to self::$mock. public static
     *
     * @param string $name
     * @param mixed $args
     *
     * @return mixed
     */
    public static function __callStatic($name, $args)
    {
        return call_user_func_array(
            [static::$mock, $name],
            $args
        );
    }
}

class StockAvailable extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Context extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Db extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Configuration extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Tools extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Category extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;

    public $id = null;
}

class Group extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

class Manufacturer extends MockProxy
{
    // Redeclare to use this instead MockProxy::mock
    protected static $mock;
}

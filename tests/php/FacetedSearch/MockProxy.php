<?php

class MockProxy
{
    static protected $mock;

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

class StockAvailable extends MockProxy {
    // Redeclare to use this instead MockProxy::mock
    static protected $mock;
}

class Context extends MockProxy {
    // Redeclare to use this instead MockProxy::mock
    static protected $mock;
}

class Db extends MockProxy {
    // Redeclare to use this instead MockProxy::mock
    static protected $mock;
}

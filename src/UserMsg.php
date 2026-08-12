<?php

namespace PbClasses;

class UserMsg
{

    protected static $messageArr = array();

    public static function push($v)
    {
        array_push(self::$messageArr, $v);
    }

    public static function getAll()
    {
        return self::$messageArr;
    }

    public static function merge($arr)
    {
        self::$messageArr = array_merge(self::$messageArr, $arr);
    }
}

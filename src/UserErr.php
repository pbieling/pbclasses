<?php

namespace PbClasses;

class UserErr
{

    protected static $errorArr = [];

    public static function push($v)
    {
        array_push(self::$errorArr, $v);
    }

    public static function getAll()
    {
        return self::$errorArr;
    }

    public static function merge($arr)
    {
        self::$errorArr = array_merge(self::$errorArr, $arr);
    }
}

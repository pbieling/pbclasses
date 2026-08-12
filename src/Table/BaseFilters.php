<?php

namespace PbClasses\Table;

/**
 * @author Peter Bieling - 2016-11-02
 */
class BaseFilters {

    public static function phpPassword($value) {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    public static function insertTimestamp($value = null) {
        return date('Y-m-d H:i:s');
    }

    public static function germanDate2Sql($value) {
        $dateArr = explode('.', $value);
        return $dateArr[2] . '-' . $dateArr[1] . '-' . $dateArr[0];
    }

}

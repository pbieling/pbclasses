<?php

namespace PbClasses\Util;

/**
 *
 * @author Bieling Peter - 2023-08-14
 * Apply one or more functions on the values of arrays to modify, escape or sanitze values
 */
class Filter {

    protected static $filterArr;

    public static function assocArr($arr, $filters = 'SPECIAL_CHARS') {
        $backArr = [];
        self::setFilters($filters);
        foreach ($arr as $key => $val) {
            $backArr[$key] = self::applyFilters($val);
        }
        return $backArr;
    }

    public static function numericArr($arr, $filters = 'SPECIAL_CHARS') {
        $backArr = [];
        self::setFilters($filters);
        for ($i = 0; $i < count($arr); $i++) {
            $backArr[$i] = self::applyFilters($arr[$i]);
        }
        return $backArr;
    }

    public static function str($str, $filters = 'SPECIAL_CHARS') {
        self::setFilters($filters);
        return self::applyFilters($str);
    }

    protected static function setFilters($filters) {
        self::$filterArr = []; //Reset
        $arr = explode(',', $filters);
        foreach ($arr as $sg) {
            $tmp = trim($sg);
            if ($tmp === '') {
                continue;
            }
            self::$filterArr[] = strtoupper($tmp);
        }
    }

    protected static function applyFilters($val) {
        foreach (self::$filterArr as $sgFilter) {
            $val = self::applySgFilter($val, $sgFilter);
        }
        return $val;
    }

    protected static function applySgFilter($val, $sgFilter) {
        /* Könnte sein, dass auch der Leerstring verändert werden soll, bei weiteren Funkttionen
          if ($val === '') {
          return $val;
          }
         * 
         */

        $filterArr = \explode(':', $sgFilter);
        $fil = $filterArr[0];
        $flag = (isset($filterArr[1])) ? $filterArr[1] : null;

        switch ($fil) {
            case 'SPECIAL_CHARS':
            case 'HTMLSPECIALCHARS': //alias
                return htmlspecialchars($val);
            case 'TRIM':
                return trim($val);
            case 'ADDSLASHES':
                return addslashes($val);
            case 'STRTOUPPER':
                return strtoupper($val);
            case 'STRTOLOWER':
                return strtolower($val);
            case 'UTF8_DECODE':
                return utf8_decode($val);
            case 'UTF8_ENCODE':
                return utf8_encode($val);
            case 'URLDECODE':
                return urldecode($val);
            case 'URLENCODE':
                return urlencode($val);
            case 'CP850_TO_UTF8' :
                //return iconv("CP850","UTF-8",$val); 
                try {
                    return \mb_convert_encoding($val, 'UTF-8', 'CP850');
                } catch (\Exception $exc) {
                    \trigger_error('not converted: ' . $val . ' in ' . __FILE__ . ' : ' . __LINE__);
                    return $val;
                }


            case 'UTF8_TO_CP850' :
                //return iconv("UTF-8","CP850",$val);    
                try {
                    return \mb_convert_encoding($val, 'CP850', 'UTF-8');
                } catch (\Exception $exc) {
                    \trigger_error('not converted: ' . $val . ' in ' . __FILE__ . ' : ' . __LINE__);
                    return $val;
                }

            default:
                if (!$flag) {
                    //new \PbClasses\Debug\Logging(constant($fil), __FILE__, __LINE__);
                    return filter_var($val, constant($fil));
                } else {
                    //@todo: Bitmask mit mehreren Konstanten
                    return filter_var($val, constant($fil), constant($flag));
                }
        }
    }
}

<?php
namespace PbClasses\Module;
/**
 * Provides the static methods for MConf-classes  encapsulated in modules.
 * For example:
 * 
 *      class MConf extends \PbClasses\Module\AbstractConf {
           protected static $config = array();
        }
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 25.01.2019, 14:12:37
 */
abstract class AbstractConf {

   public static function set($k, $v) {
        static::$config[$k] = $v;
    }

    public static function get($k) {
        if (\is_string($k)) {
            $k = array($k);
        }
        $tmpConf = & static::$config;
        foreach ($k as $index) {
            if (!isset($tmpConf[$index])) {
                return null;
            }
            $tmpConf = & $tmpConf[$index];
        }
        return $tmpConf;
    }

    public static function init($a) {
        static::$config = $a;
    }

    public static function merge($a) {
        static::$config = \array_merge(static::$config, $a);
    }

    public static function getAll() {
        return static::$config;
    }

    public static function addKV($confKey, $k, $v) {
        if (!isset(static::$config[$confKey])) {
            throw new \Exception($confKey . ' is not defined');
        }
        if (!is_array(static::$config[$confKey])) {
            throw new \Exception($confKey . ' is no array');
        }
        static::$config[$confKey][$k] = $v;
    }

    public static function mergeKV($confKey, $kvArr) {
        if (!isset(static::$config[$confKey])) {
            throw new \Exception($confKey . ' is not defined');
        }
        if (!is_array(static::$config[$confKey])) {
            throw new \Exception($confKey . ' is no array');
        }
        static::$config[$confKey] = \array_merge(static::$config[$confKey], $kvArr);
    }

}

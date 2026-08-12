<?php
namespace PbClasses\Util;

/**
 * Adds or removes trailing slashes.
 *
 * @author Bieling Peter - 2022-11-09
 */
class Slash {
    /**
     * 
     * @param string $path
     * @return string
     */
    
    public static function add($path) {
        if (substr($path, -1) === '/') {
            return $path;
        }
        return $path . '/';
    }
    
    /**
     * 
     * @param string $path
     * @return string
     */
    public static function remove($path) {
        if (substr($path, -1) === '/') {
            return substr($path, 0, -1);
        }
        return $path;
    }
}

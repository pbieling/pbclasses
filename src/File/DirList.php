<?php

namespace PbClasses\File;

/**
 *  * @author Bieling Peter - 01.06.2024
 */
class DirList {

    public static function get($dir) {
        if (!is_dir($dir)) {
            return false;
        }

        $dirArr = \scandir($dir);
        $retArr = [];
        foreach ($dirArr as $entry) {
            if (!is_dir($dir . '/' . $entry)) {
                continue;
            }
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            //label (dirname as key) => full path
            $retArr[$entry] = $dir . '/' . $entry;
        }
       
        return $retArr;
    }

}

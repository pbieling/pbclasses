<?php

namespace PbClasses\File;

/**
 * Description of FileList
 *
 * @author Bieling Peter
 */
class FileList {

    public static function get($dir, $extension = null) {
        if (!is_dir($dir)) {
            return false;
        }

        $fileArr = \scandir($dir);
        $retArr = [];
        foreach ($fileArr as $file) {
            if (!is_file($dir . '/' . $file)) {
                continue;
            }

            //Each extension
            if ($extension) {
                $partArr = pathinfo($file);
                //new \PbClasses\Debug\Logging($partArr, __FILE__, __LINE__);
                if ($partArr['extension'] !== $extension) {
                    continue;
                }
            }

            //label (Filename as key) => full path
            $retArr[$file] = $dir . '/' . $file;
        }
       
        return $retArr;
    }

}

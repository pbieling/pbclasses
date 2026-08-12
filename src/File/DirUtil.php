<?php

namespace PbClasses\File;

/**
 * Creates missing directiories wich are needet to save a file. (Only creates the Dirs)
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 18.12.2017, 08:47:12
 */
class DirUtil {

    public static function makeMissingDirs($basePath, $filePath, $permissions = 0755) {
        if (!\is_dir($basePath)) {
            return false;
        }
        $base = \rtrim(\str_replace('\\', '/', $basePath), '/');
        $filePathArr = \explode('/', \str_replace('\\', '/', $filePath));

        if (!count($filePathArr)) {
            return false;
        }

        if (count($filePathArr) == 1) {
            return true;
        }

        \array_pop($filePathArr);
        
        $currDir = $base;
        foreach ($filePathArr as $dir) {
            $currDir .= '/' . $dir;
            if (\is_dir($currDir)) {
                continue;
            }
            if (!\mkdir($currDir, $permissions)) {
                return false;
            }
        }
        return true;
    }

}

<?php
namespace PbClasses\Util;

/**
 * @author Peter Bieling - info@media-palette.de
 * Created on : 06.09.2018, 17:09:31
 */
class UniqueId {

    public static function get($str = 'uid') {
        $uuid = \md5(\uniqid($str, true));
        //5f527c13-4bf4-42bd-a191-ab2a3af7ce4b

        return substr($uuid, 0, 8) . '-' .
                substr($uuid, 8, 4) . '-' .
                substr($uuid, 12, 4) . '-' .
                substr($uuid, 16, 4) . '-' .
                substr($uuid, 20);
    }

}

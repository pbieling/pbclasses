<?php
namespace PbClasses\Debug;

class VarDump {

    public static function get($var) {
        \ob_start();
        \var_dump($var);
        return \ob_get_clean();
    }
}

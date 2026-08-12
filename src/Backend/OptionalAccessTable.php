<?php

namespace PbClasses\Backend;

use PbClasses\Conf;
/**
 * Fügt das Template für die Access-Tabelle ein, sofern in der Projektkonfiguration
 * 
 */
class OptionalAccessTable {
    public function __construct() {
    if (Conf::get('access_tables')) {
           //Template wird eingefügt, wenn 'access_tables' definiert sind.
           $accBlock = Conf::get('c')->getTpl('autoload_access_table');
       } else {
           $accBlock = '';
       }   
        Conf::addKV('cSeRe', 'AUTOLAD_ACCESS_TABLE', $accBlock);
    }
}

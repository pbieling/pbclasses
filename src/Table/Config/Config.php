<?php
namespace PbClasses\Table\Config;
use PbClasses\Conf;

/**
 * 
 *
 * @author Bieling Peter
 */
class Config {
    /**
     * 
     * @param strubg $table
     * @param mixed $key  //null = alle Konfigurationswerte abfragen
     * @param string $keyAttrName //Spaltenname Schlüssel
     * @param string $valAttrName //Spaltenname Wert
     * @return mixed //wenn $key === null -> String sonst Array (Key-Value-Paare)
     */
    public static function get($table, $key = null, $keyAttrName = 'ConfKey', $valAttrName = 'ConfVal') {
        $db = Conf::get('db');
        
        $what = array($keyAttrName, $valAttrName);
        $where = array('Hide' => 0);
        if ($key !== null) {
            $where[$keyAttrName] = $key;
        }
        
        
        
        $condition = 'ORDER by Ord';
        $res = $db->select($table, $what, $where, '', $condition);
        if (empty($res)) {
            return null;
        }
        
        $backArr = array();
        foreach ($res as $row) {
            $backArr[$row[0]] = $row[1];
        }
        //Wenn ein bestimmter Wert abgefragt wird, diesen zurückgeben
        if ($key !== null) {
            return $backArr[$key];
        }
        
        
        return $backArr;
        
    }
}

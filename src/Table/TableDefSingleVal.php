<?php
namespace PbClasses\Table;
use PbClasses\Conf;

/**
 * @author Bieling Peter - 2022-11-29
 * 
 * Klasse für das Tabellensystem.
 * 
 * Liefert das Label für den aktuellen Wert (Schlüssel) aus einer Definitionstabelle. 
 * Beispieltabelle:
 * CREATE TABLE `av_organisation` (
  `ID` int NOT NULL,
  `OrgIndex` int UNSIGNED NOT NULL DEFAULT '0',
  `OrgName` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `DefComment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Ord` int UNSIGNED NOT NULL DEFAULT '0',
  `Hide` tinyint(1) NOT NULL DEFAULT '0',
  `CrTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 * 
 * 
 * 
 *  'provide_dyn_data' => array(
        //...
        'organisation_label' =>  ['\\PbClasses\\Table\\TableDefSingleVal', 'av_organisation', 'OrgIndex', 'OrgName', '-'], 
        //...
    ),
    

 */

class TableDefSingleVal {
    public function __construct($placeholder, & $sr, & $tdArr, & $conf, $paramArr) {
      
        list($table, $fieldname, $key, $label, $returnOnEmpty) = $paramArr;
        $value = $tdArr[$fieldname];
        
        // selectSingleVal($table, $sel = "", $where = "", $returnOnEmpty = null) {
        $sr[$placeholder] = Conf::get('db')->selectSingleVal($table, $label, [$key => $value], $returnOnEmpty);
    }

}

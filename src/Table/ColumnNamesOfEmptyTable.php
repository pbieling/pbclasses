<?php

namespace PbClasses\Table;

/**
 * https://stackoverflow.com/questions/10854097/select-on-empty-table-but-still-get-column-names
 * 
 * SELECT tbl.* 
  FROM (SELECT 1) AS ignore_me
  LEFT JOIN your_table AS tbl
  ON 1 = 1
  LIMIT 1
 * 
 * **************************************
 * 
 * SELECT DatumVon AS DV, DatumBis AS DB
  FROM (

  SELECT 1
  ) AS ignore_me
  LEFT JOIN `bgsonline__termine` ON 1 =1
  LIMIT 1
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 31.01.2019, 11:08:29
 */
class ColumnNamesOfEmptyTable {

    public static function get($db, $table, $what) {
        $extendedTable = " (SELECT 'x1x' ) AS ignore_me LEFT JOIN $table ON 1 = 1";
        $condition = 'LIMIT 1';

        try {
            $res = $db->selectAssoc($extendedTable, $what, '', '', $condition);
        } catch (Exception $exc) {
            $err = $exc->getMessage();
            new \PbClasses\Exception\Logging($err, __FILE__, __LINE__);
            return false;
        }
        unset($res[0]['x1x']); //Überflüssiges Feld wieder entfernen
        return array_keys($res[0]);
    }

    public static function getFromFirst($db, $table, $what) {
        $condition = 'LIMIT 1';
        try {
            $res = $db->selectAssoc($table, $what, '', '', $condition);
        } catch (Exception $exc) {
            $err = $exc->getMessage();
            new \PbClasses\Exception\Logging($err, __FILE__, __LINE__);
            return false;
        }
        return array_keys($res[0]);
    }

}

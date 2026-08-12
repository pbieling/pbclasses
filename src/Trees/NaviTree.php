<?php

/**
 * http://www.phpbar.de/w/Baumstruktur_aus_DB_in_Array_abbilden
  2   * gegeben sei eine Tabelle wie folgt:
  3   *
  4   * id | parent | name
  5   * -------------------
  6   *  1 |      0 | A
  7   *  2 |      0 | B
  8   *  3 |      0 | C
  9   *  4 |      1 | Aa
  10  *  5 |      1 | Ab
  11  *  6 |      2 | Ba

  13
  14
  15  * @var array $items beinhaltet alle Elemente
  16
  17 $items = array();
  18
  19 $result = mysql_query('SELECT * FROM `table` ORDER BY `parent`');
  20
  21 while (($row = mysql_fetch_assoc($result)) !== false) {
  22     // wir wollen die IDs als Zahl und nicht als Zeichenkette
  23     $row['id']     = (int) $row['id'];
  24     $row['parent'] = (int) $row['parent'];
  25
  26     // ablegen in unserem Array
  27     $items[$row['id']] = $row;
  28
  29     // verlinken mit dem Elternteil
  30     $items[$row['parent']]['childs'][$row['id']] =& $items[$row['id']];
  31 }
  32
  33 // den gesamten Baum ausgeben
  34 print_r($items[0]);
  35
  36
  37 // nur den Ast mit der ID 1 ausgeben
  38 print_r($items[1]);
 */
/**

 * @author Peter Bieling and
 * http://www.phpbar.de/w/Baumstruktur_aus_DB_in_Array_abbilden
 * 
 * Anmerkung (p.b) Der gesamte Baum ist in der Darstellung sehr gruoß es werden aber nur die Referenzen
 * gespeichert.
 * Vorteile:    Nur eine DB-Abfrage
 *              Leichter Zugriff auf die Teilbäume ohne den Baum durchsuchen zu müssen
 */

namespace PbClasses\Trees;

use PbClasses\Conf;
use \PbClasses\Access;
 
class NaviTree {

   
    protected $db;
    protected $table;

    public function __construct($table, $dbHandle = false) {
        $this->db = ($dbHandle) ? $dbHandle : \PbClasses\Conf::get('db');
        $this->table = $table;
    }

    public function getNaviRows($pageIndex = 0) {
        if (!Conf::get('site_navi_tree')) {
            if (!$this->setNaviTree()) {
                return array();
            }
        }
        return Conf::get('site_navi_tree')[$pageIndex]['nextlevel'];
    }

    /**
     * Der volle Navigationsbaum wird eventuell noch für Unternavigationen (Sidenavi etc.) gebraucht
     * 
     * @return boolean
     */
    protected function setNaviTree() {
        $what = " *, 0 as level, '' as parents, '' as nextlevel";
        $where = "Hide = 0";
        $condition = 'ORDER BY ParentID, Ord';

        if (isset(Conf::get('backnavi_row')['AccLevel'])) {
            if (!isset($_SESSION['frg'])) {
                $frg = -1;
            } else {
                $frg = $_SESSION['frg'];
            }
            $where .= " AND '$frg' >= AccLevel";
        }
        // \PbClasses\Develop::test($where, __LINE__, 1);

        $accObj = (Conf::get('access_tables')) ? new Access() : null;
        
        
        $res = $this->db->selectAssoc($this->table, $what, $where, '', $condition);
        // \PbClasses\Develop::test($res, __LINE__, 1);

        if (!$res) {
            return false;
        }

        $items = array();
        foreach ($res as $row) {
           // new \PbClasses\Debug\Logging($row, __FILE__, __LINE__);
            $row['ID'] = (int) $row['ID'];
            if ($accObj && !$accObj->hasAccess($_SESSION['uid'],  $row['ID'])) {
                continue;
            }
            $row['ParentID'] = (int) $row['ParentID'];
            //Es ist nützlich, z.B. für die Darstellung, wenn jedes Element die Verschachtelungstiefe
            //kennt sowie seine Eltern-Elemente (parents)
            if ($row['ParentID'] && isset($items[$row['ParentID']]) && isset($items[$row['ParentID']]['parents'])) {
                //Am Anfang (leerer Pfad) kein Komma
                $separator = $items[$row['ParentID']]['parents'] ? ',' : '';
                $row['parents'] = $items[$row['ParentID']]['parents'] . $separator . $row['ParentID'];
                $row['level'] = $items[$row['ParentID']]['level'] + 1;
            }
            $items[$row['ID']] = $row;
            if (!isset($items[$row['ParentID']]) || !\is_array($items[$row['ParentID']])) {
                $items[$row['ParentID']] = array('nextlevel' => array());
            }
            if (!isset($items[$row['ParentID']]['nextlevel']) || !\is_array($items[$row['ParentID']]['nextlevel'])) {
                $items[$row['ParentID']]['nextlevel'] = array();
            }
            $items[$row['ParentID']]['nextlevel'][$row['ID']] = & $items[$row['ID']];
        }
        $refItems = & $items; //Todo: Performance testen
        Conf::set('site_navi_tree', $refItems);
        
      
        return true;
    }

}

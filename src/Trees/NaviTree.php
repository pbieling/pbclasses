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
 * Anmerkung (p.b) Der gesamte Baum ist in der Darstellung sehr groß es werden aber nur die Referenzen
 * gespeichert.
 * Vorteile:    Nur eine DB-Abfrage
 *              Leichter Zugriff auf die Teilbäume ohne den Baum durchsuchen zu müssen
 */

namespace PbClasses\Trees;

use PbClasses\Conf;
use \PbClasses\Access;

class NaviTree
{

    const DEFAULT_PROJECT_LANG = 'en';
    const DEFAULT_LANGUAGE_TABLE = 'pbc__navigation_translation';



    protected $db;
    protected $table;
    protected $translationArray = null;

    public function __construct($table, $dbHandle = false)
    {
        $this->db = ($dbHandle) ? $dbHandle : \PbClasses\Conf::get('db');
        $this->table = $table;
        $this->setTranslationArray();
    }

    public function getNaviRows($pageIndex = 0)
    {
        if (!Conf::get('site_navi_tree')) {
            if (!$this->setNaviTree()) {
                return [];
            }
        }
        return Conf::get('site_navi_tree')[$pageIndex]['nextlevel'];
    }

    /**
     * Der volle Navigationsbaum wird eventuell noch für Unternavigationen (Sidenavi etc.) gebraucht
     * 
     * @return boolean
     */
    protected function setNaviTree()
    {
        $res = $this->getTreeData();

        if (!$res) {
            return false;
        }
        $accObj = (Conf::get('access_tables')) ? new Access() : null;

        $items = array();
        foreach ($res as $row) {
            $row['ID'] = (int) $row['ID'];
            if ($accObj && !$accObj->hasAccess($_SESSION['uid'], $row['ID'])) {
                continue;
            }
            if ($this->translationArray && isset($this->translationArray[$row['ID']])) {
                $row['Label'] = $this->translationArray[$row['ID']];
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

    private function getTreeData()
    {
        $what = "ID, ParentID, Label, URL, AltTpl, HelpID, Ord, Hide, NoPage, AccLevel, 0 as level, '' as parents, '' as nextlevel";
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
        return $this->db->selectAssoc($this->table, $what, $where, '', $condition);
    }

    protected function setTranslationArray()
    {
        /*
         *                 'I18n' => [
          'project_lang' => 'de',
          'supported_lang' => ['de', 'en'],
          'default_notsupported_lang' => 'en', //If the user language is not supported the fallback is English [en]
          'navigation_translation_table' => 'navigation_translation'

          ],
         */
        $langConf = Conf::get('I18n');
        if (!$langConf) {
            return;
        }
        
        
        $projectLang = $langConf['project_lang'] ?? self::DEFAULT_PROJECT_LANG;
        $prefLang =  Conf::get('pref_lang') ?? $projectLang;
        //The user's language is already the language of the navigation table. 
        if ($projectLang === $prefLang) {
            return;
        }
        $defaultNotSupportedLang = $langConf['default_notsupported_lang'] ?? self::DEFAULT_PROJECT_LANG;

        $language = in_array($prefLang, $langConf['supported_lang']) ? $prefLang : $defaultNotSupportedLang;
        $langTable = $langConf['navigation_translation_table'] ?? self::DEFAULT_LANGUAGE_TABLE;
        $what = ['NavigationId', 'Entry'];
        $where = ['Language' => $language];
        $condition = 'ORDER BY NavigationId';
        $res = $this->db->selectAssoc($langTable, $what, $where, '', $condition);

        if (!$res) {
            return;
        }

        $this->translationArray = [];
        foreach ($res as $row) {
            $this->translationArray[$row['NavigationId']] = $row['Entry'];
        }
    }
}

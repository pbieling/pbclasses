<?php

namespace PbClasses\Access\Ajax;

use PbClasses\Conf;
use PbClasses\PbTpl;

class GroupsForPage extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $userArr = [];

    #[\Override]
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $this->db = \PbClasses\Conf::get('db');
        if (empty($this->P['pid'])) {
            $this->errorOutput('Fehlende Page-ID', __FILE__, __LINE__);
        }
        $pid = (int) $this->P['pid'];

        $res = $this->getGroupsArr($pid);
        $this->makeHtml($res);
        $this->okOutput();
    }

    protected function getGroupsArr($pid) {
        $accTableArr = Conf::get('access_tables');
        if (!$accTableArr) {
            $errortext = 'Konfigurationsfehler - Keine Zugriffstabellen definiert.';
            $this->errorOutput($errortext, __FILE__, __LINE__);
        }
      
        $table = $accTableArr['GROUP_TABLE'] . ' AS a';
        $what = 'a.`GID`, a.`GKey`, a.`GLabel`, ';
        $what .= "IFNULL((SELECT 1 FROM " . $accTableArr['PAGE2GROUPS_TABLE'] . " AS b  WHERE a.GID = b.GID  AND b.Hide = 0 AND b.PID = '$pid'), 0) AS Chk";
        $where = "a.Hide = 0";
        $condition = 'ORDER BY a.GID';
        $res = $this->db->selectAssoc($table, $what, $where, '', $condition);
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['checked'] = ($res[$i]['Chk'] == 1) ? ' checked' : '';
        }
        return $res;
    }
    
    
    protected function makeHtml($res) {
        $file = Conf::get('BACKTPLPATH') . 'system/access/access.tpl';
        try {
            $t = new PbTpl($file);
        } catch (\Exception $exc) {
            $this->errorOutput($exc->getMessage(), __FILE__, __LINE__);
        }
        
        $rowsHtml = $t->fillRowTpl('page2groups_rows', $res);
        $this->rslt['html'] = $t->fillTpl('page2groups_table', 'page2groups_rows', $rowsHtml);
       
    }

}

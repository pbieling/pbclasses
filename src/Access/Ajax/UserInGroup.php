<?php

namespace PbClasses\Access\Ajax;

use PbClasses\Conf;
use PbClasses\PbTpl;

class UserInGroup extends \PbClasses\Ajax\AbstractResponse
{

    protected $db;
    protected $userArr = [];

    #[\Override]
    public function action()
    {
        \PbClasses\Ajax\SessionStart::start();
        $this->db = \PbClasses\Conf::get('db');
        if (empty($this->P['gid'])) {
            $this->errorOutput('Fehlende Gruppen-ID', __FILE__, __LINE__);
        }
        $gid = (int) $this->P['gid'];

        $res = $this->getUserArr($gid);
        $this->makeHtml($res);
        $this->okOutput();
    }

    protected function getUserArr($gid)
    {
        $accTableArr = Conf::get('access_tables');
        if (!$accTableArr) {
            $errortext = 'Konfigurationsfehler - Keine Zugriffstabellen definiert.';
            $this->errorOutput($errortext, __FILE__, __LINE__);
        }

        $table = Conf::get(['db_tables', 'user']) . ' AS a';
        $what = 'a.`UID`, a.`Uname`, a.`RealName`, ';
        $what .= "IFNULL((SELECT 1 FROM " . $accTableArr['USER2GROUPS_TABLE'] . " AS b  WHERE a.UID = b.UID AND b.Hide = 0 AND b.GID = '$gid'), 0) AS Chk";
        $where = "a.ON_OFF = 1";
        $res = $this->db->selectAssoc($table, $what, $where);
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['checked'] = ($res[$i]['Chk'] == 1) ? ' checked' : '';
        }
        return $res;
    }

    protected function makeHtml($res)
    {
        $file = Conf::get('BACKTPLPATH') . 'system/access/access.tpl';
        try {
            $t = new PbTpl($file);
        } catch (\Exception $exc) {
            $this->errorOutput($exc->getMessage(), __FILE__, __LINE__);
        }

        $rowsHtml = $t->fillRowTpl('table_rows', $res);
        $this->rslt['html'] = $t->fillTpl('group2user_table', 'table_rows', $rowsHtml);
    }
}

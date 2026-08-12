<?php

namespace PbClasses;

use PbClasses\Conf;

/**
 * Description of Access
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 30.01.2020, 16:39:54
 */
class Access {

    const LOGIN_PID = 1; //Auf diese Seite muss jeder. Check nicht nötig nach Login
    /*
      'access_tables' => array('PAGE2GROUPS_TABLE' => $table_prefix . 'access_page2groups',
      'GROUP_TABLE' => $table_prefix . 'access_groups',
      'USER2GROUPS_TABLE' => $table_prefix . 'access_user2groups')

     */
    protected $accessTables = null;
    protected $accessConf = null;
    protected $db;

    public function __construct() {
        $this->db = Conf::get('db');
        $this->accessTables = Conf::get('access_tables');
        $this->accessConf = Conf::get('access_conf');
    }

    public function hasAccess($uid, $pid) {
      
        
        if (!$this->accessTables) {
            new \PbClasses\Exception\Logging('Problem mit Acces-Konfiguration', __FILE__, __LINE__);
            return false;
        }

        //Diese Seite gehört zu jeder Gruppe und benötigt keinen Check
        if ($pid === self::LOGIN_PID) {
            return true;
        }

        if (!$uid) {
             new \PbClasses\Exception\Logging('Problem mit UID (0 oder undefined)', __FILE__, __LINE__);
            return false;
        }

        if (!$pid) {
            return false;
        }

        /* User wurde bereits auf Admin-Status gecheckt? */
        if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
            return true;
        }


        if ($this->isAdmin($uid)) {
            $_SESSION['admin'] = true;
            return true;
        }
   
        if (isset($this->accessTables['PAGE2USER_TABLE'])) {
            $res = $this->checkPage2UserTable($uid, $pid);

            if ($res !== null) {
                return ($res == 0) ? true : false;
            }
        }

        if ($this->isFreeIfNoIndex($pid)) {
            return true;
        }


        $tables = $this->accessTables['USER2GROUPS_TABLE'] . ' a, '
                . $this->accessTables['PAGE2GROUPS_TABLE'] . ' b, '
                . $this->accessTables['GROUP_TABLE'] . ' c';
        $what = 'a.UID';
        $where = "a.UID = '$uid' AND a.GID = b.GID AND a.GID = c.GID "
                . "AND b.PID = '$pid' AND a.Hide = 0 AND b.Hide=0 "
                . "AND c.Hide = 0";
        if (!$this->db->select($tables, $what, $where, '', 'LIMIT 1')) {
            return false;
        }
        return true;
    }

    protected function checkPage2UserTable($uid, $pid) {
        $table = $this->accessTables['PAGE2USER_TABLE'];
        $what = 'Forbidden';
        $where = ['UID' => $uid, 'PID' => $pid, 'Hide' => 0];
        //selectSingleVal($table, $sel = "", $where = "", $returnOnEmpty = null)
        return $this->db->selectSingleVal($table, $what, $where);
    }

    protected function isAdmin($uid) {

        $tables = $this->accessTables['USER2GROUPS_TABLE'] . ' a, '
                . $this->accessTables['GROUP_TABLE'] . ' c';
        $what = 'a.UID';
        $where = "a.UID = '$uid' AND a.GID = c.GID "
                . "AND a.Hide = 0 "
                . "AND c.Hide = 0 AND c.GKey = 'admin'";
        if (!$this->db->select($tables, $what, $where, '', 'LIMIT 1')) {
            return false;
        }
        return true;
    }

    /**
     * 
     * @param int $pid
     * @return bool
     */
    protected function isFreeIfNoIndex($pid) {
        //Check whether this security risk is explicitly accepted.
        if (empty($this->accessConf['no_entry_is_free']) || $this->accessConf['no_entry_is_free'] !== true) {
            return false;
        }

        $what = 'PID';
        $where = ['PID' => $pid, 'Hide' => 0];
        $res = $this->db->select($this->accessTables['PAGE2GROUPS_TABLE'], $what, $where);
        if ($res) {
            return false;
        }

        //If there is no table for single user access rules return true
        if (!isset($this->accessTables['PAGE2USER_TABLE'])) {
            return true;
        }

        //else check if there is an entry:
        $res2 = $this->db->select($this->accessTables['PAGE2USER_TABLE'], $what, $where);
        if ($res2) {
            return false;
        }
        //No enty was found:
        return true;
    }
}

<?php

namespace PbClasses\Access;

use PbClasses\Conf;

/**
 * 'access_tables' => array('PAGE2GROUPS_TABLE' => $table_prefix . 'access_page2groups',
  'GROUP_TABLE' => $table_prefix . 'access_groups',
  'USER2GROUPS_TABLE' => $table_prefix . 'access_user2groups',
  'PAGE2USER_TABLE' => $table_prefix . 'access_page2user'),
 *
 * @author Peter Bieling
 */
class AccessGroupsForPage {

    protected $groupArr = [];
    protected $db;

    public function __construct() {
        $this->db = Conf::get('db');
    }

    public function getGroupArr($pid) {
        $accTableArr = Conf::get('access_tables');

        //==== 'GROUP_TABLE' ===================
        // GID, GKey, GLabel, CrBy, CrDate, Ord, Hide (access_groups)
        //======= 'PAGE2GROUPS_TABLE' ==========
        //PID, GID, CrBy, CrDate, Hide


        $what = "a.GID, b.GKey, b.GLabel";
        $where = "a.PID = '$pid' AND a.GID = b.GID AND a.Hide = 0 AND b.HIDE = 0";
        $condition = 'ORDER BY GID';

        $tables = $accTableArr['PAGE2GROUPS_TABLE'] . ' a, ' . $accTableArr['GROUP_TABLE'] . ' b';
        $res = $this->db->selectAssoc($tables, $what, $where, '', $condition);
        return $res ? $res : [];
    }
}

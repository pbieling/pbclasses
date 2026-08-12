<?php

namespace PbClasses\Table\Access;
use PbClasses\Conf;
use PbClasses\PbTpl;

/**
 * @todo Testen, ob der User Zugriff hat, wenn die Gruppe deaktiviert ist.
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 2025-01-31
 */
class GroupMembers {

    protected $db;
    protected $groupToUserTable;
   // protected $t;
    protected $rowArr;
    
    public function __construct($key, & $tdArr, & $conf) {
        

       // $template = Conf::get('BACKTPLPATH') . $conf['add_fields_data']['artikel_varianten']['tpl'];
        $this->db = Conf::get('db');
        $this->groupToUserTable = Conf::get(['access_tables', 'USER2GROUPS_TABLE']);
        
        try {
            $userRows = $this->getGroupMemberRows($tdArr['GID']);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $tdArr[$key] = '-FEHLER-';
            return;
        }
        
        $tdArr[$key] = $this->getHtml($userRows);
      
        
    }
    
    
    protected function getGroupMemberRows($gid) {
        $tables = $this->groupToUserTable . ' a, ' . Conf::get(['db_tables', 'user']) . ' b';
        
        $what = 'a.UID, b.Uname, b.RealName';
        $where = "a.GID = '$gid' AND a.UID = b.UID AND b.ON_OFF = 1 AND a.Hide = 0";
        $condition = 'ORDER BY a.UID';
        $this->rowArr = $this->db->selectAssoc($tables, $what, $where, '', $condition);
     //   new \PbClasses\Debug\Logging($this->rowArr, __FILE__, __LINE__);
        return ($this->rowArr) ? $this->rowArr : [];
        
    }

    
    protected function getHtml($userRows) {

        $file = Conf::get('BACKTPLPATH') . 'system/access/access.tpl';
        try {
            $t = new PbTpl($file);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            return 'FEHLER';
        }
        
        return $t->fillRowTpl('group_member', $userRows);
       
        
    
    }

}
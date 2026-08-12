<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

class GetSearchResult extends \PbClasses\Ajax\AbstractResponse {
    protected $db;
    protected $conf;
    protected $c;
    
    public function action() {
        \PbClasses\Ajax\SessionStart::start();


        $this->db = Conf::get('db');
        $this->conf = MConf::get('docu_conf');

        $resultRows = $this->getResults();
        
        $this->c = new \PbClasses\PbTpl($this->conf['public']['search_tpl']);    
        
        if (! $resultRows) {
             $this->rslt['content'] = $this->c->fillTpl('no_result');
        } else {
            $searchRows = $this->c->fillRowTpl('row', $resultRows);
            $this->rslt['content'] = $this->c->fillTpl('content', 'rows', $searchRows);
        }

        $this->rslt['succ'] = 'OK';
        $this->output();
    }
    
    protected function getResults() {
        $search = $this->P['search'];
        
        $tableContent = $this->conf['doc_tables']['PAGE_CONTENT'];
        $tableIndex = $this->conf['doc_tables']['PAGE_INDEX'];
        
        
        
        $table = $tableIndex . ' a, ' . $tableContent . ' b';
        
        $what = 'a.ID, a.Label';
        
        $where = "a.ID = b.PageId AND a.Hide = 0 AND (b.Placeholder in ('keywords', 'teaser') AND b.Entry LIKE '%$search%' OR a.Label LIKE '%$search%')";
        $condition = 'GROUP BY a.ID Order by a.ParentID, a.ID LIMIT 20';
        $res = $this->db->selectAssoc($table, $what, $where, '', $condition);
        
        if (!$res) {
            return 0;
        }
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['teaser'] = $this->getEntry('teaser', $res[$i]['ID']);
            $res[$i]['keywords'] = $this->getEntry('keywords', $res[$i]['ID']);
        }
        
        return $res;
    }
    
    protected function getEntry($fieldname, $id) {
        $tableContent = $this->conf['doc_tables']['PAGE_CONTENT'];
        $what = 'Entry';
        $where = array('PageId' => $id,
                       'ModID' => 1,
                       '`Row`' => 0,
                       'Placeholder' => $fieldname);
        $res = $this->db->select($tableContent, $what, $where, '', 'LIMIT 1');
        if (! $res) {
            return '';
        }
        return $res[0][0];
        
    }

}

<?php

namespace PbClasses\MDoc\Ajax;

use PbClasses\Conf;
use PbModule_commondocu\MConf;

class UpdateEntry extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $conf;
    protected $table;

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
            /*
            new \PbClasses\Debug\Logging($this->P, __FILE__, __LINE__);
            $this->rslt['errortext'] = 'Test - Abbruch.';
            $this->output(__FILE__, __LINE__);
             * 
             */
        
        $this->db = Conf::get('db');

        $this->conf = MConf::get('docu_conf');
        $this->table = $this->conf['doc_tables']['PAGE_CONTENT'];

        
        $changes = false;
        foreach($this->P['entry'] as $placeHolder => $sgEntryArr) {
            $what = array('entry' => $sgEntryArr[0]);

            if ($this->makeUpdateOrEntry($what, $placeHolder)) {
                $changes = true;
            }
        }
        if (! $changes) {
            $this->rslt['errortext'] = 'Es wurde nichts geändert.';
        }

        $this->rslt['succ'] = 'OK';
        $this->output();
    }
    
    protected function makeUpdateOrEntry($what, $placeHolder) {
        $upd = $what;
        
        try {
            //Werte ergänzen:
            $what['ModID'] = $this->P['modid'];
            $what['PageId'] = $this->P['id'];
            //Der Filter wird in der Konfiguration festgelegt
            $what['Filter'] = $this->conf['doc_vars']['placeholder_filter'][$placeHolder]; //$this->P['filter'];
            $what['Placeholder'] = $placeHolder;
            //ON DUPLICATE KEY UPDATE
            if ($this->db->insert($this->table, $what, '', $upd)) {
                return true;
            }
            return false;
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__) ;
            $err = 'Datenbankproblem.';
            $this->errorOutput($err, __FILE__, __LINE__);
        }
    }


}

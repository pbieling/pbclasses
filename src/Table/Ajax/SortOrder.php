<?php

namespace PbClasses\Table\Ajax;

/**
 * @author  Peter Bieling - 2022-12-01
 * 
 * Sortiert eine Tabelle nach einer in der Page-Konfiguration 
 * hinterlegten Unterkonfiguration, die einerm individuellem Key zugeordnet ist
 * 
 * 
 * 
 */
class SortOrder extends \PbClasses\Ajax\AbstractResponse {

    protected $db;
    protected $sortArr;


    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $this->db = \PbClasses\Conf::get('db');
        if (!isset($this->P['conf_sortkey'])) {
            $this->errorOutput('Konfigurationsfehler: Fehlender Schlüssel', __FILE__, __LINE__);
        }
        $key = & $this->P['conf_sortkey'];

        if (!isset($this->basePageConf['tablesort_data'][$key])) {
            $this->errorOutput('Konfigurationsfehler: Ungültiger Schlüssel', __FILE__, __LINE__);
        }

        if (empty($this->P['sort_arr']) || !is_array($this->P['sort_arr'])) {
            $this->errorOutput('Daten-Fehler beim Sortier-Array', __FILE__, __LINE__);
        }
        $this->sortArr = $this->P['sort_arr'];
        
       // new \PbClasses\Debug\Logging($this->sortArr, __FILE__, __LINE__);
        
        $this->saveOrder($this->basePageConf['tablesort_data'][$key]);
        $this->okOutput();
    }

    protected function saveOrder($sortConf) {
        $table = $sortConf[0];
        $primaryField = isset($sortConf[1]) ? $sortConf[1] : 'ID';
        $ordField = isset($sortConf[2]) ? $sortConf[2] : '`Ord`';
        
        
        foreach ($this->sortArr as $ord => $id) {
            $updArr = array($ordField => $ord + 1);
            $where = array($primaryField => $id);
            try {
                $this->db->update($table, $updArr, '', $where);
            } catch (\Exception $exc) {
                $this->errorOutput('Fehler beim Update der  Sortierung', __FILE__, __LINE__, $exc->getMessage());
            }
        }
    }

}

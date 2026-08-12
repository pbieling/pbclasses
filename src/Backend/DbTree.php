<?php

/**
 * @author Peter Bieling - 2018-02-01
 */
namespace PbClasses\Backend;
use PbClasses\Conf;

class DbTree {

    protected $db;
    protected $table;

    public function __construct($table, $dbHandle = false) {
        $this->db = ($dbHandle) ? $dbHandle : Conf::get('db');
        $this->table = $table;
    }
    
    
    public function getChildren($modul) {
        $what = "ID, DataSource, Fields, left(Fields, 15) as fields_short";
        $where = " ModID = $modul AND DataSource != 'mod'";
        
        $res = $this->db->selectAssoc($this->table, $what, $where, '', 'Group by Fields ORDER BY Ord DESC ');
        if (!$res) {
            return array();
        }
        return $res;
    }
    
     public function getPageData($pid) {
        $what = "";
        $res = $this->db->selectAssoc($this->table, $what, array('ID' => $pid), '', 'LIMIT 1');
        if (!$res) {
            return false;
        }
        return $res[0];
    }   
    
    
    

    public function rename($id, $text) {
        $id = (int) $id;
        $text = trim($text);
        if (!$text) {
            throw new \Exception('Text fehlt für ID ' . $id);
        }
        $upd = array('Label' => $text);
        return $this->db->update($this->table, $upd, '', array('ID' => $id)) ? true : false;
    }
    
    public function delete($id, $parent) {
        if ($this->db->delete($this->table, array('ID' => $id))) {
            return true;
        }
        return false;
    }

    public function create($parent, $ord, $text) {

        $text = trim($text);
        $inArr = array('Label' => $text,
            'Ord' => $ord,
            'ParentID' => $parent,
			'Hide' => 1);
        $this->db->insert($this->table, $inArr);
        
        
        $id = $this->db->insertId();
        
        $this->sortColumn($id, $ord, $parent);
        return $id;
    }

    public function move($id, $ord, $parent) {
        $upd = array('Ord' => $ord,
                     'ParentID' => $parent);
        $this->db->update($this->table, $upd, '', array('ID' => $id));
        $this->sortColumn($id, $ord, $parent);
    }

    protected function sortColumn($id, $ord, $parent) {
        $what = 'ID';
        $where = "ParentId = '$parent' AND ID != '$id'";
        $condition = 'ORDER BY ORD, ID';
        $res = $this->db->select($this->table, $what, $where, '', $condition);
        if (! $res) {
            //Als neues Kindelement? - Todo: testen
            return;
        }
        
        //Ein Zähler mehr als Elemente! Für das verschobene Element
        $ordCounter= 0;
        
        for ($i = 0; $i < count($res); $i++) {
            //Das verschobene Element hat bereits die richige Position (Ord = $ord)
            if ($i == $ord) {
                $ordCounter++;
            }
            
            $upd = array('Ord' => $ordCounter); 
            $this->db->update($this->table, $upd, '', array('ID' => $res[$i][0]));
            $ordCounter++;
        }
    }


}

<?php

/**
 * @author Peter Bieling - info@media-palette.de
 * Created on : 04.01.2018, 14:24:37
 */

namespace PbClasses\MDoc;

use PbClasses\MDoc\ClassResult;

use PbClasses\Conf;

class PrevNextNavi extends ClassResult {

    protected $db;
    protected $t;
    protected $table;
    protected $contentTable;

    protected function run() {
        $this->db = Conf::get('db');
        /*

         * 
         * 
         * 
         */
        //Objekt wurde übergeben und kann hier genutzt werden:
        $this->t = $this->paramArr['pstr_arr']['tpl_obj'];
        $this->table = $this->paramArr['pstr_arr']['doc_conf']['doc_tables']['PAGE_INDEX'];
        $this->contentTable = $this->paramArr['pstr_arr']['doc_conf']['doc_tables']['PAGE_CONTENT'];
        $indexRow = & $this->paramArr['pstr_arr']['row_index'];

        $seRe = array('prevlink' => (!$indexRow['Ord']) ? '' : $this->getPrev($indexRow['ParentID'], $indexRow['Ord']),
            'uplink' => (!$indexRow['ParentID']) ? '' : $this->getUp($indexRow['ParentID']),
            'nextlink' => $this->getNext($indexRow['ParentID'], $indexRow['Ord']),
            'child_block' => $this->getChildBlock($indexRow['ID'])
        );

        $iconsSere = Conf::get('doc_icons') ?? [
            'doc_up' => 'far fa-arrow-alt-circle-up',
            'doc_prev' => 'far fa-arrow-alt-circle-left',
            'doc_next' => 'far fa-arrow-alt-circle-right'
        ];
        $seRe = \array_merge($seRe, $iconsSere);

        $this->result = $this->t->fillTpl('prevnext_container', $seRe);
    }

    protected function getPrev($parentId, $ord) {
        $where = "ParentId = '$parentId' AND Hide = 0 AND Ord < '$ord'";

        $res = $this->db->selectAssoc($this->table, array('ID', 'Label'), $where, '', 'ORDER BY ORD DESC LIMIT 1');
        if (!$res) {
            return '';
        }
        return $this->t->fillTpl('prevlink', $res[0]);
    }

    protected function getNext($parentId, $ord) {
        $where = "ParentId = '$parentId' AND Hide = 0 AND Ord > '$ord'";

        $res = $this->db->selectAssoc($this->table, array('ID', 'Label'), $where, '', 'ORDER BY ORD ASC LIMIT 1');
        if (!$res) {
            return '';
        }
        return $this->t->fillTpl('nextlink', $res[0]);
    }

    protected function getUp($parentId) {
        $res = $this->db->selectAssoc($this->table, array('ID', 'Label'), array('ID' => $parentId, 'Hide' => 0), '', 'LIMIT 1');
        if (!$res) {
            return '';
        }
        return $this->t->fillTpl('uplink', $res[0]);
    }

    protected function getChildBlock($id) {

        try {
            $res = $this->db->selectAssoc($this->table, array('ID as child_id', 'Label as child_label'), array('ParentID' => $id, 'Hide' => 0), '', 'ORDER BY Ord');
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage() . ' ' . $exc->getTraceAsString());
            return '-- TECHNISCHER FEHLER --';
        }


        if (!$res) {
            return '';
        }
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]['child_teaser'] = $this->getTeaser($res[$i]['child_id']);
        }


        $items = $this->t->fillRowTpl('child_item', $res);

        return $this->t->fillTpl('child_block', 'child_items', $items);
    }

    protected function getTeaser($id) {
        //ModID 	PageId 	Row 	Placeholder    
        $where = array('PageId' => $id, '`Row`' => 0, 'Placeholder' => 'teaser');
        try {
            $res = $this->db->select($this->contentTable, 'Entry', $where, '', 'LIMIT 1');
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage() . ' '. $exc->getTraceAsString());
            return '-- TECHNISCHER FEHLER --';
        }
        return (!$res) ? '' : $res[0][0];
    }

}

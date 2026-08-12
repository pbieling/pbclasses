<?php

namespace PbClasses\MDoc;

use PbClasses\Conf;
use PbModule_commondocu\MConf;


class DocTree {

    protected $c;
    protected $conf;
    protected $isAdmin;

    public function __construct($conf = null) {
        //new \PbClasses\Debug\Logging(MConf::getAll(), __FILE__, __LINE__);
        //new \PbClasses\Debug\Logging($conf, __FILE__, __LINE__);
        
        $this->isAdmin = (!empty(Conf::get('page_conf')['class_param']['MDocTree']['isAdmin'])) ? true : false;
        if ($conf) {
            $this->conf = $conf;
        } else {
            $confFile = Conf::get('APATH') . 'doc.conf.php';
            if (!\file_exists($confFile)) {
                throw new \Exception('Keine Konfiguration gefunden.');
            }
            $this->conf = require $confFile;
        }
        $docIndex = ($this->isAdmin) ? 'admin' : 'public';
        $this->c = new \PbClasses\PbTpl($this->conf[$docIndex]['tree_tpl']);
        $cse_re = array();
        $naviRows = $this->getNaviRows();
        if (! $naviRows) {
             Conf::addKV('cSeRe', 'doc_tree', '');  
             return;
        }
        $cse_re['tree'] = $this->buildSitemap($naviRows);
        MConf::addKV('output_sere', 'doc_tree', $this->c->fillTpl('content1', $cse_re));
       // Conf::addKV('cSeRe', 'doc_tree', $this->c->fillTpl('content1', $cse_re));
    }

    protected function getNaviRows($parentid = 0) {
        $what = '';
        $where = array('ParentId' => $parentid);
        if (! $this->isAdmin) {
            $where['Hide'] = 0;
        }
        $res = Conf::get('db')->selectAssoc($this->conf['doc_tables']['PAGE_INDEX'], $what, $where, '', 'ORDER BY Ord, ID');
        if (!$res) {
            return false;
        }

        $tmp_tree = array();
        foreach ($res as $row) {
            $tmp = ''; //Buffer
            $tmp_tree[$row['ID']]['Label'] = $row['Label'];
            $tmp_tree[$row['ID']]['ID'] = $row['ID']; //neu
            $tmp_tree[$row['ID']]['Status'] = ($row['URL']) ? 1 : 0;
            $tmp_tree[$row['ID']]['type'] = \PbClasses\Backend\Icon::getIconType($row);
            if ($tmp = $this->getNaviRows($row['ID'])) {
                $tmp_tree[$row['ID']]['nextlevel'] = $tmp;
            } else { //Kann man auch weglassen, nur muss man dann später
                //die Existenz von nextlevel abfragen
                $tmp_tree[$row['ID']]['nextlevel'] = '';
            }
        }
        return $tmp_tree;
    }

    /*
      Der Navigationsbaum wird Level für Level durchwandert, bzw. abgebaut.
      Das Array mit dem Teilbaum der nächsten Ebenen gibt die Funktion rekursiv
      an "sich selbst" weiter, bis 'nextlevel' nur noch einen Leerstring
      enthält.
      Nun wird das Template auf der niedrigsten Verschachtelungsebene
      (höchste Levelnr dieses Astes)
      gefüllt und das fertige HTML "noch oben", also an die aufrufende Funktion
      zurückgegeben.


     */

    protected function buildSitemap($tree, $level = 1) {
        $html_buffer = '';
        foreach ($tree as $k => $v_arr) {
            $tmp = '';
            if (\is_array($v_arr['nextlevel'])) {
                $tmp = $this->buildSitemap($v_arr['nextlevel'], $level + 1);
            }
            $v_arr['nextlevel'] = $tmp;
            $v_arr['level'] = $level;
            $html_buffer .= $this->c->fillTpl('links', $v_arr);
        }
        return $this->c->fillTpl('tree', 'links', $html_buffer);
    }

}

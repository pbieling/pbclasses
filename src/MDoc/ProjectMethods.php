<?php

namespace PbClasses\MDoc;

use PbClasses\Conf;

/**
 * Projektbezogene allgemeine Erweiterungen für komplexe Aufgaben.
 */
class ProjectMethods {

    protected $page_id;
    protected $group_id;
    protected $pstr_arr;
    protected $mod_id;
    protected $token_arr;
    protected $pgVars = array();
    protected $pgTables = array();
    protected $fronttplpath;

    public function __construct($page_id, $mod_id, $group_id, $pstr_arr, $token_arr, $pgTables = null, $pgVars = null, $fronttplpath = null) {
        $this->page_id = $page_id;
        $this->mod_id = $mod_id;
        $this->group_id = $group_id;
        $this->pstr_arr = $pstr_arr;
        $this->token_arr = $token_arr;
        if ($pgTables === null) {
            $this->pgVars = Conf::get('pg_vars');
            $this->pgTables = Conf::get('pg_tables');
            $this->fronttplpath = Conf::get('FRONTTPLPATH');
        } else {
            $this->pgVars = $pgVars;
            $this->pgTables = $pgTables;
            $this->fronttplpath = $fronttplpath;            
        }



        // \PbClasses\Develop::test($this->pstr_arr, __LINE__,1);
    }

    public function get_scnd_style() {
        $what = 'CSS';
        $where = array(
            'ID' => $this->page_id
        );
        $res = Conf::get('db')->select($this->pgTables['PAGE_INDEX'], $what, $where);
        if (trim($res[0][0]) == '') {
            return array(
                'scnd_style' => ''
            );
        }

        $style_arr = explode(',', $res[0][0]);
        $pt = new \PbClasses\PbTpl($this->fronttplpath . 'common.tpl');
        $buffer = '';
        foreach ($style_arr as $css) {
            $css = trim($css); //Leerzeichen weg.
            $buffer .= $pt->fillTpl('scnd_style', 'cssfile', $css);
        }
        return array(
            'scnd_style' => $buffer
        );
    }

    public function getJsFiles() {
        $js = trim($this->pstr_arr['Param']);
        if (!$js) {
            return array(
                'js_files' => ''
            );
        }

        $jsArr = explode(',', $js);
        $pt = new \PbClasses\PbTpl($this->fronttplpath . 'common.tpl');
        $buffer = '';
        foreach ($jsArr as $sgJsFile) {
            $jsf = \trim($sgJsFile); //Leerzeichen weg.
            $buffer .= $pt->fillTpl('js_files', 'file', $jsf);
        }
        return array(
            'js_files' => $buffer
        );
    }

    public function get_metas() {
        $what = "Titel as `title`, Meta_description as meta_description, Meta_keywords as meta_keywords";
        $where = array(
            'ID' => $this->page_id
        );
        $res = Conf::get('db')->selectAssoc($this->pgTables['PAGE_METAS'], $what, $where);
        if (!$res) {
            $err = "ID " . $this->page_id . ' nicht in Page_metas gefunden';
            \PbClasses\UserErr::push($err);
            return (array('title' => '',
                'meta_description' => '',
                'meta_keywords' => ''));
        }


        return $res[0];
    }

}

<?php

namespace PbClasses\MDoc;

use PbClasses\Conf;

/**
 * Projektbezogene Erweiterungen für komplexe Aufgaben.
 * Daten, die erst berechnet werden müssen
 */
class OwnProjectMethods {

    protected $page_id;
    protected $group_id;
    protected $pstr_arr;
    protected $mod_id;
    protected $token_arr;
    protected $pgVars = array();
    protected $pgTables = array();

    public function __construct($page_id, $mod_id, $group_id, $pstr_arr, $token_arr) {
        $this->page_id = $page_id;
        $this->mod_id = $mod_id;
        $this->group_id = $group_id;
        $this->pstr_arr = $pstr_arr;
        $this->token_arr = $token_arr;
        $this->pgVars = Conf::get('pg_vars');
        $this->pgTables = Conf::get('pg_tables');
        // \PbClasses\Develop::test($this->pstr_arr, __LINE__,1);
    }

    public function getClassResult($classname, $fields) {
        $paramArr = array('page_id' => $this->page_id,
            'mod_id' => $this->mod_id,
            'group_id' => $this->group_id,
            'pstr_arr' => $this->pstr_arr,
            'token_arr' => $this->token_arr);

       // $classname = '\\' . __NAMESPACE__ . '\\' . $classname;

        $obj = new $classname($paramArr);
        return $obj->getSeRe($fields);
    }

}

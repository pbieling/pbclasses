<?php
/*
 *  @author Peter Bieling - pb@p-bieling.de - 2022-11-27
 */

namespace PbClasses\Table;

use PbClasses\Conf;


class TableDefHtml {

    //put your code here


    protected $defTabel;
    protected $key;
    protected $label;
    protected $template;
    protected $tplName;
    protected $rows;
    protected $db;
    protected $defTable;

    //Tabelle, Key/Index, Label , TplFile (optional) , TplName (optional)
    public function __construct($paramArr) {

        
        $this->defTable = $paramArr[0];
        $this->key = $paramArr[1];
        $this->label = $paramArr[2];
        $this->template = isset($paramArr[3]) ? $paramArr[3] : Conf::get('BACKTPLPATH') . 'tables/common.tpl';
        $this->tplName = isset($paramArr[4]) ? $paramArr[4] : 'standard_option';

        $this->db = Conf::get('db');
        //$this->db->setDebug(true);
    }

    public function getHtml() {
        $this->getRows();
        
        if (!$this->rows) {
            return '';
        }

        try {
            $t = new \PbClasses\PbTpl($this->template);

            return $t->fillRowTpl($this->tplName, $this->rows);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            return '';
        }
    }

    protected function getRows() {
        try {

            //<option value="{OPTION_VALUE}">{OPTION_LABEL}</option>

            $what = $this->key . ' AS option_value, ' . $this->label . ' AS option_label';
            $where = array('Hide' => 0);
            $condition = 'ORDER BY `Ord`, option_label';

            $this->rows = $this->db->selectAssoc($this->defTable, $what, $where, '', $condition);
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
        }
    }

}

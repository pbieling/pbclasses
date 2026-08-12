<?php
namespace PbClasses\Table\Filters;
use PbClasses\Conf;

/**
 *
 * @author Peter Bieling - 2022-12-20
 */
abstract class AbstractQueryPart {
    protected $kvArr;
    protected $filtersConf;
    protected $fullConf;
    protected $sqlPart = '';
    protected $db;
    
    
    public function __construct($kvArr, & $filterConf, & $fullConf) {
         $this->kvArr = $kvArr;
         $this->filtersConf = $filterConf;
         $this->fullConf = $fullConf;
         
         $this->db = Conf::get('db');
         
         $this->buildQueryPart();
    }
    
    abstract protected function buildQueryPart();
    
    public function get() {
        return  $this->sqlPart;
    }
}

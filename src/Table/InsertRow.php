<?php
namespace PbClasses\Table;

use PbClasses\Conf;

/**
 * Inserts a row into a table.
 *
 * @author Peter Bieling
 */
class InsertRow {
    protected $table;

    
    
    public function __construct($table) {
        $this->table = $table;
    }
    
    
    

    /**
     * 
     * @param array $inArr
     * @return mixed affected rows
     */
    
    public function insert($inArr) {
        //INSERT IGNORE!
        return Conf::get('db')->insert($this->table, $inArr, '', true); 
    }
}

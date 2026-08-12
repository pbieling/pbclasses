<?php
namespace PbClasses\Table;

use PbClasses\Conf;

/**
 * Updates values of a single row using the value auf the primary Key defined in the table configuration file.
 * If a composite primary key is needed, the key components must be stored as an array in the configuration.
 * The values send to the server are a base64-encoded serialized array.
 *
 * @author Peter Bieling
 */
class UpdateRow {
    protected $table;
    protected $primaryKey;
    protected $logArr; //For logging if set in configuration
    
    
    public function __construct($table, $primaryKey) {
        $this->table = $table;
        $this->logArr['table'] = $table;
        $this->logArr['type'] =  'update';
        $this->primaryKey = $primaryKey;
    }
    
    
   /**
    *  update($table, $keys, $entries = "", $where = "", $and_or = "AND", $condition = '', $ignore = false)
    * 
    * UPDATE IGNORE!
    * 
    * @param string $idValues
    * @return int affected rows
    * @throws \Exception if 
    * 
    * composite primary key
    */
    
    public function update($idValues, $updArr) {
        $where = WhereFromPrimaryKey::get($this->primaryKey, $idValues);
        $this->logArr['upd'] = $updArr;
        $this->logArr['where'] = $where;
        
        return Conf::get('db')->update($this->table, $updArr, '', $where, "AND", '', true); 
       
    }
    public function getLogData() {
        return $this->logArr;
    }
}

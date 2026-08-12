<?php

namespace PbClasses\Table;

use PbClasses\Conf;

/**
 * Deletes a single row using the value auf the primary Key defined in the table configuration file.
 * If a composite primary key is needed, the key components must be stored as an array in the configuration.
 * The values send to the server are a base64-encoded serialized array.
 *
 * @author Peter Bieling
 */
class DeleteRow {

    protected $table;
    protected $primaryKey;
    protected $logArr; //For logging if set in configuration
    protected $mustLog;

    public function __construct($table, $primaryKey, $mustLog = null) {

        $this->table = $table;
        $this->logArr['table'] = $table;
        $this->logArr['type'] = 'delete';
        $this->primaryKey = $primaryKey;
        $this->mustLog = $mustLog;
    }

    /**
     * 
     * @param string $idValues
     * @return int affected rows
     * @throws \Exception if 
     * 
     * composite primary key
     */
    public function delete($idValues) {
        $where = WhereFromPrimaryKey::get($this->primaryKey, $idValues);
        $this->logArr['upd'] = ($this->mustLog) ? $this->getDelRow($where) : null; //delete!
        $this->logArr['where'] = $where;

        return Conf::get('db')->delete($this->table, $where);
    }

    public function getLogData() {
        return $this->logArr;
    }

    protected function getDelRow($where) {
        try {
            $res =  Conf::get('db')->selectAssoc($this->table, '', $where);
            if (! $res) {
                throw new \Exception('Row not found');
            }
            if (count($res) > 1) {
                throw new \Exception('More than one row found.');
            }
            
            
            return $res[0];
            
            
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging(data, __FILE__, __LINE__);
            return 'PBCLASSES-ERROR';
        }
    }
}

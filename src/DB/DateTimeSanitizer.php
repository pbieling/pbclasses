<?php
namespace PbClasses\DB;

/**
 * 
 *
 * @author Peter Bieling - info@media-palette.de - 2021-10-21
   
 */
class DateTimeSanitizer {

    protected $db;
    protected $dateTimeFields = array();

    public function __construct($db = null) {
        if (null === $db) {
            $this->db = \PbClasses\Conf::get('db');
        } else {
            $this->db = $db;
        }
        if (!$this->db) {
            throw new \UnexpectedValueException('No database connection.');
        }
    }

    /**
     * 
     * @param string $table
     * @param arrayRef $inpArr
     * @return void
     * 
     */
    public function clean($table, & $inpArr) {
        $this->getDateTimeArr($table);
        if (! count($this->dateTimeFields)) {
            return;
        }
        foreach ($inpArr as $key => $val) {
            if (isset($this->dateTimeFields[$key])  && $this->mustReplace($val, $this->dateTimeFields[$key] )) {
                    $inpArr[$key] = null;
            }
        }
    }
    
    protected function mustReplace($val, $type) {
        if ($val === '') {
            return true;
        }
        if ($type === 'datetime' && $val === '0000-00-00 00:00:00') {
            return true;
        }
        if ($type === 'date' && $val === '0000-00-00') {
            return true;
        }
        if ($type === 'timestamp' && $val === '0000-00-00 00:00:00') {
            return true;
        }        
        if ($type === 'time' && $val === '00:00:00') {
            return true;
        }  
        return false;
    }
    

    protected function getDateTimeArr($table) {

        $result = $this->db->query('DESCRIBE ' . $table);
        if (!$result) {
            return false;
        }

        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            if ($row['Null'] === 'YES' && ($row['Type'] === 'date' || $row['Type'] === 'datetime' || $row['Type'] === 'timestamp' || $row['Type'] === 'time' )) {
            
                $this->dateTimeFields[$row['Field']] = $row['Type'];
            }
        } 
        
    }

}

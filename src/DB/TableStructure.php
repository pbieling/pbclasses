<?php

namespace PbClasses\DB;

/**
 * Provides an associative array with the structure of the table.
 *
 * @author Peter Bieling - info@media-palette.de
 * @version 0.1
 */
class TableStructure {

    protected $db;

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
     * @return mixed boolean (no result) or array
     * 
     */
    public function get($table) {
        $resArr = array();
        $result = $this->db->query('DESCRIBE ' . $table);
        if (!$result) {
            return false;
        }

        if (method_exists($result, 'fetch')) {

            //PDO::FETCH_ASSOC
            while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
                if ($row['Default'] === null && $row['Null'] === 'NO') {
                    $row['OwnDefault'] = $this->getOwnDefault($row['Type']);
                } else {
                    $row['OwnDefault'] = $row['Default'];
                }
                $resArr[$row['Field']] = $row;
            }
        } else {
            // Notice that the key is the fieldname.
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                if ($row['Default'] === null && $row['Null'] === 'NO') {
                    $row['OwnDefault'] = $this->getOwnDefault($row['Type']);
                } else {
                    $row['OwnDefault'] = $row['Default'];
                }
                $resArr[$row['Field']] = $row;
            }
        }


        return $resArr;
    }

    protected function getOwnDefault($type) {
        if (\preg_match('/int\(/', $type)) {
            return 0;
        }

        if (\preg_match('/char\(/', $type)) {
            return '';
        }
        if ($type === 'text') {
            return '';
        }
        if ($type === 'datetime') {
            return date('Y-m-d H:i:s');
        }
        if ($type === 'date') {
            return date('Y-m-d');
        }
        if ($type === 'time') {
            return '00:00';
        }

        return '';
    }
}

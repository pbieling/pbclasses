<?php
namespace PbClasses\Table;

/**
 * @author Peter Bieling
 */
class Event {
    public function __construct($className, $table, $row, $id = null) {
        try {
             new $className($table, $row);    
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage() . ' ' . $exc->getTraceAsString());
        }
    }
}

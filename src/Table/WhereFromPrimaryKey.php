<?php

namespace PbClasses\Table;

/**
 * @author Peter Bieling
 */
class WhereFromPrimaryKey {
        public static function get($primaryKey, $idValues) {
        if (\is_string($primaryKey)) {
            return array($primaryKey => $idValues);
        } 
        
        if (\is_array($primaryKey)) {
            $idValues = unserialize(base64_decode($idValues));
            
            if (! \is_array($idValues)) {
                throw new \Exception('primary key values must be an array because it is a composite primary key');
            }
            
            if (count($primaryKey) !== count($idValues)) {
                 throw new \Exception('primary key: mismatch of key values');   
            }
            
            return \array_combine($primaryKey, $idValues);
           
        }    
    }
}

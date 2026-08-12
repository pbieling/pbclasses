<?php
namespace PbClasses\Backend\Ajax;
use PbClasses\Conf;
/**
 * @author Peter Bieling - info@media-palette.de
 */
class SwitchUpdate  extends \PbClasses\Ajax\AbstractResponse{
    protected $db;
    
    protected $fieldMap = [ 'hide' => 'Hide',
                            'nopage' => 'NoPage'];
    

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $id = (int) $this->P['id'];

        
        if (!$id) {
            $this->rslt['errortext'] = 'Keine gültige ID übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        
        $fieldKey = $this->P['fname'];
        if (empty($this->fieldMap[$fieldKey])) {
            $this->rslt['errortext'] = 'Kein gültiger Schlüssel übermittelt';
            $this->output(__FILE__, __LINE__);            
        }
        
        $fieldname = $this->fieldMap[$fieldKey];         
        
        
//        $value = (int) $this->P['value'];
        
        
       
        $this->db = Conf::get('db');
        $updStr = "$fieldname = if(`$fieldname` = '0', '1', '0')"; 
        try {
            
            if ($this->db->update(Conf::get('db_tables')['backnavi'], $updStr, '', ['ID' => $id])) {
                $this->rslt['succ'] = 'OK';
            } else {
                $this->rslt['errortext'] = 'Kein Update möglich';
                $this->output(__FILE__, __LINE__);   
            }
        } catch (\Exception $exc) {
            new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
            $this->rslt['errortext'] = 'Datenbankfehler beim Update';
            $this->output(__FILE__, __LINE__);
        }


        $this->output();
    }
}

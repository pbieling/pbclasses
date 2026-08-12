<?php
namespace PbClasses\Backend\Ajax;
use PbClasses\Conf;

/**
 * Description of GetSrcCode
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 19.12.2017, 10:44:12
 */
class UpdateSrcCode extends \PbClasses\Ajax\AbstractResponse {

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        
        //Todo: Weitere Typen von Quelltexten verfügbar machen:
        
        $srcFile = Conf::get('APATH') . 'config/' . $this->P['src_file'];
   
        if (! \file_exists($srcFile)) {
             $this->rslt['errortext'] = 'Die Datei wurde nicht gefunden. Ein Update ist nicht möglich.';   
             $this->output(__FILE__, __LINE__);
        }
        
        if (! \file_put_contents($srcFile, $this->P['src_code'])) {
             $this->rslt['errortext'] = 'Die Datei konnte nicht geändert werden.';   
             $this->output(__FILE__, __LINE__);            
        }
        
        

        $this->rslt['succ'] = 'OK';
        $this->output();
    }

}

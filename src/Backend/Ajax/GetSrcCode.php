<?php
namespace PbClasses\Backend\Ajax;
use PbClasses\Conf;

/**
 * Description of GetSrcCode
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 19.12.2017, 10:44:12
 */
class GetSrcCode extends \PbClasses\Ajax\AbstractResponse {

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        
        //Todo: Weitere Typen von Quelltexten verfügbar machen:
        
        $srcFile = Conf::get('APATH') . 'config/' . $this->P['src_file'];
   
        if (! \file_exists($srcFile)) {
             $this->rslt['errortext'] = 'Keine Datei gefunden';   
             $this->output(__FILE__, __LINE__);
        }
        
        $this->rslt['code'] = \file_get_contents($srcFile);







        $this->rslt['succ'] = 'OK';
        $this->output();
    }

}

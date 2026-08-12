<?php

namespace PbClasses\MDoc\Ajax;

use PbModule_commondocu\MConf;

/**
 * Description of ImgRegisterForm
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 23.05.2018, 12:10:44
 */
class ImgRegisterForm extends \PbClasses\Ajax\AbstractResponse {

    protected $conf;
     
     
    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        $filename = $this->P['filename'];


        if (!$filename) {
            $this->rslt['errortext'] = 'Kein Dateiname übermittelt';
            $this->output(__FILE__, __LINE__);
        }
        $this->conf = MConf::get('docu_conf');

        try {
            $c = new \PbClasses\PbTpl($this->conf['admin']['image_upload_tpl']);
        } catch (\Exception $exc) {
            $this->rslt['errortext'] = $exc->getMessage();
            $this->output(__FILE__, __LINE__);
        }

        $seRe = array('new_image_name' => substr($filename, 21),
                      'upload_image_name' =>  substr($filename, 6));
        $this->rslt['image_register_form'] = $c->fillTpl('image_register_form', $seRe);
        $this->rslt['succ'] = 'OK';
        $this->output();
    }

}

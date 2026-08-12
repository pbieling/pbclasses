<?php

namespace PbClasses\Tpl\Ajax;

use PbClasses\Conf;

class JsTemplate extends \PbClasses\Ajax\AbstractResponse {

    public function action() {
        //new \PbClasses\Debug\Logging('test', __FILE__, __LINE__);
       // \PbClasses\Ajax\SessionStart::start();

        if (empty($this->P['jstemplate'])) {
            // $this->rslt['errortext'] = 'Fehler beim Laden von Daten.';
            // $this->output(__FILE__, __LINE__);
            $this->noTemplate();
        }
        if (empty(Conf::get('loadable_js_templates')[$this->P['jstemplate']])) {
            // $this->rslt['errortext'] = 'Vorgang nicht erlaubt.';
            // $this->output(__FILE__, __LINE__);
            $this->noTemplate();
        }
        $tplFile = Conf::get('loadable_js_templates')[$this->P['jstemplate']];
        if (!file_exists($tplFile)) {
            // $this->rslt['errortext'] = 'Datendatei konnte nicht gefunden werden.';
            // $this->output(__FILE__, __LINE__);
            $this->noTemplate();
        }

        \PbClasses\Ajax\SessionStart::start();

        try {
            $t = new \PbClasses\PbTpl($tplFile);
            $this->rslt['jstpl'] = $t->getTplArr();
            $this->rslt['succ'] = 'OK';
        } catch (\Exception $ex) {
            $this->rslt['errortext'] = 'Technischer Fehler';
            new \PbClasses\Exception\Logging($ex, __FILE__, __LINE__);
        }
        $this->output(__FILE__, __LINE__);
    }

    protected function noTemplate() {
        $this->rslt['jstpl'] = null;
        $this->okOutput();
    }

}

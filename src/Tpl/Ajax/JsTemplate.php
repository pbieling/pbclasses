<?php

namespace PbClasses\Tpl\Ajax;

use PbClasses\Conf;
use PbClasses\Exception\Messages;

class JsTemplate extends \PbClasses\Ajax\AbstractResponse
{

    public function action()
    {
        if (empty($this->P['jstemplate'])) {
            $this->noTemplate();
        }
        if (empty(Conf::get('loadable_js_templates')[$this->P['jstemplate']])) {
            $this->noTemplate();
        }
        $tplFile = Conf::get('loadable_js_templates')[$this->P['jstemplate']];
        if (!file_exists($tplFile)) {
            $this->noTemplate();
        }

        \PbClasses\Ajax\SessionStart::start();

        try {
            $t = new \PbClasses\PbTpl($tplFile);
            $this->rslt['jstpl'] = $t->getTplArr();
            $this->okOutput();
        } catch (\Exception $exc) {
            $this->errorOutput(Messages::get('Technical error'), __FILE__, __LINE__, $exc->getMessage());
        }
        $this->output(__FILE__, __LINE__);
    }

    protected function noTemplate()
    {
        $this->rslt['jstpl'] = null;
        $this->okOutput();
    }
}

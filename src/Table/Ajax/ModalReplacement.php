<?php

namespace PbClasses\Table\Ajax;

use PbClasses\Conf;

class ModalReplacement extends \PbClasses\Ajax\AbstractResponse {

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
        // new \PbClasses\Debug\Logging(Conf::getAll(), __FILE__, __LINE__);
        // new \PbClasses\Debug\Logging( $this->basePageConf, __FILE__, __LINE__);
        
        //new \PbClasses\Debug\Logging('AUFRUF!!!', __FILE__, __LINE__);

        if (!isset($this->P['key'])) {
            $this->errorOutput('Konfigurationsfehler: Fehlender Schlüssel', __FILE__, __LINE__);
        }
        $key = & $this->P['key'];

        if (!isset($this->basePageConf['tabledef_data'][$key])) {
            $this->errorOutput('Konfigurationsfehler: Ungültiger Schlüssel', __FILE__, __LINE__);
        }

        $this->handleRequest($this->basePageConf['tabledef_data'][$key]);

        $this->okOutput();
    }

    protected function handleRequest($confArr) {
        $classname = array_shift($confArr);
        if (!class_exists($classname)) {
            $this->errorOutput('Konfigurationsfehler - Bitte wenden Sie sich an den Administrator', __FILE__, __LINE__, $classname . ' fehlt');
        }
        
        //Angegebene Klasse liefert HTML
        try {
            $obj = new $classname($confArr);
            $this->rslt['html'] = $obj->getHtml();
            if ($this->rslt['html'] === '') {
                $this->errorOutput('Technischer Fehler - Bitte wenden Sie sich an den Administrator.', __FILE__, __LINE__);
            }
        } catch (\Exception $exc) {
            $this->errorOutput('Technischer Fehler - Bitte wenden Sie sich an den Administrator.', __FILE__, __LINE__, $exc->getMessage());
        }
    }

}

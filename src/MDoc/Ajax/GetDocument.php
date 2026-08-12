<?php
namespace PbClasses\MDoc\Ajax;
use PbClasses\Conf;
use PbModule_commondocu\MConf;

/**
 * Description of GetDocument
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 21.12.2017, 17:01:13
 */
class GetDocument extends \PbClasses\Ajax\AbstractResponse {

    public function action() {
        try {
            \PbClasses\Ajax\SessionStart::start();
            
            //new \PbClasses\Debug\Logging(MConf::getAll(), __FILE__, __LINE__);
            
            
            
            $conf = MConf::get('docu_conf');
            //require_once dirname(\PbClasses\Conf::getPbClassesPath()) . '/php-markdown-lib/vendor/autoload.php'; 
          
            
            try {
                $docObj = new \PbClasses\MDoc\DocGenerate($conf);
                $page = $docObj->getPage($this->P['id']);
            } catch (\Exception $exc) {
                $this->rslt['errortext'] = 'Technischer Fehler. Das Dokument konnte nicht geladen werden';
                new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
                $this->output(__FILE__, __LINE__);
            }

            if (!$page) {
                $err = 'Fehler beim Anfordern des Dokuments: ' . $docObj->getErrors();
                $this->rslt['errortext'] = $err;
                new \PbClasses\Exception\Logging($err, __FILE__, __LINE__);
                $this->output(__FILE__, __LINE__);
            }

            //  $this->rslt['content'] = 'Seite ' . $this->P['id'];

            $this->rslt['content'] = $page;



            $this->rslt['succ'] = 'OK';
            $this->output();
        } catch ( \Exception $exc) {
               $this->rslt['errortext'] = 'Tecnnischer Fehler beim Anfordern des Dokuments.';
               new \PbClasses\Exception\Logging($exc->getMessage(), __FILE__, __LINE__);
              $this->output(__FILE__, __LINE__);
        }
    }

}

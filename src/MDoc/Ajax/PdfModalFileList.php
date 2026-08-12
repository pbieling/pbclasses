<?php
namespace PbClasses\MDoc\Ajax;
use PbClasses\Conf;
use PbModule_commondocu\MConf;
/**
 * 2021-01-30 Lädt die Dateiliste für PDF-Links
 * @author Peter Bieling - info@media-palette.de
 */
class PdfModalFileList  extends \PbClasses\Ajax\AbstractResponse{
   
    protected $conf;
    protected $t;
    protected $pdfDir;
    protected $pdfArr = array();
    
  

    public function action() {
        \PbClasses\Ajax\SessionStart::start();
       
        $this->conf = MConf::get('docu_conf');
        $this->t = new \PbClasses\PbTpl($this->conf['doc_vars']['FRONTTPLPATH'] . 'common.tpl');
        
        $this->pdfDir =  Conf::get('DOCROOT') .  $this->conf['doc_vars']['doc_media_pdf'];
        $this->getPdfList();
        $this->makeOptions();
        
        $this->okOutput();
    }
    
    protected function getPdfList() {
        if (!is_dir($this->pdfDir)) {
            $this->errorOutput('Kein PDF-Verzeichnis gefunden', __FILE__, __LINE__, $this->pdfDir);
        }
        
        /*
         * [standard_option]
<option value="{OPTION_VALUE}{ATTR}">{OPTION_LABEL}</option>
         */

        // Loop through the folder
        
        $dir = dir($this->pdfDir);
        while (false !== ($entry = $dir->read())) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            //pathinfo ( string $path , int $options = PATHINFO_DIRNAME | PATHINFO_BASENAME | PATHINFO_EXTENSION | PATHINFO_FILENAME ) 
            if (\strtolower(\pathinfo ( $entry , PATHINFO_EXTENSION)) === 'pdf') {
                new \PbClasses\Debug\Logging($entry, __FILE__, __LINE__);
                $this->pdfArr[] = $entry;
            }
            // Deep copy directories
            //$this->xcopy("$source/$entry", "$dest/$entry", $permissions);
        }

        // Clean up
        $dir->close();        
    }
    
    protected function makeOptions() {
        if (!count($this->pdfArr)) {
            $this->rslt['html']  = $this->t->fillTpl('standard_option', ['option_value', 'attr', 'option_label'], ['', '', '- Kein PDF gefunden -']);
            return;
        }
        
        $this->rslt['html'] = $this->t->fillTpl('standard_option', ['option_value', 'attr', 'option_label'], ['', '', '-PDF auswählen-']);
        foreach($this->pdfArr as $pdfFile) {
            $seRe = array('option_value' => $this->conf['doc_vars']['doc_media_pdf'] . '/' . $pdfFile,
                          'attr' => '',
                          'option_label' => $pdfFile);
            $this->rslt['html'] .= $this->t->fillTpl('standard_option', $seRe);
        }
    }
    
    
    
}

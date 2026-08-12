<?php
/**
 * Wird nicht mehr benötigt, weil PbTpl jetzt selbst einen Index ergänzen kann.
 * 
 * 
 * XmlReplace liefert gefüllten XML-Template-String aus XML-Datei oder String zurück.
 *
 * @author Peter Bieling
 */
namespace PbClasses\PDF;
class XmlReplace {
   
    public function getXml($seRe, $template, $file = true, $addMain = false, $tplName = 'main')  {
        if ($addMain && $file) { 
            $template = "[main]\n" . file_get_contents($template); 
            $file = false;
        } elseif ($addMain && ! $file) {
            $template = "[main]\n" . $template;
            $file = false;
        }
        
        $t = new \PbClasses\PbTpl($template, $file);
        return $t->fillTpl($tplName, $seRe);
        
    }
    
}

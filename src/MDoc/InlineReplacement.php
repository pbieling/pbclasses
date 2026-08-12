<?php

namespace PbClasses\MDoc;
/**
 * @author Peter Bieling - info@media-palette.de
 * Created on : 20.12.2018, 17:21:50
 */
class InlineReplacement extends \PbClasses\MDoc\DocGenerate {

    public function getHtml($text, $mdFlag = true) {
        
        $tmpText = $this->make_inline_replacements($text);   
        if ($mdFlag) {
            return $this->markdownFilter($tmpText);
        }
        
        return $tmpText;
    }

}

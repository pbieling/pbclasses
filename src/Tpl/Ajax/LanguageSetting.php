<?php

namespace PbClasses\Tpl\Ajax;


class LanguageSetting extends \PbClasses\Ajax\AbstractResponse {




    public function action() {
        \PbClasses\Ajax\SessionStart::start();
       
        
        $language = $this->P['languageselection'] ?? null;
        
        $_SESSION['selected_lang'] = $language; 
        \PbClasses\Conf::set('pref_lang', $language);
        

        $this->okOutput();
    }



}

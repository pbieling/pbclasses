<?php

namespace PbClasses\Tpl\Ajax;

use PbClasses\Conf;
use PbClasses\Exception\Messages;

class LanguageSetting extends \PbClasses\Ajax\AbstractResponse
{

    public function action()
    {
        \PbClasses\Ajax\SessionStart::start();

        $language = $this->P['languageselection'] ?? null;

        if (!$language) {


            $this->errorOutput(Messages::get('Technical error'),
                    __FILE__,
                    __LINE__,
                    'Problem with the language selection ' . print_r($this->P, true));
        }


        $_SESSION['selected_lang'] = $language;
        Conf::set('pref_lang', $language);

        $this->okOutput();
    }
}

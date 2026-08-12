<?php

namespace PbClasses\Tpl\Ajax;
use PbClasses\Conf;

/**
 * Description of HelperLanguage
 *
 * @author Peter Bieling
 */
class HelperLanguage {
     public static function languageTpl($file) {
         $language = filter_input(INPUT_POST, "pbtpl_language", FILTER_SANITIZE_SPECIAL_CHARS);
         //new \PbClasses\Debug\Logging($_POST, __FILE__, __LINE__);

         //Post parameter existing?
         if (! $language) {
             return $file;
         }
         
         //language configuration existing?
         if (! Conf::get('pbtpl_languages')) {
             return $file;
         }
         
         //Specific language has entry?
         if (!  Conf::get('pbtpl_languages')[$language]) {
             return $file;
         }
         
         //Get name of language template
         $langSuffix = Conf::get('pbtpl_languages')[$language];
         $tplfile = preg_replace('/\.tpl$/', $langSuffix . '.tpl', $file);
         
         //Does file exist?
         if (file_exists($tplfile)) {
             return $tplfile;
         }
         
         //Not found: return original filename
         return $file;
         
     }
}

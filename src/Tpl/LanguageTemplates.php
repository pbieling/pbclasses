<?php

namespace PbClasses\Tpl;

use PbClasses\Conf;

/*
 * 
 * 
 * @see project.conf.php
  'I18n' => [
  'project_lang' => 'de',
  //if no language file is given for the project language, use the second parameter and not
  //the default langauage file.
  'default_value_if_project_lang' => true,
  'supported_lang' => ['de', 'en'],
  'fallback_language' => 'en', //If the user language is not supported the fallback is English [en]
  'navigation_translation_table' => 'pbc__navigation_translation'

  ],
 * 
 */

class LanguageTemplates
{

    const FALLBACK_LANGUAGE = 'en';

    public static function get(?array $i18nConf, string $tplDir, string $fileName)
    {
        $defaultTpl = $tplDir . $fileName;

        if ($i18nConf === null) {
            return $defaultTpl;
        }

        $projectLang = $i18nConf['project_lang'] ?? self::FALLBACK_LANGUAGE;
        $prefLang =  $_SESSION['selected_lang'] ?? Conf::get('pref_lang') ?? $i18nConf['fallback_language'] ?? self::FALLBACK_LANGUAGE;
        if ($projectLang === $prefLang) {
            return $defaultTpl;
        }

        if (!isset($i18nConf['supported_lang']) || !in_array($prefLang, $i18nConf['supported_lang'])) {
            $fallbackLang = $i18nConf['fallback_language'] ?? self::FALLBACK_LANGUAGE;
            if ($fallbackLang === $projectLang) {
                return $defaultTpl;
            }
        }


        $prefTpl = $tplDir . $prefLang . '/' . $fileName;
        if (file_exists($prefTpl)) {
            return $prefTpl;
        }
        return $defaultTpl;
    }
}

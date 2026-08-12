<?php

namespace PbClasses\Exception;

use PbClasses\Conf;

/**
 * Can be used to output error messages in different languages
 *
 * @author Bieling Peter
 */
class Messages {

    private static array $messages = [
        
        'An error occured' => ['en' => 'An error occured. Please contact the administrator if the error persists.',
                              'de'  => '<h1>Es ist ein Fehler aufgetreten.</h1><p>Bitte wenden Sie sich an den Administrator, wenn der Fehler bestehen bleibt.</p>'],
        
        'Invalid credentials' => [ 'en' => 'Invalid credentials',
                                   'de' => 'Fehlerhafte Zugangsdaten'] 
        
    ];

    public static function get(string $key, ?string $lang = null): string {
        if ($lang === null) {
            $lang = Conf::get('pref_lang') ?? 'en';
        }
        if (!isset(self::$messages[$key])) {
            return self::defaultMessage($lang, $key);
        }
        if (!isset(self::$messages[$key][$lang])) {
            return self::messages['en'][$key] ?? self::defaultMeassage($lang, $key);
        }
        

        return self::$messages[$key][$lang];
    }

    protected static function defaultMessage($lang, $key) {
        if ($lang === 'de') {
            return 'Es ist ein Fehler mit folgender Meldung aufgetreten: ' . $key;
        }

        return 'An error occured :  ' . $key;
    }
}

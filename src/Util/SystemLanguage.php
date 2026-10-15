<?php

namespace PbClasses\Util;

/**
 * @author Bieling Peter and google ai
 */
class SystemLanguage
{

    /**
     * Returns the language code as tow chars (i.e. de, en, fr ...)
     * 
     * @return string    first tow chars
     */
    public static function get(): string
    {
        return substr(self::getSystemLanguage(), 0, 2);
    }

    
    
    /**
     * Use this method directly if you want to get the long version of the 
     * language information.
     * E.g. 
     *  "de-DE" or "en-US" (Windows) 
     *  "de_DE" or "en_US" (Linux / macOS)
     * 
     * @return string 
     */
    public static function getSystemLanguage(): string
    {
        // 1. Determine operating system
        $os = strtoupper(substr(PHP_OS, 0, 3));

        // 2. Logic for Windows
        if ($os === 'WIN') {
            // Use PowerShell to retrieve the user interface culture.
            $shellOutput = shell_exec('powershell -NoProfile -Command "[System.Globalization.CultureInfo]::CurrentUICulture.Name"');
            if ($shellOutput) {
                return trim($shellOutput); // Returns e.g. "de-DE" or "en-US"
            }
        }
        // 3. Logic for Linux / macOS
        else {
            // Checks the common environment variables for the system language.
            foreach (['LANG', 'LC_ALL', 'LC_MESSAGES', 'LANGUAGE'] as $var) {
                $lang = getenv($var);
                if (!empty($lang)) {
                    // Strips off formatting such as ".UTF-8" or "@euro"
                    if (preg_match('/^[a-z]{2,3}(_[A-Z]{2})?/', $lang, $matches)) {
                        return $matches[0]; // Returns e.g. "de_DE" or "en_US"
                    }
                }
            }
        }

        // Fallback if nothing was found
        return 'en';
    }
}

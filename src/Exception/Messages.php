<?php

declare(strict_types=1);

namespace PbClasses\Exception;

use PbClasses\Conf;

/**
 * Provides localised error/status messages.
 *
 * Messages are keyed by an English identifier string and stored as an
 * associative array of language-code → translated string pairs.
 *
 * Example:
 * ```php
 * echo Messages::get('Invalid credentials', 'de');
 * // → "Fehlerhafte Zugangsdaten"
 * ```
 *
 * ### Language resolution
 * If no language is passed to {@see get()}, it is determined in this order:
 *  1. `Conf::get('pref_lang')`
 *  2. `'en'`
 *
 * Note: A language selected by the user (`$_SESSION['selected_lang']`) is not
 * evaluated here. The class PbClasses\Session already applies it to `pref_lang`.
 *
 * ### Extending the registry
 * The built-in messages can be extended at runtime:
 *  - {@see push()}        – register a single key
 *  - {@see addMessages()} – register several keys at once
 *  - {@see loadFile()}    – load keys from a PHP file that returns an array
 *
 * Example of a message file (`messages_app.php`):
 * ```php
 * <?php
 * return [
 *     'Session expired' => [
 *         'en' => 'Your session has expired.',
 *         'de' => 'Ihre Sitzung ist abgelaufen.',
 *     ],
 * ];
 * ```
 *
 * @author Peter Bieling, supported by claude.ai
 */
class Messages
{

    /**
     * Language used as last-resort fallback.
     */
    private const DEFAULT_LANG = 'en';

    /**
     * Internal message registry.
     *
     * Structure: [ 'Message key' => [ 'en' => '...', 'de' => '...' ] ]
     *
     * @var array<string, array<string, string>>
     */
    private static array $messages = [
        'An error occurred' => [
            'en' => 'An error has occurred. Please contact the administrator if the error persists.',
            'de' => 'Es ist ein Fehler aufgetreten. Bitte wenden Sie sich an den Administrator, wenn der Fehler bestehen bleibt.',
        ],
        'Invalid credentials' => [
            'en' => 'Invalid credentials',
            'de' => 'Fehlerhafte Zugangsdaten',
        ],
        'Configuration error' => [
            'en' => 'A configuration error has occurred. Please contact the administrator.',
            'de' => 'Es ist ein Konfigurationsfehler aufgetreten. Bitte wenden Sie sich an den Administrator.',
        ],
        'Technical error' => [
            'en' => 'A technical error has occurred. Please contact the administrator.',
            'de' => 'Es ist ein technischer Fehler aufgetreten. Bitte wenden Sie sich an den Administrator.',
        ],
        'Operation not permitted' => [
            'en' => 'Operation not permitted.',
            'de' => 'Vorgang nicht erlaubt.',
        ],
        'Operation not possible' => [
            'en' => 'Operation not possible',
            'de' => 'Vorgang nicht mögllich'
        ],
        'Invalid input' => [
            'en' => 'Invalid input',
            'de' => 'Ungültige Eingabe'
        ]
    ];

    /**
     * Returns the translated message for the given key and language.
     *
     * Resolution order:
     *  1. Exact match for `$key` + `$lang`
     *  2. English fallback for `$key`
     *  3. Generic default message containing the raw key
     *
     * @param string      $key  Message identifier (e.g. 'Invalid credentials').
     * @param string|null $lang Language code (e.g. 'en', 'de'); matched exactly.
     *                          If `null`, `Conf::get('pref_lang')` is used, falling back to 'en'.
     */
    public static function get(string $key, ?string $lang = null): string
    {
        $lang ??= self::resolveLanguage();

        if (!isset(self::$messages[$key])) {
            return self::defaultMessage($lang, $key);
        }

        return self::$messages[$key][$lang] ?? self::$messages[$key][self::DEFAULT_LANG] ?? self::defaultMessage($lang, $key);
    }

    /**
     * Determines the language to use when none is passed explicitly.
     *
     * Order: `Conf::get('pref_lang')`, 'en'. A value that is not a non-empty
     * string is ignored. The user's session language is already reflected in
     * `pref_lang` by the configuration/initialisation class.
     */
    private static function resolveLanguage(): string
    {
        $preferred = Conf::get('pref_lang');
        if (is_string($preferred) && $preferred !== '') {
            return $preferred;
        }

        return self::DEFAULT_LANG;
    }

    /**
     * Returns a generic fallback message when no registered translation exists.
     *
     * @param string $lang Language code used to localise the fallback text itself.
     * @param string $key  The unresolved message key, appended verbatim.
     */
    protected static function defaultMessage(string $lang, string $key): string
    {
        return match ($lang) {
            'de' => 'Es ist ein Fehler mit folgender Meldung aufgetreten: ' . $key,
            default => 'An error occurred: ' . $key,
        };
    }

    /**
     * Registers a single message key with its translations.
     *
     * @param string               $key           Message identifier.
     * @param array<string,string> $languageArray Map of language-code → translation.
     * @param bool                 $replaceFlag   `false` (default): merge into an existing key. Languages
     *                                            contained in `$languageArray` are added or overwritten,
     *                                            all other languages of that key remain intact.
     *                                            `true`: replace the entire entry of an existing key.
     */
    public static function push(string $key, array $languageArray, bool $replaceFlag = false): void
    {
        if (!$replaceFlag && isset(self::$messages[$key])) {
            $languageArray = array_merge(self::$messages[$key], $languageArray);
        }

        self::$messages[$key] = $languageArray;
    }

    /**
     * Registers multiple message keys at once.
     *
     * Each key is forwarded to {@see push()}, so `$overwriteFlag` has the same
     * meaning as `$replaceFlag` there:
     *  - `false` (default): translations are merged into existing keys.
     *  - `true`: existing keys are replaced completely.
     *
     * @param array<string, array<string,string>> $messageArray  Keys mapped to their language arrays.
     * @param bool                                $overwriteFlag Whether to replace existing keys entirely.
     */
    public static function addMessages(array $messageArray, bool $overwriteFlag = false): void
    {
        foreach ($messageArray as $key => $languageArray) {
            self::push($key, $languageArray, $overwriteFlag);
        }
    }

    /**
     * Loads messages from a PHP file and registers them via {@see addMessages()}.
     *
     * The file must return an array in the format
     * `[ 'Message key' => [ 'en' => '...', 'de' => '...' ] ]`.
     * Existing keys are merged, not replaced. Only load files from trusted
     * locations, as the file is executed with `require`.
     *
     * @param string $file                Complete path to the file.
     * @param bool   $exceptionIfNotFound Throw an exception (default) if the file does not exist or is
     *                                    not readable. If `false`, the problem is logged and `false` is returned.
     *
     * @return bool `true` if the file was loaded, `false` if it was not found (only when
     *              `$exceptionIfNotFound` is `false`).
     *
     * @throws \RuntimeException         If the file is not found/readable and `$exceptionIfNotFound` is `true`.
     * @throws \UnexpectedValueException If the file does not return an array.
     */
    public static function loadFile(string $file, bool $exceptionIfNotFound = true): bool
    {
        if (!is_file($file) || !is_readable($file)) {
            if ($exceptionIfNotFound) {
                throw new \RuntimeException('File not found or not readable: ' . $file);
            }

            new Logging('File ' . $file . ' not found or not readable.', __FILE__, __LINE__);
            return false;
        }

        // Isolated scope: the included file cannot access or overwrite local variables.
        $messageArray = (static fn(): mixed => require $file)();

        if (!is_array($messageArray)) {
            throw new \UnexpectedValueException('Message file must return an array: ' . $file);
        }

        self::addMessages($messageArray);
        return true;
    }

    /**
     * Returns the complete message registry; intended primarily for debugging.
     *
     * @return array<string, array<string, string>>
     */
    public static function getAll(): array
    {
        return self::$messages;
    }
}

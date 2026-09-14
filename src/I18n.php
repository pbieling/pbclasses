<?php
namespace PbClasses;
use PbClasses\Conf;

class I18n
{
    const FALLBACK_LANGUAGE = 'en';

    private static ?I18n $instance = null;

    private array $translations = [];
    private array $registeredFiles = [];
    private string $fallbackLanguage;

    /**
     * Private constructor to prevent direct instantiation.
     * Use I18n::getInstance() instead.
     */
    private function __construct(?array $files = null)
    {
        if ($files) {
            $this->register($files);
        }
        /*
         *                 'I18n' => [
          'project_lang' => 'de',
          'supported_lang' => ['de', 'en'],
          'fallback_language' => 'en', //If the user language is not supported the fallback is English [en]
          'navigation_translation_table' => 'pbc__navigation_translation'
          ],
         */
        $this->fallbackLanguage = Conf::get(['I18n', 'fallback_language']) ?? self::FALLBACK_LANGUAGE;
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserializing of the singleton instance.
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }

    /**
     * Returns the single instance of I18n, creating it if necessary.
     * $files is only used the first time the instance is created;
     * on subsequent calls it is ignored. Use register() afterwards
     * if you need to add more files to an existing instance.
     */
    public static function getInstance(?array $files = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($files);
        } elseif ($files) {
            self::$instance->register($files);
        }
        return self::$instance;
    }

    public function register(array $files): void
    {
        foreach ($files as $key => $fileName) {
            $path = ($fileName === basename($fileName)) ? Conf::get('APATH') . 'system/languages/' : '';
            $this->registeredFiles[$key] = $path . $fileName;
        }
        //new \PbClasses\Debug\Logging($this->registeredFiles, __FILE__, __LINE__);
    }

    public function get(string $key, ?string $default = null): mixed
    {
        $keyArr = explode('.', $key);
        if (count($keyArr) == 1) {
            return $default ?? $key;
        }
        $fileKey = array_shift($keyArr);
        if (isset($this->registeredFiles[$fileKey])) {
            $this->optionallyLoadLanguageFile($fileKey);
        }

        //Possible typo i.e. 'table' instead of 'tables' 
        if (! isset($this->translations[$fileKey])) {
            return $default ?? $key;
        }
        $value = $this->translations[$fileKey];
        foreach ($keyArr as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default ?? $key;
            }
            $value = $value[$part];
        }
        //Test:
        if (is_array($value)) {
            return $value; //For some JavaScript appllications the whole array ist needed. I.e. labels for buttons
            //are needed in several languages
        }
        
        
        return is_string($value) ? $value : ($default ?? $key);
    }

    private function load($fileKey): void
    {

        /*
         *                 'I18n' => [
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
        $lang =  $_SESSION['selected_lang'] ?? Conf::get('pref_lang');
        if ($lang === Conf::get(['I18n', 'project_lang' ]) && Conf::get(['I18n', 'default_value_if_project_lang']) === true) {
            $this->translations[$fileKey] = [];
            return;
        }
        $file = $this->registeredFiles[$fileKey] . '_' . $lang . '.php';
        if (!is_file($file)) {
            $file = $this->registeredFiles[$fileKey] . '_' . $this->fallbackLanguage . '.php';
        }
        if (!is_file($file)) {
            $this->translations[$fileKey] = [];
            return;
        }
        $this->translations[$fileKey] = require $file ?? [];
    }

    private function optionallyLoadLanguageFile($fileKey)
    {
        //Already loaded
        if (isset($this->translations[$fileKey])) {
            return;
        }
        $this->load($fileKey);
    }
    
    public function map($confKey, $seReArr) {
        
        $translated = [];
        foreach ($seReArr as $searchKey => $default) {
            $translated[$searchKey] = $this->get($confKey . '.' . $searchKey, $default);
        }
        return $translated;
    }
}

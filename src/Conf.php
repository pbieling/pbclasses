<?php
namespace PbClasses;

/**
 * Configuration management class
 * 
 * Provides static methods for storing and retrieving application configuration.
 * Supports nested configuration access via array paths.
 */
class Conf {
    private static array $config = [];

    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public static function set(string $key, mixed $value): void {
        self::$config[$key] = $value;
    }
    
    /**
     * Get configuration value by key or key path
     * 
     * Supports nested access via array of keys:
     * Conf::get(['page_conf', 'class_param', 'DocTree', 'docDbPrefix'])
     * 
     * @param string|array $key Single key or array of keys for nested access
     * @return mixed Configuration value or null if not found
     */
    public static function get(string|array $key): mixed {
        if (is_string($key)) {
            $key = [$key];
        }
        
        $tempConfig = &self::$config;
        foreach ($key as $index) {
            if (!isset($tempConfig[$index])) {
                return null;
            }
            $tempConfig = &$tempConfig[$index];
        }
        return $tempConfig;
    }

    /**
     * Initialize configuration with an array
     * 
     * Replaces existing configuration completely.
     * 
     * @param array $configArray Configuration array
     * @return void
     */
    public static function init(array $configArray): void {
        self::$config = $configArray;
    }

    /**
     * Merge configuration with existing values
     * 
     * @param array $configArray Configuration array to merge
     * @return void
     */
    public static function merge(array $configArray): void {
        self::$config = array_merge(self::$config, $configArray);
    }

    /**
     * Get all configuration values
     * 
     * @return array Complete configuration array
     */
    public static function getAll(): array {
        return self::$config;
    }

    /**
     * Add a key-value pair to an existing configuration array
     * 
     * @param string $configKey Configuration key that must be an array
     * @param string $key Key to add
     * @param mixed $value Value to add
     * @return void
     * @throws \Exception If config key doesn't exist or is not an array
     */
    public static function addKV(string $configKey, string $key, mixed $value): void {
        if (!isset(self::$config[$configKey])) {
            throw new \Exception($configKey . ' is not defined');
        }
        if (!is_array(self::$config[$configKey])) {
            throw new \Exception($configKey . ' is no array');
        }
        self::$config[$configKey][$key] = $value;
    }

    /**
     * Merge key-value pairs into an existing configuration array
     * 
     * @param string $configKey Configuration key that must be an array
     * @param array $keyValueArray Associative array to merge
     * @return void
     * @throws \Exception If config key doesn't exist or is not an array
     */
    public static function mergeKV(string $configKey, array $keyValueArray): void {
        if (!isset(self::$config[$configKey])) {
            throw new \Exception($configKey . ' is not defined');
        }
        if (!is_array(self::$config[$configKey])) {
            throw new \Exception($configKey . ' is no array');
        }
        self::$config[$configKey] = array_merge(self::$config[$configKey], $keyValueArray);
    }

    /**
     * Get the file system path of this library
     * 
     * @return string Directory path
     */
    public static function getPbClassesPath(): string {
        return __DIR__;
    }
}
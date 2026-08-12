<?php

declare(strict_types=1);

namespace PbClasses\Ajax;

use PbClasses\Conf;
use PbClasses\Exception\Logging;

/**
 * Retrieves page configuration for AJAX requests.
 *
 * This class provides access to page-specific configuration data
 * that is injected into the DOM and passed via AJAX parameters.
 *
 * AJAX usage:
 *   params.pbconfdata = $('body').data('pbconfdata');
 *
 * HTML source:
 *   <body data-subroot="{DATA_SUBROOT}"
 *         data-pbconfdata='{PBCONFDATA}'>
 *
 * Page configuration example:
 *   'se_re' => [
 *       'pagetitle'   => 'Invoices',
 *       'mainheader'  => 'Invoices - Overview',
 *       'pbconfdata'  => json_encode(['pid' => 13])
 *   ]
 *
 * @author Peter Bieling
 */
final class BasePageConf
{
    /**
     * Retrieves page configuration by page ID
     *
     * @param int|null $pid Page ID
     * @return array|false Configuration array or false on failure
     */
    public static function get(?int $pid): array|false
    {
        if ($pid === null || $pid <= 0) {
            return false;
        }

        $db = Conf::get('db');
        $backnaviTable = Conf::get(['db_tables', 'backnavi']);

        if (!$backnaviTable) {
            new Logging(
                'No backnavi table found',
                __FILE__,
                __LINE__
            );
            return false;
        }

        $url = self::fetchUrlFromDatabase($db, $backnaviTable, $pid);
        
        if ($url === null) {
            return false;
        }

        return self::loadConfiguration($url, $pid);
    }

    /**
     * Fetches URL from database for given page ID
     *
     * @param object $db Database connection
     * @param string $table Table name
     * @param int $pid Page ID
     * @return string|null URL or null on failure
     */
    private static function fetchUrlFromDatabase(
        object $db,
        string $table,
        int $pid
    ): ?string {
        $where = [
            'ID' => $pid,
            'Hide' => 0
        ];

        $result = $db->select($table, 'URL', $where, '', 'LIMIT 1');

        if (!$result) {
            new Logging(
                sprintf('Keinen Backnavi-Eintrag für PID %d gefunden', $pid),
                __FILE__,
                __LINE__
            );
            return null;
        }

        $url = trim($result[0][0] ?? '');

        if ($url === '') {
            new Logging(
                sprintf('Leeren URL-Eintrag für PID %d gefunden', $pid),
                __FILE__,
                __LINE__
            );
            return null;
        }

        return $url;
    }

    /**
     * Loads configuration file
     *
     * @param string $url URL path
     * @param int $pid Page ID (for error logging)
     * @return array|false Configuration array or false on failure
     */
    private static function loadConfiguration(string $url, int $pid): array|false
    {
        $configPath = Conf::get('APATH') . 'config/' . $url;

        if (!file_exists($configPath)) {
            new Logging(
                sprintf('Configuration %s not found', $configPath),
                __FILE__,
                __LINE__
            );
            return false;
        }

        // Security check: ensure the file is within the config directory
        $realConfigPath = realpath($configPath);
        $realConfigDir = realpath(Conf::get('APATH') . 'config/');

        if ($realConfigPath === false || 
            $realConfigDir === false || 
            !str_starts_with($realConfigPath, $realConfigDir)) {
            new Logging(
                sprintf('No valid configuration path: %s', $configPath),
                __FILE__,
                __LINE__
            );
            return false;
        }

        return require $realConfigPath;
    }
}
<?php

namespace PbClasses;

use PbClasses\Conf;

/**
 * Secure Session Management Class
 * Handles session initialization with proper security configurations.
 * 
 * @todo Session regeneration has to update the table online in MaybeRegenerateSession
 */
class Session
{

    const SESSION_MAX_LIFETIME = 54000; // 15 hours (3600 * 15)
    // Valid session ID lengths (PHP default, custom configurations)
    const VALID_SESSION_ID_LENGTHS = [26, 32, 40, 52, 64];

    protected $sessConfig = [];

    /**
     * Initialize session with security-hardened configuration
     * 
     * @throws \RuntimeException if session initialization fails
     */
    public function __construct()
    {
        try {
            $this->loadSessionConfiguration();
            $this->configureSession();
            $this->startSession();
            $this->validateSessionId();
        } catch (\Exception $e) {
            // Log error appropriately in production
            error_log("Session initialization failed: " . $e->getMessage());
            throw new \RuntimeException("Failed to initialize session", 0, $e);
        }
    }

    /**
     * Load session configuration based on login group
     */
    protected function loadSessionConfiguration(): void
    {
        $loginGroup = $this->determineLoginGroup();

        if ($loginGroup && isset(Conf::get('sess_config')[$loginGroup])) {
            $this->sessConfig = Conf::get('sess_config')[$loginGroup];
        } else {
            $this->sessConfig = Conf::get('sess_config');
        }

        // Ensure we have an array configuration
        if (!is_array($this->sessConfig)) {
            $this->sessConfig = [];
        }
    }

    /**
     * Determine the login group from page configuration or AJAX request
     * 
     * @return string|false Login group name or false if not found
     */
    protected function determineLoginGroup()
    {
        $pageConf = Conf::get('page_conf');

        // Check if this is an AJAX request with specific session
        if (isset($pageConf['ajaxclass'])) {
            return $this->getAjaxLoginGroup();
        }

        // Check for direct login group configuration
        if (isset($pageConf['login_group']) &&
                isset(Conf::get('sess_config')[$pageConf['login_group']])) {
            return $pageConf['login_group'];
        }

        return false;
    }

    /**
     * Configure session parameters with security hardening
     */
    protected function configureSession(): void
    {
        // Set custom session save path if configured
        if (isset($this->sessConfig['SESSPATH'])) {
            $sessionPath = $this->sessConfig['SESSPATH'];

            // Validate session path exists and is writable
            if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
                throw new \RuntimeException("Session path is not writable: {$sessionPath}");
            }

            session_save_path($sessionPath);
        }

        // Set custom session name if configured
        if (isset($this->sessConfig['SESSNAME'])) {
            $sessionName = preg_replace('/[^a-zA-Z0-9]/', '', $this->sessConfig['SESSNAME']);
            session_name($sessionName);
        }

        // Configure session garbage collection
        $sessMaxLife = $this->sessConfig['session_max_lifetime'] ?? self::SESSION_MAX_LIFETIME;
        ini_set('session.gc_maxlifetime', (string) $sessMaxLife);

        // Set secure cookie parameters
        $this->setSecureCookieParams();

        // Additional security settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
    }

    /**
     * Set secure cookie parameters for session
     */
    protected function setSecureCookieParams(): void
    {
        // Detect if we're on HTTPS
        $isSecure = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        // Allow configuration override for secure flag
        if (isset($this->sessConfig['cookie_secure'])) {
            $isSecure = (bool) $this->sessConfig['cookie_secure'];
        }

        $cookiePath = $this->sessConfig['cookie_path'] ?? '/';
        $cookieDomain = $this->sessConfig['cookie_domain'] ?? '';
        $sameSite = $this->sessConfig['cookie_samesite'] ?? 'Lax';

        // For PHP 7.3+, use array syntax
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookiePath,
                'domain' => $cookieDomain,
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => $sameSite
            ]);
        } else {
            // Fallback for older PHP versions
            session_set_cookie_params(0, $cookiePath, $cookieDomain, $isSecure, true);
        }
    }

    /**
     * Start the session
     * 
     * @throws \RuntimeException if session start fails
     */
    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!session_start()) {
            throw new \RuntimeException("Failed to start session");
        }
    }

    /**
     * Validate session ID and regenerate if necessary
     */
    protected function validateSessionId(): void
    {
        $sessionId = session_id();

        if (empty($sessionId)) {
            throw new \RuntimeException("Session ID is empty");
        }

        $length = strlen($sessionId);
        $isValidLength = in_array($length, self::VALID_SESSION_ID_LENGTHS, true);

        // Also check if session ID contains only valid characters
        $isValidFormat = preg_match('/^[a-zA-Z0-9,-]{' . $length . '}$/', $sessionId);

        if (!$isValidLength || !$isValidFormat) {
            $this->regenerateSessionId();
        }

        // Regenerate session ID periodically for security
        $this->maybeRegenerateSessionId();
    }

    /**
     * Regenerate session ID with proper cleanup
     * 
     * @param bool $deleteOldSession Whether to delete the old session data
     */
    protected function regenerateSessionId(bool $deleteOldSession = true): void
    {
        if (!session_regenerate_id($deleteOldSession)) {
            error_log("Failed to regenerate session ID");
        }
    }

    /**
     * Periodically regenerate session ID to prevent session fixation
     * Regenerates every 30 minutes by default
     */
    protected function maybeRegenerateSessionId(): void
    {
        $regenerateInterval = $this->sessConfig['regenerate_interval'] ?? self::SESSION_MAX_LIFETIME;     //1800; // 30 minutes

        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
            return;
        }

        if (time() - $_SESSION['last_regeneration'] > $regenerateInterval) {
            $this->regenerateSessionId(false); // Keep session data
            $_SESSION['last_regeneration'] = time();
        }
    }

    /**
     * Determine login group from AJAX request cookie
     * 
     * @return string|false Login group name or false if not found
     */
    protected function getAjaxLoginGroup()
    {
        if (!isset($_SERVER['HTTP_COOKIE'])) {
            return false;
        }

        $cookies = $this->parseCookies($_SERVER['HTTP_COOKIE']);
        $sessionConf = Conf::get('sess_config');

        if (!is_array($sessionConf)) {
            return false;
        }

        // Search for matching session name in configuration
        foreach ($sessionConf as $groupName => $groupConfig) {
            if (!is_array($groupConfig) || !isset($groupConfig['SESSNAME'])) {
                continue;
            }

            $sessionName = $groupConfig['SESSNAME'];

            if (isset($cookies[$sessionName])) {
                return $groupName;
            }
        }

        return false;
    }

    /**
     * Parse HTTP cookie header into associative array
     * 
     * @param string $cookieHeader Raw cookie header value
     * @return array Associative array of cookie name => value pairs
     */
    protected function parseCookies(string $cookieHeader): array
    {
        $cookies = [];

        // Split cookies by semicolon and space
        $cookiePairs = explode('; ', $cookieHeader);

        foreach ($cookiePairs as $cookiePair) {
            // Split only on first equals sign (value might contain =)
            $parts = explode('=', $cookiePair, 2);

            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                // Only add non-empty cookie names
                if ($name !== '') {
                    $cookies[$name] = $value;
                }
            }
        }

        return $cookies;
    }

    /**
     * Destroy the current session
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Delete session cookie
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly']
                );
            }

            session_destroy();
        }
    }

    /**
     * Get current session configuration
     * 
     * @return array Session configuration
     */
    public function getConfig(): array
    {
        return $this->sessConfig;
    }
}

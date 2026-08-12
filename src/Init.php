<?php

namespace PbClasses;

use PbClasses\Conf;
use PbClasses\DB\SqlPdo;
use PbClasses\Session;
use PbClasses\User;
use PbClasses\View\ShowPage;
use PbClasses\Exception\Logging;
use PbClasses\Exception\Messages;

/**
 * Init class for framework initialization
 *
 * This class is responsible for:
 * - Initializing paths and configuration
 * - Loading page configuration based on the requested page
 * - Database connection and session management
 * - Routing of Ajax classes and regular pages
 * - Error handling and 404 responses
 *
 * @package PbClasses
 */
class Init
{

    /**
     * Path to the admin directory
     * @var string
     */
    private string $apath;

    /**
     * Path to the configuration directory
     * @var string
     */
    private string $confpath;

    /**
     * Base path of the project
     * @var string
     */
    private string $base;

    /**
     * Configuration of the current page
     * @var array
     */
    private array $pageConfig = [];

    /**
     * Database connection (PDO or mysqli)
     * @var object|null
     */
    private ?object $db = null;

    /**
     * Constructor - Initializes the framework
     *
     * @param string|null $dir Base directory (default: getcwd())
     * @param string|null $apath Admin path (default: $dir/admin)
     * @param array|null $pageConfigParam Optional direct page configuration
     * @throws \Exception On critical errors during initialization
     */
    public function __construct(?string $dir = null, ?string $apath = null, ?array $pageConfigParam = null)
    {
        try {
            $this->initializePaths($dir, $apath);
            $this->initializeConfiguration();
            $this->loadProjectConfiguration();

            $srcpage = $this->getSrcPage();
            $this->pageConfig = $this->loadPageConfiguration($srcpage, $pageConfigParam);

            $this->processGetParameters();
            $this->initializeDatabase();
            $this->handleSessionAndAuth($srcpage);
            $this->executePageLogic();
        } catch (\Exception $exc) {
            $this->handleFatalError($exc);
        }
    }

    /**
     * Initializes the paths for the framework
     *
     * Sets the paths for:
     * - Admin directory (apath)
     * - Configuration directory (confpath)
     * - Base directory (base)
     *
     * @param string|null $dir Base directory, if null getcwd() is used
     * @param string|null $apath Admin path, if null $dir/admin is used
     * @return void
     */
    private function initializePaths(?string $dir, ?string $apath): void
    {
        $dir = $dir ?? getcwd();
        $this->apath = $apath ? rtrim($apath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $dir . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR;
        $this->confpath = $this->apath . 'config' . DIRECTORY_SEPARATOR;
        $this->base = $dir . DIRECTORY_SEPARATOR;
    }

    /**
     * Initializes the framework base configuration
     *
     * Sets default configuration values for paths, database and session.
     * These values can later be overridden by the project configuration.
     *
     * @return void
     */
    private function initializeConfiguration(): void
    {
        Conf::init([
            'APATH' => $this->apath,
            'MODULE_PATH' => dirname($this->apath) . '/modules/',
            'HTTP_ROOT' => '/',
            'PBCLASSES_PATH' => __DIR__,
            'BACKTPLPATH' => $this->apath . 'backtemplates' . DIRECTORY_SEPARATOR,
            'FRONTTPLPATH' => $this->apath . 'fronttemplates' . DIRECTORY_SEPARATOR,
            'BASEPATH' => $this->base,
            'PAGE_ID' => false,
            'page_conf' => [],
            'with_sql' => true,
            'db_credentials' => [],
            'db_tables' => [],
            'sess_config' => [],
            'pref_lang' => substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) ?? 'en',
        ]);
    }

    /**
     * Loads the project-specific configuration
     *
     * Includes the project.conf.php which overrides or extends
     * the framework configuration.
     *
     * @return void
     * @throws \RuntimeException If the project configuration is not found
     */
    private function loadProjectConfiguration(): void
    {
        $projectConfig = $this->apath . 'project.conf.php';
        if (!file_exists($projectConfig)) {
            throw new \RuntimeException("Project configuration not found: {$projectConfig}");
        }
        require $projectConfig;
    }

    /**
     * Determines the requested page from the GET parameter 'srcpage'
     *
     * Handles special cases like:
     * - Logout pages (redirect to index.php)
     * - Parameterized routes (e.g. blog articles)
     *
     * @return string Sanitized srcpage or empty string for index
     */
    private function getSrcPage(): string
    {
        $srcpage = filter_input(INPUT_GET, 'srcpage', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

        if ($srcpage && preg_match('~admin/logout\.php$~', $srcpage)) {
            Conf::set('do_logout', true);
            return 'index.php';
        }

        $paramPages = Conf::get('with_params');
        if ($paramPages !== null) {
            $srcpage = $this->setParam($srcpage, $paramPages);
        }

        return $srcpage;
    }

    /**
     * Loads the configuration for the requested page
     *
     * Determines which configuration to load based on srcpage:
     * - Directly passed configuration (pageConfigParam)
     * - Index page (if srcpage is empty)
     * - File-based configuration (from config directory)
     * - Ajax class configuration (for dynamic calls)
     *
     * @param string $srcpage The requested page
     * @param array|null $pageConfigParam Optional direct configuration
     * @return array The page configuration
     */
    private function loadPageConfiguration(string $srcpage, ?array $pageConfigParam): array
    {
        if ($pageConfigParam !== null) {
            return $pageConfigParam;
        }

        if (empty($srcpage)) {
            Conf::set('SELF', '/index.php');
            return $this->requirePageConfig($this->confpath . 'index.php');
        }

        $pageconffile = $this->confpath . $srcpage;

        if (file_exists($pageconffile)) {
            return $this->loadFileBasedConfig($srcpage, $pageconffile);
        }

        return $this->loadAjaxClassConfig($srcpage);
    }

    /**
     * Loads a file-based page configuration
     *
     * Handles both files and directories (index.php is then loaded).
     * Also sets the SELF path in the configuration.
     *
     * @param string $srcpage Relative page URL
     * @param string $pageconffile Full path to the configuration file
     * @return array The page configuration
     */
    private function loadFileBasedConfig(string $srcpage, string $pageconffile): array
    {
        if (is_dir($pageconffile)) {
            $pageconffile .= DIRECTORY_SEPARATOR . 'index.php';
            if (!file_exists($pageconffile)) {
                $this->showNotFound();
            }
        }

        Conf::set('SELF', '/' . $srcpage);
        return $this->requirePageConfig($pageconffile);
    }

    /**
     * Loads the configuration for Ajax classes
     *
     * Supports different Ajax class patterns:
     * - PbClasses module Ajax classes
     * - Standalone module Ajax classes
     * - Framework Ajax classes (PG, Todo, Table, etc.)
     * - Project-specific Ajax classes
     *
     * @param string $srcpage The srcpage pointing to an Ajax class
     * @return array Configuration with 'ajaxclass' key
     */
    private function loadAjaxClassConfig(string $srcpage): array
    {
        $patterns = [
            // PbClasses module Ajax
            '~PbClasses/(.+?)/PbModule_(.+?)/(.*)\.php~' => function ($matches) {
                $nsp = str_replace('/', '\\', $matches[3]);
                $this->loadModuleConfig($matches[1], $matches[2]);
                return ['ajaxclass' => '\\PbClasses\\' . $nsp];
            },
            // Module Ajax
            '~(.+)/(PbModule_(.+)/Ajax/.*)\.php~' => function ($matches) {
                $nsp = str_replace('/', '\\', $matches[2]);
                $this->loadModuleConfig($matches[1], $matches[3]);
                return ['ajaxclass' => '\\' . $nsp];
            },
            // PbClasses Ajax
            '~(PbClasses/(?:PG|Todo|Table|Tpl|Backend|Doc|Access)/Ajax/.*)\.php~' => function ($matches) {
                $nsp = str_replace('/', '\\', $matches[1]);
                return ['ajaxclass' => '\\' . $nsp];
            },
        ];

        foreach ($patterns as $pattern => $handler) {
            if (preg_match($pattern, $srcpage, $matches)) {
                return $handler($matches);
            }
        }

        // Project classes
        $projectClassPath = $this->apath . 'classes/' . $srcpage;
        if (file_exists($projectClassPath)) {
            return $this->loadProjectAjaxClass($srcpage);
        }

        $this->showNotFound();
    }

    /**
     * Loads the module configuration and initializes the module
     *
     * Includes the module.conf.php and instantiates the module's Init class.
     *
     * @param string $modulePath Relative path to the module
     * @param string $moduleName Name of the module (without PbModule_ prefix)
     * @return void
     */
    private function loadModuleConfig(string $modulePath, string $moduleName): void
    {
        $moduleConfigPath = Conf::get('MODULE_PATH') . $modulePath . '/module.conf.php';
        if (file_exists($moduleConfigPath)) {
            require_once $moduleConfigPath;
            $modclassname = '\\PbModule_' . $moduleName . '\\Init';
            if (class_exists($modclassname)) {
                new $modclassname('ajax');
            }
        }
    }

    /**
     * Loads a project-specific Ajax class
     *
     * Creates the fully qualified class name based on the
     * project namespace and file path.
     *
     * @param string $srcpage Path to the Ajax class file
     * @return array Configuration with 'ajaxclass' key
     */
    private function loadProjectAjaxClass(string $srcpage): array
    {
        $projectNamespace = Conf::get('own_namespace') ?? Conf::get('project_name');
        $nsp = str_replace('/', '\\', preg_replace('/\.php$/', '', $srcpage));
        return ['ajaxclass' => '\\' . $projectNamespace . '\\' . $nsp];
    }

    /**
     * Loads a page configuration via require
     *
     * Security check that an array is returned.
     *
     * @param string $path Path to the configuration file
     * @return array The loaded configuration or empty array
     */
    private function requirePageConfig(string $path): array
    {
        $pageConfig = require $path;
        return is_array($pageConfig) ? $pageConfig : [];
    }

    /**
     * Processes GET parameters from the REQUEST_URI
     *
     * Extracts the query string and stores the parameters
     * in the configuration for later access.
     *
     * @return void
     */
    private function processGetParameters(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $getParamStr = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($getParamStr !== null) {
            $this->setGetParam($getParamStr);
        }
    }

    /**
     * Initializes the database connection
     *
     * Checks if SQL is needed, sets up the table configuration
     * and establishes the connection (PDO or mysqli).
     *
     * @return void
     */
    private function initializeDatabase(): void
    {
        Conf::set('page_conf', $this->pageConfig);

        $withSql = $this->pageConfig['with_sql'] ?? Conf::get('with_sql');
        Conf::set('with_sql', $withSql);

        if (!$withSql) {
            return;
        }

        $this->setupDatabaseTables();
        $this->connectDatabase();
    }

    /**
     * Sets up the database table configuration
     *
     * Adds standard framework tables with optional prefix
     * if no tables are configured yet.
     *
     * @return void
     */
    private function setupDatabaseTables(): void
    {
        if (count(Conf::get('db_tables')) > 0) {
            return;
        }

        $tablePrefix = Conf::get('table_prefix') ?? '';
        $fwTables = ['backnavi', 'logging', 'user', 'online', 'groupuser'];

        $tables = [];
        foreach ($fwTables as $table) {
            $tables[$table] = $tablePrefix . $table;
        }

        Conf::set('db_tables', $tables);
    }

    /**
     * Establishes the database connection using PDO
     *
     * Sets the charset to utf8mb4 for full Unicode support.
     *
     * @return void
     */
    private function connectDatabase(): void
    {
        $credentials = Conf::get('db_credentials');
        $this->db = new SqlPdo($credentials);

        $this->db->query("SET NAMES utf8mb4");
        Conf::set('db', $this->db);
    }

    /**
     * Handles session management and authentication
     *
     * Loads BackNavi data, checks if session is required,
     * initializes session/user and validates access rights.
     *
     * @param string $srcpage The requested page (for session tracking)
     * @return void
     */
    private function handleSessionAndAuth(string $srcpage): void
    {
        $pageId = $this->pageConfig['PAGE_ID'] ?? null;
        if (empty($pageId)) {
            return;
        }

        Conf::set('PAGE_ID', $pageId);
        $this->loadBackNaviData($pageId);

        $isSession = $this->shouldUseSession();

        if ($isSession) {
            $this->initializeSession($srcpage);
        } else {
            $this->validatePublicAccess();
        }
    }

    /**
     * Loads the BackNavi data for the current page
     *
     * Retrieves additional information from the backnavi table
     * like AccessLevel, title, etc.
     *
     * @param int $pageId The page ID
     * @return void
     */
    private function loadBackNaviData(int $pageId): void
    {
        $backNaviTable = Conf::get('db_tables')['backnavi'] ?? null;
        if (empty($backNaviTable) || !$this->db) {
            Conf::set('backnavi_row', false);
            return;
        }

        $result = $this->db->selectAssoc(
                $backNaviTable,
                '',
                ['ID' => $pageId],
                '',
                'LIMIT 1',
        );

        Conf::set('backnavi_row', $result ? $result[0] : false);
    }

    /**
     * Checks if a session should be used
     *
     * A session is required when:
     * - 'session' in configuration is not false
     * - OR a 'login_group' is defined in the page configuration
     *
     * @return bool True if session is required
     */
    private function shouldUseSession(): bool
    {
        return Conf::get('session') !== false || isset($this->pageConfig['login_group']);
    }

    /**
     * Initializes session and user management
     * 
     * If public mode is set in the configuration there is no login and user check.
     * Be careful when using this feature!
     * (If access_tables are defined the public_mode setting does not work.)
     *
     * Starts the session, creates user object and handles logout requests.
     * Stores page ID and srcpage in session for Ajax requests.
     *
     * @param string $srcpage The requested page
     * @return void
     */
    private function initializeSession(string $srcpage): void
    {
        new Session();
        $_SESSION['base_pid'] = $this->pageConfig['PAGE_ID'];
        $_SESSION['base_srcpage'] = $srcpage;


        if (Conf::get('public_mode') === true && Conf::get('access_tables') === null) {
            $_SESSION['login'] = true;
            $_SESSION['frg'] = 0;
            $_SESSION['user_realname'] = '';
            return;
        }

        $sess = new User();

        $doLogout = Conf::get('do_logout') || filter_input(INPUT_POST, 'submitlogout') !== null || Conf::get('get_param_string') === 'grlogout';

        if ($doLogout) {
            $sess->logLogout();
        }
    }

    /**
     * Validates access to public pages without session
     *
     * Shows 404 if the page has a required_user_level > 0 or
     * an AccessLevel > 0, but no session is active.
     *
     * @return void
     */
    private function validatePublicAccess(): void
    {
        $requiredLevel = $this->pageConfig['required_user_level'] ?? 0;
        $backNaviRow = Conf::get('backnavi_row');
        $accLevel = $backNaviRow['AccLevel'] ?? 0;

        if ($requiredLevel > 0 || $accLevel > 0) {
            $this->showNotFound();
        }
    }

    /**
     * Executes the page or Ajax logic
     *
     * Decides based on the configuration whether to:
     * - Display a normal page (ShowPage)
     * - Execute an Ajax class
     *
     * @return void
     */
    private function executePageLogic(): void
    {
        if (!empty($this->pageConfig['PAGE_ID'])) {
            new ShowPage();
        } elseif (isset($this->pageConfig['ajaxclass'])) {
            $this->executeAjaxClass($this->pageConfig['ajaxclass']);
        }
    }

    /**
     * Instantiates and executes an Ajax class
     *
     * Checks if the class exists and creates an instance.
     * Errors are logged.
     *
     * @param string $className Fully qualified class name
     * @return void
     */
    private function executeAjaxClass(string $className): void
    {
        try {
            if (!class_exists($className)) {
                new Logging("Class not found: {$className}", __FILE__, __LINE__);
                $this->showNotFound();
                return;
            }
            new $className();
        } catch (\Exception $exc) {
            new Logging(
                    $exc->getMessage() . ' ' . $exc->getTraceAsString(),
                    __FILE__,
                    __LINE__,
            );
        }
    }

    /**
     * Displays a 404 Not Found page
     *
     * Sends HTTP 404 header and shows a simple error message.
     * Terminates script execution.
     *
     * @return void (never returns)
     */
    protected function showNotFound(): void
    {
        header("HTTP/1.1 404 Not Found");
        echo '<h1>Die Seite konnte nicht gefunden werden!</h1>';
        exit;
    }

    /**
     * Parses GET parameters from a query string
     *
     * Decodes and sanitizes the parameters with htmlspecialchars.
     * Stores them in the configuration under 'get_param'.
     *
     * @param string $getParamStr Query string (e.g. "key=value&foo=bar")
     * @return void
     */
    protected function setGetParam(string $getParamStr): void
    {
        //Make params available as string
        Conf::set('get_param_string', $getParamStr);

        //and as array
        $params = [];
        foreach (explode('&', $getParamStr) as $param) {
            $parts = explode('=', $param, 2);
            $key = htmlspecialchars(urldecode($parts[0]), ENT_QUOTES, 'UTF-8');
            $value = isset($parts[1]) ? urldecode($parts[1]) : true;
            $params[$key] = $value;
        }
        Conf::set('get_param', $params);
    }

    /**
     * Route for blogs and articles with parameters
     *
     * Example in project.conf.php:
     *   'with_params' => ['artikel' => 'blog.php']
     *
     * URL: artikel/Dingens123
     *      ^^^^^^^^ keystring (slash is included in search)
     *              ^^^^^^^^^ param_string
     *
     * @param string $srcpage The requested source page
     * @param array $paramPages Array mapping route prefixes to target pages
     * @return string The resolved source page
     */
    protected function setParam(string $srcpage, array $paramPages): string
    {
        foreach ($paramPages as $keystring => $targetPage) {
            $searchString = $keystring . '/';
            if (str_starts_with($srcpage, $searchString)) {
                $param = substr($srcpage, strlen($keystring));
                Conf::set('param_string', $param);
                return $targetPage;
            }
        }

        return $srcpage;
    }

    /**
     * Handles fatal errors during initialization
     *
     * Logs the error and displays:
     * - A custom error page (system/scripterror.html) if available
     * - Or a generic error message
     *
     * Only active when PAGE_ID is set (not for Ajax requests).
     *
     * @param \Exception $exc The exception that occurred
     * @return void (never returns for page requests)
     */
    private function handleFatalError(\Exception $exc): void
    {
        new Logging(
                $exc->getMessage() . ' ' . $exc->getTraceAsString(),
                __FILE__,
                __LINE__,
        );

        header("HTTP/1.1 500 Internal Server Error");

        $errFile = $this->apath . 'system/scripterror.html';
        if (file_exists($errFile)) {
            readfile($errFile);
            exit;
        }

        echo Messages::get('An error occured');
        exit;
    }
}

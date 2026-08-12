<?php

namespace PbClasses\View;

use PbClasses\Conf;
use PbClasses\PbTpl;
use PbClasses\UserErr;
use PbClasses\UserMsg;
use PbClasses\Trees\NaviTree;
use PbClasses\Tpl\CreateMakeNavi;
use PbClasses\Tpl\SubNavi;
use PbClasses\Tpl\BreadcrumbNavi;
use Exception;

/**
 * Main page rendering class for the PB Framework
 * 
 * Handles page type detection, template management, navigation, and content display
 * 
 * @author Peter Bieling - 2026-01-16
 */
class ShowPage
{
    private const DEFAULT_OUTER_TEMPLATE = 'pb-standard.tpl';
    private const DEFAULT_CSS_FILE = '/admin/css/backnavi.css';
    private const VALID_PAGE_TYPES = ['login', 'start', 'action'];
    private const VALID_FILE_TYPES = ['css_files', 'js_files'];
    private const VALID_MESSAGE_TYPES = ['error', 'message'];
    
    private array $mainConfig = [];
    private array $pageConfig = [];
    private PbTpl $templateEngine;
    private string $pageType;
    private string $templatePath;
    private array $searchReplace = ['pbconfdata' => ''];

    public function __construct()
    {
        $this->initialize();
        $this->setupTemplateEngine();
        $this->renderPage();
    }

    /**
     * Initialize configuration and detect page type
     */
    private function initialize(): void
    {
        $this->templatePath = Conf::get('BACKTPLPATH');
        $this->pageType = $this->detectPageType();
        $this->pageConfig = Conf::get('page_conf') ?? [];
        
        $this->mainConfig = [
            'session' => $this->shouldUseSession(),
            'css_files' => $this->getGlobalCssFiles(),
            'se_re' => []
        ];
    }

    /**
     * Determine if session handling is enabled
     */
    private function shouldUseSession(): bool
    {
        if (Conf::get('session') === false) {
            return false;
        }
        
        return true === Conf::get('session') || 
               isset($this->pageConfig['login_group']);
    }

    /**
     * Get global CSS files configuration
     */
    private function getGlobalCssFiles(): array
    {
        $globalCss = Conf::get('global_css');
        return is_array($globalCss) ? $globalCss : [self::DEFAULT_CSS_FILE];
    }

    /**
     * Detect the current page type based on request and session
     */
    private function detectPageType(): string
    {
        $isSessionRequired = Conf::get('session') !== false || 
                            isset(Conf::get('page_conf')['login_group']);
        
        if ($isSessionRequired && empty($_SESSION['login'])) {
            return 'login';
        }
        
        if (!isset($_REQUEST['action'])) {
            return 'start';
        }
        
        $this->validateActionParameter($_REQUEST['action']);
        return 'action';
    }

    /**
     * Validate action parameter for security
     */
    private function validateActionParameter(string $action): void
    {
        if (!preg_match('/^[a-z0-9_]+$/', $action)) {
            throw new Exception('Invalid action parameter');
        }
    }

    /**
     * Setup main template engine
     */
    private function setupTemplateEngine(): void
    {
        $this->resolveTemplateConfiguration();
        
        $outerTemplate = $this->templatePath . $this->pageConfig['outer_tpl_file'];
        $this->templateEngine = new PbTpl($outerTemplate);
        
        Conf::set('t', $this->templateEngine);
        Conf::set('tSeRe', []);
    }

    /**
     * Resolve template configuration with cascading priority
     */
    private function resolveTemplateConfiguration(): void
    {
        // Type-specific overrides
        $typeConfig = $this->pageConfig[$this->pageType] ?? [];
        
        if (!empty($typeConfig['outer_tpl_file'])) {
            $this->pageConfig['outer_tpl_file'] = $typeConfig['outer_tpl_file'];
        }
        
        if (!empty($typeConfig['outer_tpl_name'])) {
            $this->pageConfig['outer_tpl_name'] = $typeConfig['outer_tpl_name'];
        }
        
        // Set defaults
        $this->pageConfig['outer_tpl_name'] ??= 'main';
        $this->pageConfig['outer_tpl_file'] ??= self::DEFAULT_OUTER_TEMPLATE;
        $this->pageConfig['se_re'] ??= [];
        $this->mainConfig['se_re'] ??= [];
    }

    /**
     * Main page rendering orchestration
     */
    private function renderPage(): void
    {
        $this->setContent();
        $this->setToolbar();
        $this->setNavigation();
        $this->executeAppendClasses();
        $this->display();
    }

    /**
     * Setup page content based on page type
     */
    private function setContent(): void
    {
        if ($this->pageType === 'action') {
            $this->configureActionContent();
        }
        
        $this->resolveContentTemplateConfiguration();
        $this->createContentTemplate();
        $this->executeContentClasses();
        $this->fillContentTemplate();
    }

    /**
     * Configure content for action-type pages
     */
    private function configureActionContent(): void
    {
        $action = $_REQUEST['newaction'] ?? $_REQUEST['action'];
        
        if (!isset($this->pageConfig['action'][$action])) {
            throw new Exception('Invalid action requested');
        }
        
        $actionConfig = $this->pageConfig['action'][$action];
        
        // Override templates if specified
        if (isset($actionConfig['tpl_name'])) {
            $this->pageConfig[$this->pageType]['tpl_name'] = $actionConfig['tpl_name'];
        }
        
        if (isset($actionConfig['tpl_file'])) {
            $this->pageConfig['action']['tpl_file'] = $actionConfig['tpl_file'];
        }
        
        // Merge classes and modules
        if (!empty($actionConfig['classes'])) {
            $this->pageConfig[$this->pageType]['classes'] = $actionConfig['classes'];
        }
        
        if (!empty($actionConfig['modules'])) {
            $this->pageConfig[$this->pageType]['modules'] = $actionConfig['modules'];
        }
    }

    /**
     * Resolve content template configuration with cascading
     */
    private function resolveContentTemplateConfiguration(): void
    {
        $typeConfig = &$this->pageConfig[$this->pageType];
        
        // Cascade from general to specific
        $typeConfig['tpl_name'] ??= $this->pageConfig['tpl_name'] ?? 'content';
        $typeConfig['tpl_file'] ??= $this->pageConfig['tpl_file'] ?? 
                                     "pb-standard-{$this->pageType}.tpl";
    }

    /**
     * Create and configure content template
     */
    private function createContentTemplate(): void
    {
        $templateFile = $this->templatePath . $this->pageConfig[$this->pageType]['tpl_file'];
        $contentTemplate = new PbTpl($templateFile);
        
        Conf::set('c', $contentTemplate);
        Conf::set('cSeRe', []);
    }

    /**
     * Execute content-related classes and modules
     */
    private function executeContentClasses(): void
    {
        // Prepend classes (project-wide)
        if ($prependClasses = Conf::get('prepend_classes')) {
            $this->executeClasses($prependClasses);
        }
        
        // Page-specific classes
        $typeConfig = $this->pageConfig[$this->pageType] ?? [];
        
        if (!empty($typeConfig['classes'])) {
            $this->executeClasses($typeConfig['classes']);
        }
        
        if (!empty($typeConfig['modules'])) {
            $this->executeModules($typeConfig['modules']);
        }
    }

    /**
     * Fill content template with data
     */
    private function fillContentTemplate(): void
    {
        $contentTemplate = Conf::get('c');
        $templateName = $this->pageConfig[$this->pageType]['tpl_name'];
        
        $searchReplace = array_merge(
            $this->pageConfig[$this->pageType]['sr'] ?? [],
            Conf::get('cSeRe') ?? []
        );
        
        $this->searchReplace['content'] = $contentTemplate->fillTpl($templateName, $searchReplace);
        
        // Set self reference
        $subroot = Conf::get('se_re')['data_subroot'] ?? '';
        $this->mainConfig['se_re']['self'] = $subroot . Conf::get('SELF');
    }

    /**
     * Execute classes with optional parameters
     */
    private function executeClasses(array|string $classes): void
    {
        $classes = (array) $classes;
        
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                new $value();
            } else {
                new $key($value);
            }
        }
    }

    /**
     * Execute modules with configuration
     */
    private function executeModules(array $modules): void
    {
        foreach ($modules as $moduleName => $parameters) {
            $configFile = Conf::get('MODULE_PATH') . $moduleName . '/module.conf.php';
            $namespace = require_once $configFile;
            
            if (!$namespace) {
                $namespace = $parameters['namespace'] ?? $moduleName;
                $className = "\\PbModule_{$namespace}\\Init";
            } else {
                $className = "\\{$namespace}\\Init";
            }
            
            new $className($parameters);
        }
    }

    /**
     * Execute append classes (project-wide post-processing)
     */
    private function executeAppendClasses(): void
    {
        if ($appendClasses = Conf::get('append_classes')) {
            $this->executeClasses($appendClasses);
        }
    }

    /**
     * Setup navigation based on page type
     */
    private function setNavigation(): void
    {
        if ($this->pageType === 'login' && empty($this->pageConfig[$this->pageType]['navi'])) {
            $this->searchReplace['navi'] = '';
            return;
        }
        
        $this->resolveNavigationConfiguration();
        $navigationType = strtolower($this->pageConfig[$this->pageType]['navi']);
        
        if ($navigationType === 'empty') {
            $this->searchReplace['navi'] = '';
            return;
        }
        
        $this->renderNavigation($navigationType);
        $this->renderAdditionalNavigation();
    }

    /**
     * Resolve navigation configuration with cascading
     */
    private function resolveNavigationConfiguration(): void
    {
        $this->pageConfig['navi'] ??= Conf::get('navi');
        $this->pageConfig[$this->pageType]['navi'] ??= $this->pageConfig['navi'] ?? 'navi-left';
    }

    /**
     * Render main navigation
     */
    private function renderNavigation(string $navigationType): void
    {
        $treeObj = new NaviTree(Conf::get('db_tables')['backnavi']);
        $naviTree = $treeObj->getNaviRows();
        
        $templateFile = $this->getNavigationTemplate($navigationType);
        $naviTemplate = CreateMakeNavi::getObj($templateFile, $navigationType);
        
        $this->searchReplace['navi'] = $naviTemplate->get($naviTree);
    }

    /**
     * Get navigation template file path
     */
    private function getNavigationTemplate(string $navigationType): string
    {
        $customTemplate = $this->pageConfig[$this->pageType]['navitpl'] ?? null;
        
        return $customTemplate 
            ? $this->templatePath . $customTemplate
            : $this->templatePath . $navigationType . '.tpl';
    }

    /**
     * Render additional navigation components
     */
    private function renderAdditionalNavigation(): void
    {
        if (Conf::get('subnavi')) {
            new SubNavi($this->mainConfig['se_re']);
        }
        
        if (Conf::get('breadcrumbnavi')) {
            new BreadcrumbNavi($this->mainConfig['se_re']);
        }
    }

    /**
     * Setup toolbar
     */
    private function setToolbar(): void
    {
        if ($this->pageType === 'login') {
            $this->searchReplace['toolbar'] = '';
            return;
        }
        
        $loginBlock = $this->renderLoginBlock();
        $this->searchReplace['toolbar'] = $this->templateEngine->fillTpl('toolbar', 'login', $loginBlock);
    }

    /**
     * Render login block for toolbar
     */
    private function renderLoginBlock(): string
    {
        if (empty($_SESSION['user_realname'])) {
            return '';
        }
        
        return $this->templateEngine->fillTpl('login', [
            'user_realname' => $_SESSION['user_realname']
        ]);
    }

    /**
     * Display the final page
     */
    private function display(): void
    {
        $this->displayMessages(UserErr::getAll(), 'error');
        $this->displayMessages(UserMsg::getAll(), 'message');
        $this->includeAssets('css_files');
        $this->includeAssets('js_files');
        $this->mergeSearchReplaceArrays();
        $this->includeIcons();
        
        echo $this->templateEngine->fillTpl(
            $this->pageConfig['outer_tpl_name'], 
            $this->searchReplace
        );
    }

    /**
     * Display messages or errors
     */
    private function displayMessages(array $messages, string $type): void
    {
        if (!in_array($type, self::VALID_MESSAGE_TYPES, true)) {
            throw new Exception("Invalid message type: {$type}");
        }
        
        if (empty($messages)) {
            $this->searchReplace[$type] = '';
            return;
        }
        
        $itemsHtml = $this->templateEngine->fillRowtpl(
            "{$type}_item",
            "{$type}_item",
            $messages
        );
        
        $this->searchReplace[$type] = $this->templateEngine->fillTpl(
            $type,
            "{$type}_rows",
            $itemsHtml
        );
    }

    /**
     * Include CSS or JS assets
     */
    private function includeAssets(string $fileType): void
    {
        if (!in_array($fileType, self::VALID_FILE_TYPES, true)) {
            throw new Exception("Invalid file type: {$fileType}");
        }
        
        $files = $this->collectAssetFiles($fileType);
        $this->searchReplace[$fileType] = $this->renderAssetFiles($files, $fileType);
    }

    /**
     * Collect all asset files from various sources
     */
    private function collectAssetFiles(string $fileType): array
    {
        $files = array_merge(
            $this->mainConfig[$fileType] ?? [],
            $this->pageConfig[$fileType] ?? [],
            $this->pageConfig[$this->pageType][$fileType] ?? []
        );
        
        // Add module assets
        $moduleFiles = Conf::get("pbmodule_{$fileType}");
        if ($moduleFiles) {
            $files = array_merge($files, $moduleFiles);
        }
        
        return $files;
    }

    /**
     * Render asset file includes
     */
    private function renderAssetFiles(array $files, string $fileType): string
    {
        $html = '';
        
        foreach ($files as $key => $value) {
            $searchReplace = is_array($value)
                ? ['file' => $key, 'params' => $this->buildAssetParams($value)]
                : ['file' => $value, 'params' => ''];
            
            $html .= $this->templateEngine->fillTpl($fileType, $searchReplace);
        }
        
        return $html;
    }

    /**
     * Build HTML attributes string for assets
     */
    private function buildAssetParams(array $params): string
    {
        if (! count($params)) {
            return '';
        }
        
        $parts = [];
        
        foreach ($params as $key => $value) {
            $parts[] = $key . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }
        
        return ' ' . implode(' ', $parts); //Make sure to add a blank space at the beginning.
    }

    /**
     * Merge all search-replace arrays
     */
    private function mergeSearchReplaceArrays(): void
    {
        foreach ($this->mainConfig['se_re'] as $key => $value) {
            $this->searchReplace[$key] = $value;
        }
        
        if ($confSeRe = Conf::get('se_re')) {
            foreach ($confSeRe as $key => $value) {
                $this->searchReplace[$key] = $value;
            }
        }
        
        foreach ($this->pageConfig['se_re'] as $key => $value) {
            $this->searchReplace[$key] = $value;
        }
    }

    /**
     * Include icon definitions
     */
    private function includeIcons(): void
    {
        if ($icons = Conf::get('icons')) {
            foreach ($icons as $key => $value) {
                $this->searchReplace["pbcicon_{$key}"] = $value;
            }
        }
    }

    /**
     * Public setter for search-replace values
     */
    public function setSeRe(string $key, $value): void
    {
        $this->searchReplace[$key] = $value;
    }
}
<?php

namespace PbClasses\Table\Ajax;

use PbClasses\Conf;
use PbClasses\Table\Logging\UpdateLog;
use PbClasses\Table\TableHtml;
use PbClasses\Table\InsertRow;
use PbClasses\Table\UpdateRow;
use PbClasses\Table\DeleteRow;
use PbClasses\Table\BaseFilters;
use PbClasses\Table\Event;
use PbClasses\Exception\Logging as ExcLogging;

/**
 * Allocator handles AJAX requests for table operations (CRUD)
 * Optimized version with improved performance and maintainability
 */
class Allocator extends \PbClasses\Ajax\AbstractResponse
{

    private const DEFAULT_TABLE_LIMIT_ROWS = 10;
    private const SLASH_REPLACEMENT = '-7-';
    private const MODULE_PREFIX = 'module';

    protected array $conf = [];
    protected ?array $G = [];
    protected array $allowedInsertFields = [];
    protected array $allowedUpdateFields = [];
    protected ?string $tabidExt = null;
    protected ?string $moduleName = null;
    protected ?string $modulePath = null;

    public function action(): void
    {
        $this->G = Conf::get('get_param');

        $this->validateRequiredParams();

        $tabid = $this->P['tabid'];
        $tabPath = $this->resolveTablePath($tabid);
        $tabFile = $this->buildTableFilePath($tabPath, $tabid);

        $this->loadConfiguration($tabFile);
        $this->handleSessionStart();
        $this->configureTableSettings();
        $this->configurePagination();

        $this->allocate($this->P['cmd']);
    }

    /**
     * Validate required parameters are present
     */
    private function validateRequiredParams(): void
    {
        if (empty($this->P['cmd']) || empty($this->P['tabid'])) {
            $this->errorOutput(
                    'Technischer Fehler 1',
                    __FILE__,
                    __LINE__,
                    'Parameter cmd oder tabid fehlt'
            );
        }
    }

    /**
     * Resolve the correct table path based on module or default configuration
     */
    private function resolveTablePath(string & $tabid): string
    {
        $parts = explode('_', $tabid);

        if (count($parts) >= 3 && $parts[0] === self::MODULE_PREFIX) {
            return $this->resolveModulePath($parts, $tabid);
        }

        return Conf::get('APATH') . 'tableconf/';
    }

    /**
     * Resolve module-specific table path
     */
    private function resolveModulePath(array $parts, string & $tabid): string
    {
        array_shift($parts); // Remove 'module'
        $moduleName = $this->normalizeModuleName(array_shift($parts));

        $modulePath = Conf::get('module_path') ?? dirname(Conf::get('APATH')) . '/modules/';

        $this->initializeModule($modulePath, $moduleName);

        $tabid = join('_', $parts);

        return $modulePath . $moduleName . '/tableconf/';
    }

    /**
     * Normalize module name by replacing encoded slashes
     */
    private function normalizeModuleName(string $name): string
    {
        return str_replace(self::SLASH_REPLACEMENT, '/', $name);
    }

    /**
     * Initialize module if it exists
     */
    private function initializeModule(string $modulePath, string $moduleName): void
    {
        $modclassname = '\\PbModule_' . $moduleName . '\\Init';
        $configFile = $modulePath . $moduleName . '/module.conf.php';

        if (!file_exists($configFile)) {
            return;
        }

        $modNsp = require_once $configFile;
        //The configuration file is not yet loaded. 
        //So they will be added later to the $this->conf array.
        //@see handleAllCmd()
        $this->moduleName = $moduleName;
        $this->modulePath = $modulePath;

        if (is_string($modNsp)) {
            $modclassname = '\\' . $modNsp . '\\Init';
        }

        if (class_exists($modclassname)) {
            new $modclassname('ajax');
        }
    }

    /**
     * Build the complete file path for the table configuration
     */
    private function buildTableFilePath(string $tabPath, string $tabid): string
    {

        $tabidPart = $this->extractTabidBase($tabid);
        $tabFile = $tabPath . $tabidPart . '.php';

        return str_replace(self::SLASH_REPLACEMENT, '/', $tabFile);
    }

    /**
     * Extract base tabid and optional extension
     */
    private function extractTabidBase(string $tabid): string
    {
        $parts = explode('#', $tabid);

        if (isset($parts[1])) {
            $this->tabidExt = $parts[1];
        }

        return $parts[0];
    }

    /**
     * Load table configuration file
     */
    private function loadConfiguration(string $tabFile): void
    {
        if (!file_exists($tabFile)) {
            $this->errorOutput(
                    'Technischer Fehler - Tabellenkonfiguration fehlt.',
                    __FILE__,
                    __LINE__,
                    $tabFile
            );
        }

        $this->conf = require $tabFile;
    }

    /**
     * Configure basic table settings
     */
    private function configureTableSettings(): void
    {
        $this->conf['ins_upd_table'] = $this->conf['main_table'] ?? $this->conf['table'];

        $this->conf['unique_identifier'] = $this->conf['unique_identifier'] ?? $this->conf['table'];

        if ($this->tabidExt !== null) {
            $this->conf['unique_identifier'] .= $this->tabidExt;
        }

        $this->conf['tabid'] = $this->P['tabid'];
    }

    /**
     * Configure pagination settings
     */
    private function configurePagination(): void
    {
        if (isset($this->P['index'])) {
            $this->conf['query']['table_limit_skip'] = (int) $this->P['index'];
        }

        if (!empty($this->P['limit'])) {
            $this->conf['query']['table_limit_rows'] = (int) $this->P['limit'];
        }

        $this->conf['query']['table_limit_rows'] = $this->conf['query']['table_limit_rows'] ?? self::DEFAULT_TABLE_LIMIT_ROWS;

        $this->rslt['rowlimit'] = $this->conf['query']['table_limit_rows'];
    }

    /**
     * Route command to appropriate handler
     */
    protected function allocate(string $cmd): void
    {
        $primaryKey = $this->conf['table_def']['primary_key'] ?? 'ID';

        $handlers = [
            'upd_sg' => fn() => $this->handleSingleUpdate($primaryKey),
            'switch_val' => fn() => $this->handleSwitchValue($primaryKey),
            'upd_row' => fn() => $this->handleRowUpdate($primaryKey),
            'insert_row' => fn() => $this->handleInsertRow(),
            'all' => fn() => $this->handleAllCmd(),
            'body' => fn() => $this->handleBodyCmd(),
            'delete' => fn() => $this->handleDelete($primaryKey),
            'body' => fn() => $this->handleBodyCmd(),
            'set_sort' => fn() => $this->handleSetSort(),
            'get_preset_from_classes' => fn() => $this->handleGetPresetFromClasses(),
            'dojob' => fn() => $this->handleDoJob(),
                // Add other handlers here
        ];

        if (isset($handlers[$cmd])) {
            $handlers[$cmd]();
            $this->okOutput();
        } else {
            $this->handleUnknownCommand($cmd);
        }
    }

    //Cloade optimized:
    private function handleAllCmd(): void
    {
        $this->initializeSession();
        $this->setupConfiguration();
        $this->prepareResultData();
        $this->processFilters();
        $this->generateTableOutput();
    }

    private function initializeSession(): void
    {
        // Reset session data for new table
        $_SESSION[$this->conf['unique_identifier']] = null;

        $this->addModuleConfigData();

        // Store foreign key constraint if provided
        if (isset($this->P['where_id'])) {
            $_SESSION[$this->conf['unique_identifier']]['where_id'] = $this->P['where_id'];
        }

        if (isset($this->P['scope'])) {
            $_SESSION[$this->conf['unique_identifier']]['scope'] = $this->P['scope'];
        }
    }

    private function setupConfiguration(): void
    {
        $this->makeSetup();
    }

    private function prepareResultData(): void
    {
        // Return only required fields (update, insert) for security and performance
        $this->rslt['table_structure'] = $this->getTableStructure($this->conf['ins_upd_table']);
        $this->rslt['allowed_insert'] = $this->allowedInsertFields;
        $this->rslt['allowed_update'] = $this->allowedUpdateFields;
        $this->rslt['fieldlabels'] = $this->getFieldLabels();

        if (!empty($this->conf['filters_client'])) {
            $this->rslt['filters'] = $this->conf['filters_client'];
        }

        if (!empty($this->conf['transform_values'])) {
            $this->rslt['transform_values'] = $this->conf['transform_values'];
        }

        $this->setLanguageMappings();
        $this->setModalInputModifiers();
    }

    private function getFieldLabels(): array
    {
        $langIndex = $this->P['pbtpl_language'] ?? 'de';
        $langKey = 'display_name_' . $langIndex;

        // Try language-specific labels first, then fallback to default
        if (isset($this->conf['table_def'][$langKey]) && is_array($this->conf['table_def'][$langKey])) {
            return $this->conf['table_def'][$langKey];
        }

        if (isset($this->conf['table_def']['display_name']) && is_array($this->conf['table_def']['display_name'])) {
            return $this->conf['table_def']['display_name'];
        }

        return [];
    }

    private function setLanguageMappings(): void
    {
        $langIndex = $this->P['pbtpl_language'] ?? 'de';

        if (!empty($this->conf['js_language_mapping'][$langIndex])) {
            $this->rslt['js_language_mapping'] = $this->conf['js_language_mapping'][$langIndex];
        }
    }

    private function setModalInputModifiers(): void
    {
        if (empty($this->conf['js_modal_input_modifyer'])) {
            $this->conf['js_modal_input_modifyer'] = [];
        }

        // Enable dynamic presets if configured
        if (!empty($this->conf['preset_from_class'])) {
            $this->conf['js_modal_input_modifyer']['dynamic_preset'] = true;
        }

        $this->rslt['js_modal_input_modifyer'] = !empty($this->conf['js_modal_input_modifyer']) ? $this->conf['js_modal_input_modifyer'] : false;
    }

    private function processFilters(): void
    {
        if (!empty($this->conf['get_filters'])) {
            $this->setGetFilters();
        }

        // Apply pre-configured filters for initial query
        $preFilters = $this->getPreFilters();

        if (count($preFilters) > 0) {
            $this->buildFilterCondition($preFilters);
        }
    }

    private function generateTableOutput(): void
    {
        $tableObj = new TableHtml($this->conf['table'], $this->conf);
        $this->addOwnForms();

        $this->rslt['succ'] = 'OK';
        $this->rslt['html'] = $tableObj->getTableHtml();
        $this->rslt['totalrows'] = $tableObj->getNum();
        $this->rslt['tablerows'] = $tableObj->getUnfilteredNum();
        $this->rslt['bodyrows'] = $tableObj->getBodyNum();
        $this->rslt['startindex'] = $tableObj->getStartIndex();
        $this->rslt['limitselection'] = $this->determineLimitSelection($tableObj);
    }

    private function determineLimitSelection(TableHtml $tableObj): int
    {
        $limitSelection = $tableObj->getLimitSelection();

        if ($limitSelection !== null) {
            return $limitSelection;
        }

        return $this->conf['query']['table_limit_rows'] ?? self::LIMIT_ROWS;
    }

    private function addModuleConfigData()
    {
        if ($this->moduleName) {
            $this->conf['module_name'] = $this->moduleName;
            $this->conf['module_path'] = $this->modulePath;
        }
    }

    private function handleBodyCmd()
    {

        $this->addModuleConfigData();
        //Wenn Sortierung in Session hinterlegt ist, diese in Query-Konfiguration übernehmen
        if (!empty($_SESSION[$this->conf['unique_identifier']]['sortorder'])) {
            $this->conf['query']['table_condition'] = $_SESSION[$this->conf['unique_identifier']]['sortorder'];
        }

        //Filter können auch leer sein, wenn sie zurückgesetzt werden
        $filters = $this->getAlwaysFilters();
        if (isset($this->P['filters'])) {
            if (empty($this->P['filters'])) {
                $this->buildFilterCondition($this->P['filters']);
            } elseif (is_array($this->P['filters'])) {
                $filters = array_merge($filters, $this->P['filters']);
            }
        }
        if (count($filters) > 0) {
            $this->buildFilterCondition($filters);
        } elseif (!empty($_SESSION[$this->conf['unique_identifier']]['filter_where'])) {
            $this->conf['query']['filter_where'] = $_SESSION[$this->conf['unique_identifier']]['filter_where'];
        }

        $tableObj = new TableHtml($this->conf['table'], $this->conf);
        $html = $tableObj->getTableHtml('body');
        $this->rslt['succ'] = 'OK';
        $this->rslt['html'] = $html;
        $this->rslt['totalrows'] = $tableObj->getNum();
        $this->rslt['tablerows'] = $tableObj->getUnfilteredNum();
        $this->rslt['bodyrows'] = $tableObj->getBodyNum();
        $this->rslt['startindex'] = $tableObj->getStartIndex();
    }

    /**
     * Handle single field update
     */
    private function handleSingleUpdate(string $primaryKey): void
    {
        $this->addModuleConfigData();
        if (!$this->checkUpdateRights($this->P['fieldname'])) {
            $this->errorOutput(
                    'Update nicht erlaubt.',
                    __FILE__,
                    __LINE__,
                    $this->P['fieldname']
            );
        }

        $value = !empty($this->P['is_null']) ? null : $this->P['upd_val'];

        $this->applyPHPFilters($this->P['fieldname'], $value, 'update');
        $this->validation($this->P['fieldname'], $value, 'update');

        $updArr = [
            $this->P['fieldname'] => $this->handleUpdateFilters(
                    $this->P['fieldname'],
                    $value
            )
        ];

        $this->executeUpdate($primaryKey, $this->P['upd_id'], $updArr);
    }

    /**
     * Handle single field update
     */
    private function handleRowUpdate(string $primaryKey): void
    {
        $this->addModuleConfigData();
        $updArr = json_decode($this->P['field_arr'], true);

        if (!count($updArr)) {
            $err = 'Keine Daten für Update erhalten.';
            $this->errorOutput($err, __FILE__, __LINE__, $updArr);
        }
        foreach ($updArr as $k => $v) {

            if (!$this->checkUpdateRights($k)) {
                $err = 'Update nicht erlaubt.';
                $this->errorOutput($err, __FILE__, __LINE__, 'Wert: ' . $k);
            }
            $this->applyPHPFilters($k, $v, 'update'); //Call by reference
            $this->validation($k, $v, 'update');

            $updArr[$k] = $this->handleUpdateFilters($k, $v);
        }
        $this->modifyUpdArr($updArr);
        $this->executeUpdate($primaryKey, $this->P['upd_id'], $updArr);
        $this->rslt['rowmarker'] = $this->getRowClasses($updArr);
        $this->setSpecialChars($updArr);
        $this->rslt['update_arr'] = $updArr;
    }

    /**
     * Handle switch value toggle
     */
    private function handleSwitchValue(string $primaryKey): void
    {
        $this->addModuleConfigData();
        [$updVal, $updLabel] = $this->getSwitchVal(
                $this->P['fieldname'],
                $this->P['curr_val']
        );

        $updArr = [
            $this->P['fieldname'] => $this->handleUpdateFilters(
                    $this->P['fieldname'],
                    $updVal
            )
        ];

        $this->executeUpdate($primaryKey, $this->P['upd_id'], $updArr);

        $this->rslt['switch_label'] = $updLabel;
        $this->rslt['cssrowmodval'] = $updLabel;

        if (($this->conf['css_rowmod'][$this->P['fieldname']] ?? null) === 'TABLE_VALUE') {
            $this->rslt['cssrowmodval'] = $updVal;
        }
    }

    /*
     * Holt den nächsten Switch-Wert aus der Konfiguration
     */

    protected function getSwitchVal($fieldname, $currVal)
    {
        if (!isset($this->conf['table_def']['rights']['switch'][$fieldname])) {
            $err = 'Vorgang nicht erlaubt.';
            $this->errorOutput($err, __FILE__, __LINE__, $fieldname . ' => ' . \print_r($currVal, true));
        }
        $switchArr = $this->conf['table_def']['rights']['switch'][$fieldname];
        if (!isset($switchArr[$currVal])) {
            $err = 'Switch - fehlerhafter Index.';
            $this->errorOutput($err, __FILE__, __LINE__, $currVal);
        }

        reset($switchArr);
        //Achtung != ist richtig, nicht !==
        while (key($switchArr) != $currVal && key($switchArr) !== null) {
            next($switchArr);
        }
        next($switchArr);
        if (key($switchArr) === null) {
            reset($switchArr);
        }
        $key = key($switchArr);
        $val = current($switchArr);
        return array($key, $val);
    }

    private function handleInsertRow()
    {
        $inArr = array();
        parse_str($this->P['field_arr'], $inArr);

        if (!count($inArr)) {
            $err = 'Keine Daten für Insert erhalten.';
            $this->errorOutput($err, __FILE__, __LINE__);
        }
        foreach ($inArr as $k => $v) {
            if (!$this->checkInsertRights($k)) {
                $err = 'Einfügen nicht erlaubt.';
                $this->errorOutput($err, __FILE__, __LINE__, 'Key ' . $k . '  Value: ' . $v);
            }
            $this->applyPHPFilters($k, $v, 'insert'); //Call by reference
            //Validierung
            $this->validation($k, $v, 'insert');
            $inArr[$k] = $this->handleInsertFilters($k, $v);
        }
        //Call by reference Auto-Insert-Felder hinzufügen
        $this->addAutoInsert($inArr); //einzelne Werte
        $this->modifyInsArr($inArr); //bezogen auf das ganze Array

        $tableObj = new InsertRow($this->conf['ins_upd_table']);

        $succ = $tableObj->insert($inArr);
        if (!$succ) {
            $err = 'Einfügen nicht möglich.';
            $this->errorOutput($err, __FILE__, __LINE__, $inArr);
        }
        $this->rslt['succ'] = 'OK';

        $this->mailNotification('insert');

        !empty($this->conf['events']['after_insert']) && new Event($this->conf['events']['after_insert'], $this->conf['table'], $inArr);
    }

    private function handleDelete(string $primaryKey): void
    {
        $this->addModuleConfigData();
        if (!isset($this->P['delete_id'])) {
            $err = 'Technischer Fehler';
            $this->errorOutput($err, __FILE__, __LINE__, 'Delete-ID: ' . $this->P['delete_id']);
        }

        if (empty($this->conf['table_def']['rights']['delete'])) {
            $err = 'Löschen nicht erlaubt.';
            $this->errorOutput($err, __FILE__, __LINE__, 'Delete-ID: ' . $this->P['delete_id']);
        }
        //3rd param checks if logging is active. In this case the deleted row will be saved.
        $tableObj = new DeleteRow($this->conf['ins_upd_table'], $primaryKey, $this->mustLogDeletion());

        $succ = $tableObj->delete($this->P['delete_id']);
        if (!$succ) {
            $err = 'Löschen nicht möglich.';
            $this->errorOutput($err, __FILE__, __LINE__, 'Delete-ID: ' . $this->P['delete_id']);
        }
        $this->optionalChangeLog($tableObj);
    }

    /**
     * Execute update operation with common post-processing
     */
    private function executeUpdate(string $primaryKey, $id, array &$updArr): void
    {
        $tableObj = new UpdateRow(
                $this->conf['ins_upd_table'],
                $primaryKey
        );

        $this->modifyUpdArr($updArr);

        if (!$tableObj->update($id, $updArr)) {
            new ExcLogging($this->P, __FILE__, __LINE__);
            $this->errorOutput(
                    'Update nicht möglich.',
                    __FILE__,
                    __LINE__,
                    $updArr
            );
        }

        $this->optionalChangeLog($tableObj);
        $this->rslt['rowmarker'] = $this->getRowClasses($updArr);
        $this->setSpecialChars($updArr);
        $this->rslt['update_arr'] = $updArr;
        $this->mailNotification('update');
    }

    private function handleSetSort()
    {
        $this->addModuleConfigData();
        $sortArr = json_decode($this->P['sort'], true);
        $this->buildSortCondition($sortArr);
        $this->handleBodyCmd(); //Table-Body zurückgeben
    }

    /**
     * Handle unknown command gracefully
     */
    private function handleUnknownCommand(string $cmd): void
    {
        $this->errorOutput(
                'Unbekannter Befehl',
                __FILE__,
                __LINE__,
                "Command: $cmd"
        );
    }

    // Keep remaining protected methods from original class
    // (checkUpdateRights, validation, handleUpdateFilters, etc.)

    protected function handleSessionStart(): void
    {
        $isPublic = ($this->conf['access'] ?? null) === 'public';

        if ($isPublic) {
            session_start();
        } else {
            \PbClasses\Ajax\SessionStart::start();
        }
    }

    protected function modifyUpdArr(array &$updArr): void
    {
        if (empty($this->conf['modify_upd_arr']) || !is_array($this->conf['modify_upd_arr'])) {
            return;
        }

        foreach ($this->conf['modify_upd_arr'] as $className => $paramArr) {
            if (!empty($className)) {
                new $className($this->conf, $updArr, $paramArr);
            }
        }
    }

    protected function modifyInsArr(array &$insArr): void
    {
        if (empty($this->conf['modify_ins_arr']) || !is_array($this->conf['modify_ins_arr'])) {
            return;
        }

        foreach ($this->conf['modify_ins_arr'] as $className => $paramArr) {
            if (!empty($className)) {
                new $className($this->conf, $insArr, $paramArr);
            }
        }
    }

    protected function optionalChangeLog($tableObj): void
    {
        $tableName = $this->conf['ins_upd_table'];

        if (empty($this->conf['logging']['change_log'][$tableName])) {
            return;
        }

        $destTable = $this->conf['logging']['change_log'][$tableName];
        $logArr = $tableObj->getLogData();
        $uid = $_SESSION['uid'] ?? 0;

        UpdateLog::log($destTable, $uid, $logArr);
    }

    protected function mustLogDeletion(): bool
    {
        $tableName = $this->conf['ins_upd_table'];
        return !empty($this->conf['logging']['change_log'][$tableName]);
    }

    protected function makeSetup(): void
    {
        if (empty($this->conf['setup_classes'])) {
            return;
        }

        foreach ($this->conf['setup_classes'] as $projectClass) {
            if (class_exists($projectClass)) {
                new $projectClass($this->conf);
            } else {
                new ExcLogging(
                        $projectClass . ' nicht gefunden',
                        __FILE__,
                        __LINE__
                );
            }
        }
    }

    protected function setSpecialChars(array &$tdArr): void
    {
        foreach ($tdArr as $key => $val) {
            if (!is_string($val)) {
                continue;
            }

            // Skip if special output filters are configured
            if (!empty($this->conf['outputfilter']['html'][$key]) ||
                    !empty($this->conf['outputfilter']['md'][$key]) ||
                    !empty($this->conf['outputfilter']['ownclass'][$key]) ||
                    !empty($this->conf['add_fields'][$key])) {
                continue;
            }

            $tdArr[$key] = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }
    }

    private function handleGetPresetFromClasses(): void
    {
        $this->addModuleConfigData();
        if (empty($this->conf['preset_from_class'])) {
            return;
        }

        $presetArr = [];

        foreach ($this->conf['preset_from_class'] as $classname) {
            try {
                new $classname($presetArr, $this->conf);
            } catch (\Exception $exc) {
                $this->errorOutput(
                        'Fehler in Preset-Klasse',
                        __FILE__,
                        __LINE__,
                        $classname . ' ' . $exc->getMessage()
                );
            }
        }

        $this->rslt['dyn_preset'] = $presetArr;
    }

    protected function handleDoJob(): void
    {
        $this->addModuleConfigData();
        if (!isset($this->P['method_name'])) {
            $this->errorOutput(
                    'Konfigurationsfehler: Methodenname ist nicht definiert.',
                    __FILE__,
                    __LINE__,
                    $this->P
            );
        }

        $methodName = $this->P['method_name'];

        if (!isset($this->conf['dojob'][$methodName])) {
            $this->errorOutput(
                    'Die Funktion ist nicht definiert.',
                    __FILE__,
                    __LINE__,
                    $methodName
            );
        }

        if (!isset($this->conf['dojob'][$methodName]['classname'])) {
            $this->errorOutput(
                    'Konfigurationsfehler: Der Klassenname ist nicht definiert.',
                    __FILE__,
                    __LINE__,
                    $this->conf['dojob'][$methodName]
            );
        }

        try {
            $classname = $this->conf['dojob'][$methodName]['classname'];
            $obj = new $classname(
                    $this->P,
                    $this->conf['dojob'][$methodName],
                    $this->conf
            );
            $this->rslt['dojob'] = $obj->doJob();
        } catch (\Exception $exc) {
            $this->errorOutput(
                    'Technischer Fehler bei der Ausführung der Funktion.',
                    __FILE__,
                    __LINE__,
                    $exc->getMessage()
            );
        }
    }

    protected function setGetFilters(): void
    {
        if (empty($this->conf['filters'])) {
            return;
        }

        // Check for overwrite settings
        foreach ($this->conf['get_filters'] as $key => $valArr) {
            if (empty($this->G[$key])) {
                continue;
            }

            $sgConf = $this->conf['get_filters'][$key];

            if (($sgConf['type'] ?? null) === 'overwrite-presettings') {
                $this->resetPreFilters();
                break;
            }
        }

        // Apply filters
        foreach ($this->conf['get_filters'] as $key => $valArr) {
            if (!empty($this->G[$key])) {
                $this->updatePresetConfFromGet($valArr);
            }
        }
    }

    protected function updatePresetConfFromGet(array $sgGetArr): void
    {
        foreach ($sgGetArr['filters'] as $filterKey => $paramArr) {
            $this->conf['filters'][$filterKey]['active_on_start'] = $this->getActiveOnStartEntry($paramArr);
        }
    }

    protected function getActiveOnStartEntry(array $paramArr): array
    {
        $retArr = [];

        foreach ($paramArr as $paramKey => $getVar) {
            if (isset($this->G[$getVar])) {
                $retArr[$paramKey] = $this->G[$getVar];
            }
        }

        return $retArr;
    }

    protected function resetPreFilters(): void
    {
        if (empty($this->conf['filters'])) {
            return;
        }

        foreach ($this->conf['filters'] as $key => $sgFilterConf) {
            $this->conf['filters'][$key]['active_on_start'] = null;
        }
    }

    /**
     * 
     * @param mixed $filterArr  array or empty strimng from Post variable.
     * @return void
     */
    
    protected function buildFilterCondition(mixed $filterArr): void
    {
        $sessionKey = $this->conf['unique_identifier'];

        if (empty($filterArr)) {
            $_SESSION[$sessionKey]['filter_where'] = null;
            $_SESSION[$sessionKey]['filter_arr'] = null;
            return;
        }

        $_SESSION[$sessionKey]['filter_arr'] = $filterArr;

        $db = Conf::get('db');
        $tmpFilterArr = [];
        $filters = $this->conf['filters'];

        foreach ($filterArr as $filtername => $sgKVArr) {
            $filterConf = $filters[$filtername] ?? null;

            if ($filterConf === null) {
                //exits with error message
                $this->errorOutput(
                        'Technischer Fehler - Filter.',
                        __FILE__,
                        __LINE__,
                        $filtername . ' => ' . print_r($sgKVArr, true)
                );
            }

            if (isset($filterConf['filter_where'])) {
                $escaped = array_map([$db, 'escape'], $sgKVArr);
                $tpl = new \PbClasses\PbTpl($filterConf['filter_where'], false, 'query');
                $tmpFilterArr[] = '(' . $tpl->fillTpl('query', $escaped) . ')';
            } elseif (isset($filterConf['classname'])) {
                try {
                    $obj = new $filterConf['classname']($sgKVArr, $filterConf, $this->conf);
                    $tmpFilterArr[] = '(' . $obj->get() . ')';
                } catch (\Exception $exc) {
                    $this->errorOutput(
                            'Technischer Fehler - Filter.',
                            __FILE__,
                            __LINE__,
                            $exc->getMessage()
                    );
                }
            }
        }

        $filterWhere = implode(' AND ', $tmpFilterArr);

        $this->conf['query']['filter_where'] = $filterWhere;
        $_SESSION[$sessionKey]['filter_where'] = $filterWhere;
    }

    private function buildSortCondition($sortArr)
    {
        if (!count($sortArr)) {
            $this->conf['query']['table_condition'] = '';
            $_SESSION[$this->conf['unique_identifier']]['sortorder'] = '';
            $_SESSION[$this->conf['unique_identifier']]['sortorder_arr'] = $sortArr;
            return;
        }

        $sortString = 'ORDER BY ';
        $tmpOrderArr = [];
        foreach ($sortArr as $sgKVArr) {
            $key = key($sgKVArr);
            $tmpSortString = $key;
            if ($sgKVArr[$key]) {
                $tmpSortString .= ' DESC';
            }
            $tmpOrderArr[] = $tmpSortString;
        }

        $_SESSION[$this->conf['unique_identifier']]['sortorder'] = $sortString .= join(', ', $tmpOrderArr);
        //Daten auch strukturiert speichern:
        $_SESSION[$this->conf['unique_identifier']]['sortorder_arr'] = $sortArr;
    }

    protected function mailNotification($type)
    {
        //Nur eine Mail pro Sitzung verschicken!
        if (!empty($_SESSION['MailNotification'][$type])) {
            return;
        }


        if (!empty($this->conf['info_mail']['change'])) {
            new \PbClasses\Mail\UserNotification($this->conf['info_mail']['change']);
            $_SESSION['MailNotification'][$type] = true;
            return;
        }

        if (!empty($this->conf['info_mail'][$type])) {
            new \PbClasses\Mail\UserNotification($this->conf['info_mail'][$type]);
            $_SESSION['MailNotification'][$type] = true;
        }
    }

    private function getAlwaysFilters()
    {
        $backArr = [];
        if (empty($this->conf['filters'])) {
            return $backArr;
        }
        foreach ($this->conf['filters'] as $key => $valArr) {
            if (!empty($valArr['active_always']) && !empty($valArr['classname'])) {
                $backArr[$key] = $valArr['active_always'];
            }
        }

        $this->rslt['always_filters'] = $backArr;
        return $backArr;
    }

    private function getPreFilters()
    {
        $backArr = [];
        if (empty($this->conf['filters'])) {
            return $backArr;
        }
        foreach ($this->conf['filters'] as $key => $valArr) {
            if (!empty($valArr['active_on_start'])) {
                //Array kann WHERE_ID oder SCOPE enthalten
                $backArr[$key] = $this->modifyActivOnStartArr($valArr['active_on_start']);
            }
        }

        $this->rslt['pre_filters'] = $backArr;
        return $backArr;
    }

    protected function modifyActivOnStartArr($aosArr)
    {
        foreach ($aosArr as $k => $v) {
            $aosArr[$k] = $this->getPossiblePlaceholderVal($v);
        }
        return $aosArr;
    }

    private function getPossiblePlaceholderVal($val)
    {
        if ($val === '{WHERE_ID}') {
            if (!isset($_SESSION[$this->conf['unique_identifier']]['where_id'])) {
                $err = 'Konfigurationsfehler';
                $this->errorOutput($err, __FILE__, __LINE__, 'where_id fehlt');
            }
            return $_SESSION[$this->conf['unique_identifier']]['where_id'];
        }

        if ($val === '{SCOPE}') {
            if (!isset($_SESSION[$this->conf['unique_identifier']]['scope'])) {
                $err = 'Konfigurationsfehler';
                $this->errorOutput($err, __FILE__, __LINE__, 'scope fehlt');
            }
            return $_SESSION[$this->conf['unique_identifier']]['scope'];
        }

        return $val;
    }

    private function addAutoInsert(& $inArr)
    {
        if (empty($this->conf['auto_insert']) || !is_array($this->conf['auto_insert'])) {
            return;
        }
        foreach ($this->conf['auto_insert'] as $key => $val) {
            if ($val === '{WHERE_ID}') {
                if (!isset($_SESSION[$this->conf['unique_identifier']]['where_id'])) {
                    $err = 'Konfigurationsfehler';
                    $this->errorOutput($err, __FILE__, __LINE__, 'where_id fehlt');
                }
                $inArr[$key] = $_SESSION[$this->conf['unique_identifier']]['where_id'];
            } elseif ($val === '{SCOPE}') {
                if (!isset($_SESSION[$this->conf['unique_identifier']]['scope'])) {
                    $err = 'Konfigurationsfehler';
                    $this->errorOutput($err, __FILE__, __LINE__, 'scope fehlt');
                }
                $inArr[$key] = $_SESSION[$this->conf['unique_identifier']]['scope'];
            } else {
                $inArr[$key] = $val;
            }
        }
    }

    /**
     * 
     * @param string $table
     * @return boolean
     */
    protected function getTableStructure($table)
    {
        if (empty($this->conf['table_def']['rights']['change']) && empty($this->conf['table_def']['rights']['insert'])) {
            return false;
        }
        $obj = new \PbClasses\DB\TableStructure();
        $structure = $obj->get($table);

        //Alle Infos werden gebraucht:
        if (isset($this->conf['table_def']['rights']['insert'])) {
            //Bei der Eingabe kann es dennoch theoretisch möglich sein, dass nur besteimmte Felder erlaubt sind!
            if (true === $this->conf['table_def']['rights']['insert']) {
                $this->allowedInsertFields = array_keys($structure);
            } elseif (is_array($this->conf['table_def']['rights']['insert'])) {
                $this->allowedInsertFields = $this->conf['table_def']['rights']['insert'];
            }
        }
        if (isset($this->conf['table_def']['rights']['change'])) {
            //Bei der Eingabe kann es dennoch theoretisch möglich sein, dass nur besteimmte Felder erlaubt sind!
            if (true === $this->conf['table_def']['rights']['change']) {
                $this->allowedUpdateFields = array_keys($structure);
            } elseif (is_array($this->conf['table_def']['rights']['change'])) {
                $this->allowedUpdateFields = $this->conf['table_def']['rights']['change'];
            }
        }

        $neededFieldsArr = array_unique(array_merge($this->allowedUpdateFields, $this->allowedInsertFields));
        $structArr = [];

        foreach ($neededFieldsArr as $fieldname) {
            if (!isset($structure[$fieldname])) {
                $err = $fieldname . ': Konfigurationsfehler, bitte wenden Sie sich an den Administrator.';
                $this->errorOutput($err, __FILE__, __LINE__, 'fieldname: ' . $fieldname);
            }
            $structArr[$fieldname] = $structure[$fieldname];
        }
        return $structArr;
    }

    protected function addOwnForms()
    {
        if (empty($this->conf['table_js_ownforms'])) {
            $this->rslt['own_forms'] = null;
            return;
        }
        //$tplFile in Abhängigkeit von der Sprache
        $tplFile = \PbClasses\Tpl\Ajax\HelperLanguage::languageTpl($this->conf['table_js_ownforms']);

        if (!file_exists($tplFile)) {
            $err = 'Konfigurationsfehler, bitte wenden Sie sich an den Administrator.';
            $this->errorOutput($err, __FILE__, __LINE__, 'JS-Template');
        }

        $t = new \PbClasses\PbTpl($tplFile);
        $this->rslt['own_forms'] = $t->getTplArr();
    }

    protected function getRowClasses(& $tdArr)
    {
        // return ' testclass';
        if (empty($this->conf['table_def']['rowmarker_class'])) {
            return '';
        }
        try {
            $cl = $this->conf['table_def']['rowmarker_class'];
            $obj = new $cl();
            return $obj->getClasses($tdArr); //AddSpace            
        } catch (\Exception $exc) {

            new ExcLogging($exc->getMessage(), __FILE__, __LINE__);
            return '';
        }
    }

    protected function applyPHPFilters($k, & $v, $type)
    {
        //prüfen, ob gemeinsamer Filter vorhanden ist:
        if (isset($this->conf['ins_upd_phpfilters'][$k])) {
            $v = filter_var($v, $this->conf['ins_upd_phpfilters'][$k]);
            return;
        }
        //sonst nach spezifischem Filter suchen:
        if (isset($this->conf[$type . '_phpfilters'][$k])) {
            $v = filter_var($v, $this->conf[$type . '_phpfilters'][$k]);
        }
    }

    protected function checkUpdateRights($fieldnames)
    {
        //allow all:
        if ($this->conf['table_def']['rights']['change'] === true) {
            return true;
        }

        //otherwise it must be an array
        if (!is_array($this->conf['table_def']['rights']['change'])) {
            return false;
        }

        if (is_string($fieldnames)) {
            if (in_array($fieldnames, $this->conf['table_def']['rights']['change'])) {
                return true;
            }
            return false;
        }


        if (!is_array($fieldnames) || !count($fieldnames)) {
            $err = 'Technischer Fehler 5';
            $this->errorOutput($err, __FILE__, __LINE__, print_r($fieldnames, true));
        }

        foreach ($fieldnames as $sgFieldname) {
            if (!in_array($sgFieldname, $this->conf['table_def']['rights']['change'])) {
                return false;
            }
        }
        return true;
    }

    protected function checkInsertRights($fieldnames)
    {
        //Felder mit Eingabedaten, die nicht genutzt werden sollen, können entsprechend markiert werden,
        //müssen dann aber aktiv aus dem $inArr gelöscht werden. Am besten in der add-insert-Klasse, die das Feld benötigt.
        if (substr($fieldnames, 0, 16) === 'pbtblinsertflag_') {
            return true;
        }

        if (empty($this->conf['table_def']['rights']['insert'])) {
            return false; //nichts erlaubt.
        }

        //alles erlaubt: OK
        if ($this->conf['table_def']['rights']['insert'] === true) {
            return true;
        }

        //Es muss ein Array folgen, falls nicht true
        if (!is_array($this->conf['table_def']['rights']['insert'])) {
            return false;
        }

        if (is_string($fieldnames)) {
            if (in_array($fieldnames, $this->conf['table_def']['rights']['insert'])) {
                return true;
            }
            return false;
        }


        if (!is_array($fieldnames) || !count($fieldnames)) {
            $err = 'Technischer Fehler 5';
            $this->errorOutput($err, __FILE__, __LINE__, print_r($fieldnames, true));
        }

        foreach ($fieldnames as $sgFieldname) {
            if (!in_array($sgFieldname, $this->conf['table_def']['rights']['insert'])) {
                return false;
            }
        }

        return true;
    }

    protected function validation($k, $v, $type)
    {
        //prüfen, ob gemeinsamer Filter vorhanden ist:
        if (isset($this->conf['ins_upd_validation'][$k])) {
            $validationConf = $this->conf['ins_upd_validation'][$k];
        }
        //sonst nach spezifischem Filter suchen:
        elseif (isset($this->conf[$type . '_validation'][$k])) {
            $validationConf = $this->conf[$type . '_validation'][$k];
        }

        if (empty($validationConf)) {
            return true;
        }

        //PHP-Konstante für Filter
        if (is_int($validationConf)) {
            if (filter_var($v, $validationConf)) {
                return true;
            }
            $this->validationError($k);
        }

        if (is_array($validationConf)) {
            if (!is_array($validationConf[0])) {
                $this->sgValidation($k, $v, $validationConf);
                //Bei Fehler Ausstieg in Methode
                return true;
            }

            foreach ($validationConf as $sgValidationConf) {
                $this->sgValidation($k, $v, $sgValidationConf);
            }
            return true;
        }

        $this->validationError($k, 'Konfigurationsfehler');
    }

    protected function sgValidation($k, $v, $sgValidationConf)
    {
        $className = \array_shift($sgValidationConf);
        $param = \array_shift($sgValidationConf);
        $err = \count($sgValidationConf) ? $sgValidationConf[0] : null;
        if (substr($className, 0, 1) !== '\\') {
            $className = '\\PbClasses\\Validation\\' . $className;
        }

        if (!$className::validate($v, $param)) {
            $this->validationError($k, $err);
        }
    }

    protected function validationError($fieldName, $err = null)
    {
        if (null === $err) {
            $err = 'Ungültige Eingabe';
        }
        $this->rslt['validation_error'] = $err;
        $this->rslt['validation_fieldname'] = $fieldName;
        $this->errorOutput($err, __FILE__, __LINE__);
    }

    protected function handleInsertFilters($k, $v)
    {
        if (!empty($this->conf['table_def']['insert_filters_standard'][$k])) {
            $method = $this->conf['table_def']['insert_filters_standard'][$k];
            return $this->applyBaseFilter($method, $v);
        }
        return $v;
    }

    protected function handleUpdateFilters($k, $v)
    {
        if (!empty($this->conf['table_def']['update_filters_standard'][$k])) {
            $method = $this->conf['table_def']['update_filters_standard'][$k];
            return $this->applyBaseFilter($method, $v);
        }
        return $v;
    }

    protected function applyBaseFilter($method, $v)
    {
        if (method_exists("BaseFilters", $method)) {
            return BaseFilters::$method($v);
        }

        new ExcLogging('Method ' . $method . ' not defined!');
        return $v;
    }
}

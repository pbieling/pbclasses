<?php

declare(strict_types=1);

namespace PbClasses\DB;

use PDO;

/**

 * @author Peter Bieling
 * 
 */
class SqlPdo {

    /**
     * Default DBMS = mysql
     * @see __construct()
     * @const string
     */
    protected const DEFAULT_DBS = 'mysql';

    /**
     * PDO connection
     * 
     * @var PDO
     */
    protected $pdo;

    /**
     * Debug mode on or off
     * @see method setDebug()
     * 
     * @var bool
     */
    protected $debug = false;

    /**
     * Default path of the debug logfile
     * @see method setDebug()
     * 
     * @var string
     */
    protected $debugTestLog = '/tmp/pdo-debug.log';

    /**
     * @see method setDebug()
     * 
     * @var int
     */
    protected $maxDebugQueryOutput = 1000;

    /**
     * 
     * @param array<string,string|int>|string $host_
     * @param string $user
     * @param string $passw
     * @param string $db
     * @param int $port
     * @param string $dbs
     */
    public function __construct(mixed $host_, string $user = '', ?string $passw = '', ?string $db = '', ?int $port = 3306, ?string $dbs = self::DEFAULT_DBS) {
        if (is_array($host_)) {
            $credArr = & $host_;
            $host = (string) $credArr['host'];
            $user = (string) $credArr['user'];
            $passw = (string) $credArr['pwd'];
            $db = (string) $credArr['db'];
            if (!empty($credArr['port'])) {
                $port = (int) $credArr['port'];
            }
            $dbs = (!empty($credArr['dbs'])) ? $credArr['dbs'] : self::DEFAULT_DBS;
        } else {
            $host = $host_;
        }

        $dsn = "$dbs:host=$host;dbname=$db;charset=UTF8";
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false, // turn off emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];

        $this->pdo = new PDO($dsn, $user, $passw, $options);
    }

    /**
     * Set debug and modify the length of the bufferd query
     * 
     * @param bool $debug
     * @param int $queryOutputLength
     * @param string $logfile
     * @return bool
     */
    public function setDebug(bool $debug = true, int $queryOutputLength = 0, $logfile = null) {
        $this->debug = $debug;
        if ($queryOutputLength) {
            $this->maxDebugQueryOutput = $queryOutputLength;
        }

        if ($logfile) {
            $this->debugTestLog = $logfile;
        }
        return true;
    }

    /**
     * 
     * @param string $table
     * @param array<string, string>|string $what_
     * @param mixed $where_
     * @param list<array<string, scalar|null>> | array<string, scalar|null>|string|null $mixed
     * @param string $condition
     * @param int $assoc
     * @return mixed
     */
    public function select(string $table, mixed $what_ = "", mixed $where_ = "", mixed $mixed = null, string $condition = "", int $assoc = 0) {
        $what = $this->checkWhatParam($what_, '2cnd parameter must be of type array or string.', __FILE__, __LINE__, true);
        $where = $this->checkArrayOrStringParam($where_, '3rd parameter must be of type array or string.', __FILE__, __LINE__, true);
        list($bindArr, $andOr) = $this->handleMixedParam($mixed);

        if (is_array($what)) {
            if (!array_is_list($what)) {
                throw new \TypeError('2nd paramater must not be a list with arrays.');
            }
            $whatStr = join(', ', $what);
        } else {
            $whatStr = ($what === '') ? '*' : $what;
        }

        //$where:
        if (is_string($where)) {
            $whereStr = ($where === '') ? '' : "WHERE $where";
        } else {
            $whereStr = "WHERE " . $this->getWhereString($bindArr, $where, $andOr);
        }

        $query = "SELECT $whatStr FROM $table $whereStr $condition";

        if ($this->debug) {
            $this->debugLog($query, __FILE__, __LINE__);
            $this->debugLog($bindArr, __FILE__, __LINE__);
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindArr);

        //returns $stmt instead of value array. Useful for large result sets.
        if ($assoc === 2) {
            return $stmt;
        }


        $fetchType = ($assoc) ? PDO::FETCH_ASSOC : PDO::FETCH_NUM;
        $res = $stmt->fetchall($fetchType);

        $stmt->closeCursor();
        return $res;
    }

    /**
     * 
     * @param string $table
     * @param mixed $what
     * @param mixed $where
     * @param mixed $mixed
     * @param string $condition
     * @return list<array<string,scalar|null>> 
     */
    public function selectAssoc(string $table, mixed $what = "", mixed $where = "", mixed $mixed = null, string $condition = ""): array {
        return $this->select($table, $what, $where, $mixed, $condition, 1);
    }

    /**
     * 
     * @param string $table
     * @param mixed $what
     * @param mixed $where
     * @param scalar|null $returnOnEmpty
     * @return scalar|null
     */
    public function selectSingleVal(string $table, mixed $what = "", mixed $where = "", mixed $returnOnEmpty = null): mixed {
        $res = $this->select($table, $what, $where, '', 'LIMIT 1');
        if (!$res) {
            return $returnOnEmpty;
        }
        return $res[0][0];
    }

    /**
     * To use the stmt-object dircectly
     * 
     * @param string $table
     * @param mixed $what
     * @param mixed $where
     * @param mixed $mixed
     * @param string $condition
     * @param int $assoc
     * @return object
     */
    public function selectResource(string $table, mixed $what = "", mixed $where = "", mixed $mixed = null, string $condition = "", int $assoc = 2): object {
        return $this->select($table, $what, $where, $mixed, $condition, $assoc);
    }

    /**
     * 
     * @param string $table
     * @param mixed $keys
     * @param mixed $entries
     * @return int
     */
    public function replace(string $table, mixed $keys, mixed $entries = ""): int {
        return $this->insert($table, $keys, $entries, false, true);
    }

    /**
     * Insert data into a database table
     * 
     * @param string $table Table name
     * @param mixed $keys_ Column names (array/string) or data if $entries_ is empty
     * @param mixed $entries_ Values to insert (array/string/empty)
     * @param mixed $ignoreOrUpdate IGNORE flag (bool) or ON DUPLICATE KEY UPDATE clause (array/string)
     * @param bool $replace Use REPLACE instead of INSERT
     * @return int Number of affected rows
     * @throws \Exception
     * @throws \TypeError
     */
    public function insert(string $table, mixed $keys_, mixed $entries_ = '', mixed $ignoreOrUpdate = false, bool $replace = false): int {
        // Validate and normalize input parameters
        $keys = $this->checkArrayOrStringParam($keys_, '2nd parameter must be of type array or string.', __FILE__, __LINE__, false);
        $entries = $this->checkArrayOrStringParam($entries_, '3rd parameter must be of type array or string.', __FILE__, __LINE__, true);

        if (empty($keys)) {
            throw new \Exception('2nd parameter must not be empty.');
        }

        // Parse duplicate key handling
        [$ignore, $duplicateUpdate] = $this->parseDuplicateKeyHandling($ignoreOrUpdate);

        // Build base query
        $query = $this->buildInsertQuery($table, $replace, $ignore);

        // Handle string-based queries (raw SQL)
        if (is_string($keys) && $keys !== '') {
            return $this->handleRawSqlInsert($query, $keys, $entries, $duplicateUpdate);
        }

        // Normalize parameters for array-based inserts
        [$keysArr, $entryArr] = $this->normalizeInsertParameters($keys, $entries);

        // Build and execute parameterized query
        return $this->executeParameterizedInsert($query, $keysArr, $entryArr, $duplicateUpdate);
    }

    /**
     * Parse the ignoreOrUpdate parameter into separate ignore flag and duplicate update clause
     */
    private function parseDuplicateKeyHandling(mixed $ignoreOrUpdate): array {
        if (is_array($ignoreOrUpdate) || is_string($ignoreOrUpdate)) {
            return [false, $ignoreOrUpdate];
        }
        return [(bool) $ignoreOrUpdate, false];
    }

    /**
     * Build the base INSERT/REPLACE query with IGNORE clause if needed
     */
    private function buildInsertQuery(string $table, bool $replace, bool $ignore): string {
        $command = $replace ? 'REPLACE' : 'INSERT';
        $ignoreClause = $ignore ? ' IGNORE ' : '';
        return "$command $ignoreClause INTO $table ";
    }

    /**
     * Handle raw SQL insert queries (when keys and entries are strings)
     */
    private function handleRawSqlInsert(string $query, string $keys, mixed $entries, mixed $duplicateUpdate): int {
        $query .= $keys;

        $duplArr = [];
        $duplUpdStr = $duplicateUpdate ? $this->getDuplicateStr($duplicateUpdate, $duplArr) : '';

        // Both keys and entries are strings - execute directly without binding
        if (is_string($entries)) {
            $query .= ' ' . $entries . $duplUpdStr;
            $stm = $this->pdo->query($query);
            return $stm ? $stm->rowCount() : 0;
        }

        // Keys is string, entries is array - bind parameters
        if (is_array($entries)) {
            $query .= $duplUpdStr;
            if (count($duplArr)) {
                $entries = array_merge($entries, $duplArr);
            }
            return $this->excInsertArr($entries, $query);
        }

        throw new \TypeError('Invalid combination of parameters.');
    }

    /**
     * Normalize keys and entries parameters into consistent array format
     */
    private function normalizeInsertParameters(mixed $keys, mixed $entries): array {
        // If entries is empty, treat keys as the data array
        if ($entries === '') {
            return [null, $keys];
        }

        // Both are arrays
        if (is_array($entries)) {
            $keysArr = ($keys === '') ? null : $keys;

            if ($keysArr !== null && !is_array($keysArr)) {
                throw new \TypeError('3rd param must be array or empty string.');
            }

            return [$keysArr, $entries];
        }

        throw new \TypeError('Invalid parameter combination.');
    }

    /**
     * Execute parameterized insert with proper binding
     */
    private function executeParameterizedInsert(string $query, ?array $keysArr, array $entryArr, mixed $duplicateUpdate): int {
        $duplArr = [];
        $duplUpdStr = $duplicateUpdate ? $this->getDuplicateStr($duplicateUpdate, $duplArr) : '';

        // Keys are explicitly provided
        if (is_array($keysArr)) {
            return $this->executeInsertWithExplicitKeys($query, $keysArr, $entryArr, $duplUpdStr, $duplArr);
        }

        // Keys are derived from data
        return $this->executeInsertWithImplicitKeys($query, $entryArr, $duplUpdStr, $duplArr);
    }

    /**
     * Execute insert when column names are explicitly provided
     */
    private function executeInsertWithExplicitKeys(string $query, array $keysArr, array $entryArr, string $duplUpdStr, array $duplArr): int {
        if (!array_is_list($keysArr)) {
            throw new \TypeError('Keys array must be a list of column names.');
        }

        $fieldsStr = $this->getFieldsStr($keysArr);
        [$valueStr, $bindArr, $isList] = $this->prepareValuesAndBindings($keysArr, $entryArr);

        return $this->finishInsert($query, $bindArr, $fieldsStr, $valueStr, $isList, $duplUpdStr, $duplArr);
    }

    /**
     * Execute insert when column names are derived from the data
     */
    private function executeInsertWithImplicitKeys(string $query, array $entryArr, string $duplUpdStr, array $duplArr): int {
        // Single associative array (one row)
        if (!array_is_list($entryArr)) {
            $fieldsStr = $this->getFieldsStr(array_keys($entryArr));
            $valueStr = $this->getValueStr(array_keys($entryArr));
            return $this->finishInsert($query, $entryArr, $fieldsStr, $valueStr, false, $duplUpdStr, $duplArr);
        }

        // Multiple rows (list of associative arrays)
        if (!empty($entryArr) && !array_is_list($entryArr[0])) {
            $fieldsStr = $this->getFieldsStr(array_keys($entryArr[0]));
            $valueStr = $this->getValueStr(array_keys($entryArr[0]));
            return $this->finishInsert($query, $entryArr, $fieldsStr, $valueStr, true, $duplUpdStr, $duplArr);
        }

        $this->debugLog('TYPE-Problem:', __FILE__, __LINE__);
        $this->debugLog($entryArr, __FILE__, __LINE__);
        throw new \TypeError('Please check the types of the parameters.');
    }

    /**
     * Prepare value placeholders and bind array based on data structure
     */
    private function prepareValuesAndBindings(array $keysArr, array $entryArr): array {
        // Associative array with explicit bind parameters
        if (!array_is_list($entryArr)) {
            $valueStr = $this->getValueStr(array_keys($entryArr));
            return [$valueStr, $entryArr, false];
        }

        // Single row as list
        if (!is_array($entryArr[0])) {
            $valueStr = $this->getValueStr($keysArr);
            $bindArr = array_combine($keysArr, $entryArr);
            return [$valueStr, $bindArr, false];
        }

        // Multiple rows
        if (!array_is_list($entryArr[0])) {
            // Rows are associative arrays
            $valueStr = $this->getValueStr(array_keys($entryArr[0]));
            return [$valueStr, $entryArr, true];
        }

        // Rows are lists - convert to associative arrays
        $valueStr = $this->getValueStr($keysArr);
        $bindArr = array_map(
                fn($row) => array_combine($keysArr, $row),
                $entryArr
        );
        return [$valueStr, $bindArr, true];
    }
    
    //==== End of insert()

    /**
     * Removes backticks from named parameter.
     * 
     * @param string $param
     * @return string
     */
    protected function cleanNamedParam($param) {
        return str_replace('`', '', $param);
    }

    /**
     * 
     * @param list<string> $keysArr
     * @return string
     */
    protected function getValueStr(array $keysArr): string {
        $valArr = [];
        foreach ($keysArr as $k) {
            $valArr[] = ":" . $this->cleanNamedParam($k);
        }

        return ' VALUES (' . join(', ', $valArr) . ')';
    }

    /**
     * 
     * @param list<string> $keysArr
     * @return string
     */
    protected function getFieldsStr(array $keysArr): string {
        return ' (' . join(', ', $keysArr) . ')';
    }

    /**
     * 
     * @param mixed $duplUpd
     * @param  mixed $duplUpdArr
     * @return string
     */
    protected function getDuplicateStr(mixed $duplUpd, & $duplUpdArr): string {
        $queryPart = '';
        $queryPart .= ' ON DUPLICATE KEY UPDATE ';
        if (\is_array($duplUpd)) {
            $setArr = [];
            foreach ($duplUpd as $k => $v) {
                $setArr[] = "$k = :duplupd_" . $this->cleanNamedParam($k);
                $duplUpdArr["duplupd_" . $this->cleanNamedParam($k)] = $v;
            }
            $queryPart .= join(', ', $setArr);
        } else {
            $queryPart .= $duplUpd;
        }

        return $queryPart;
    }

    /**
     * 
     * @param list<scalar|null> | array<string, scalar|null> | list<array<string, scalar|null>> $bindArr
     * @param string $query
     * @param bool $isList
     * @return int
     * @throws \Exception
     */
    protected function excInsertArr(array $bindArr, string $query, bool $isList = false): int {
        $this->debugLog($query, __FILE__, __LINE__);
        $this->debugLog($bindArr, __FILE__, __LINE__);
        $stm = $this->pdo->prepare($query);

        if (!$isList) {
            $stm->execute($bindArr);
            return $stm->rowCount();
        }

        $counter = 0;
        try {
            $this->pdo->beginTransaction();
            foreach ($bindArr as $row) {
                $stm->execute($row);
                $counter += $stm->rowCount();
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
        return $counter;
    }

    /**
     * 
     * @return void
     */
    public function beginTransaction(): void {
        $this->pdo->beginTransaction();
    }

    /**
     * 
     * @return void
     */
    public function commit(): void {
        $this->pdo->commit();
    }

    /**
     * 
     * @return void
     */
    public function rollback(): void {
        $this->pdo->rollback();
    }

    /**
     * Basic update function.
     * 
     * @param string $table
     * @param mixed $keys_
     * @param mixed $entries_
     * @param mixed $where_
     * @param mixed $mixed
     * @param string $condition
     * @param bool $ignore
     * @return int  Number of affected rows
     * @throws \TypeError
     */
    public function update(string $table, mixed $keys_, mixed $entries_ = "", mixed $where_ = "", $mixed = null, string $condition = '', bool $ignore = false) {
        $keys = $this->checkArrayOrStringParam($keys_, '2cnd parameter must be of type array or string.', __FILE__, __LINE__, false);
        $entries = $this->checkArrayOrStringParam($entries_, '2cnd parameter must be of type array or string.', __FILE__, __LINE__, true);
        $where = $this->checkArrayOrStringParam($where_, '2cnd parameter must be of type array or string.', __FILE__, __LINE__, true);

        $bindArr = [];
        list($whereBindArr, $andOr) = $this->handleMixedParam($mixed);

        $keyVal = array();
        $setStr = "";
        $ignoreStr = ($ignore) ? 'IGNORE ' : '';

        if (is_array($keys)) {
            if (is_array($entries)) {
                $keyVal = array_combine($keys, $entries);
            } else {
                $keyVal = $keys;
            }
        } else {
            if ($entries === "") {
                //Der Setstring kann auch als String direkt ï¿½bergeben werden
                $keyVal = $keys;
                $setStr = $keys;
            }
        }

        //Wenn Set-String bereits vergeben war, ist der String nicht mehr leer.
        //Wenn er leer ist, liegt ein Array vor.
        if ($setStr === "") {
            $setArr = [];
            foreach ($keyVal as $k => $v) {
                $setArr[] = "$k = :" . $this->cleanNamedParam($k);
                $bindArr[$this->cleanNamedParam($k)] = $v;
            }
            $setStr = join(', ', $setArr);
        }
        //where-Teil
        if (is_string($where)) {
            $whereStr = $where;
            //Falls noch Daten im Where-String gesetzt sind, diese mit einbauen

            if (count($whereBindArr)) {
                // $prefixedWhereBindArr = $this->prefixWhereBindArr($whereBindArr);
                $bindArr = array_merge($bindArr, $whereBindArr);
            }
        } else {
            $whereStr = $this->getWhereString($bindArr, $where, $andOr);
        }

        $wherePart = ($whereStr) ? "WHERE $whereStr" : '';

        $query = "UPDATE $ignoreStr $table SET $setStr $wherePart " . $condition;
        $this->debugLog('UPDATE:  ', __FILE__, __LINE__);
        //Logging in the function
        return $this->excInsertArr($bindArr, $query);
    }

    /**
     * Shorthand for update with ignore command.
     * 
     * @param string $table
     * @param mixed $keys
     * @param mixed $entries
     * @param mixed $where
     * @param mixed $mixed
     * @param string $condition
     * @return int  Number of affected rows
     */
    public function updateIgnore(string $table, mixed $keys, mixed $entries = "", mixed $where = "", mixed $mixed = null, string $condition = '') {
        return $this->update($table, $keys, $entries, $where, $mixed, $condition, true);
    }

    /**
     * @param mixed $mixed
     * @return array{array<string, scalar|null> | array<array<string, scalar|null>> | array<array<scalar|null>>, string}	
     * 
     */
    protected function handleMixedParam(mixed $mixed): array {
        if (is_array($mixed)) {
            $dataArr = $mixed;
            $andOr = 'AND';
        } elseif (is_string($mixed)) {
            $dataArr = [];
            $andOr = (trim(strtoupper($mixed)) === 'OR') ? 'OR' : 'AND';
        } else {
            $dataArr = [];
            $andOr = 'AND';
        }

        return [$dataArr, $andOr];
    }

    /**
     * Basic delete function.
     * 
     * @param string $table
     * @param mixed $where_
     * @param mixed|null $mixed
     * @param string $condition
     * @return int
     * @throws \Exception
     */
    public function delete(string $table, mixed $where_, mixed $mixed = null, string $condition = ''): int {
        $where = $this->checkArrayOrStringParam($where_, '2nd parameter must be auf type string or array.', __FILE__, __LINE__, false);
        list($bindArr, $andOr) = $this->handleMixedParam($mixed);

        if (empty($where)) {
            throw new \Exception('2nd parameter must not be empty');
        }

        if (is_string($where)) {
            if ($where === 'TABLE') {
                $query = "DROP TABLE IF EXISTS " . $table;
            } elseif ($where === 'ALL') {
                $query = "TRUNCATE TABLE " . $table;
            } else {
                $query = "DELETE FROM " . $table . " WHERE " . $where;
            }
        } else {
            $whereStr = $this->getWhereString($bindArr, $where, $andOr);
            $query = "DELETE FROM " . $table . " WHERE " . $whereStr . ' ' . $condition;
        }
        return $this->excInsertArr($bindArr, $query);
    }

    /**
     * 
     * @param array<string,int|float|string|null> $bindArr
     * @param array<string,int|float|string|null> $whereArr
     * @param string $andOr
     * @return string
     */
    protected function getWhereString(array & $bindArr, array $whereArr, string $andOr): string {
        $tmpArr = array();
        foreach ($whereArr as $k => $v) {
            if ($v === null) {
                $tmpArr[] = $k . " IS NULL";
            } else {
                $tmpArr[] = $k . " = :where_" . $this->cleanNamedParam($k);
                $bindArr[':where_' . $this->cleanNamedParam($k)] = $v;
            }
        }
        $whereStr = join(" $andOr ", $tmpArr);
        return $whereStr;
    }

    /**
     * Shorthand using delete().
     * @param string $table
     * @return int  Number of affected rows
     */
    public function dropTable(string $table): int {
        return $this->delete($table, 'TABLE');
    }

    /**
     *  Shorthand using delete().
     * 
     * @param string $table
     * @return int
     */
    public function truncateTable(string $table): int {
        return $this->delete($table, 'ALL');
    }

    /**
     * Returns last insert ID as string
     * 
     * @return string|false
     */
    public function insertId(): mixed {
        return $this->pdo->lastInsertId();
    }

    /**
     * Instead of mysql_real_escape_string
     * 
     * @param string $value
     * @return string
     */
    public function escape(string $value): string {
        $quoted = $this->pdo->quote($value);
        $this->debugLog('quoted  - : ' . $quoted, __FILE__, __LINE__);
        return substr($quoted, 1, -1); //remove first and last quote.
    }

    /**
     * 
     * @param string $value
     * @return string
     */
    public function quote(string $value): string {
        return $this->pdo->quote($value);
    }

    /**
     * If you want do work directly with PDO get the object.
     * 
     * @return PDO
     */
    public function getDbh(): object {
        return $this->pdo;
    }

    /**
     * 
     * @param string $query
     * @param list<scalar> | list<array<mixed>> $bindArr
     * @return object
     */
    public function query(string $query, array $bindArr = []): object {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($bindArr);
        return $stmt;
    }

    /**
     * 
     * @param string $query
     * @return scalar|null
     */
    public function getSqlQueryVal(string $query): mixed {
        $result = $this->pdo->query($query);
        if (!$result) {
            return false;
        }
        $row = $result->fetch(PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * 
     * @return string
     */
    public function getDbName(): string {
        $result = $this->pdo->query('SELECT DATABASE()');
        if (!$result) {
            throw new \Exception('Problem to get own database name');
        }
        $row = $result->fetch(PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * 
     * @param string $table
     * @param string|null $database
     * @return array<array<string, scalar|null>>
     */
    public function showColumns(string $table, ?string $database = null): array {

        $query = 'SHOW COLUMNS FROM ' . $this->escape($table);
        if ($database) {
            $query .= ' FROM ' . $this->escape($database);
        }

        $result = $this->pdo->query($query);
        if (!$result) {
            return [[]];
        }
        $rows = $result->fetchall(PDO::FETCH_ASSOC);
        return (!$rows) ? [[]] : $rows;
    }

    /**
     * Extracts the single queries and sends single requests.
     * 
     * @param string $sqlMultiQueryOrFile
     * @param bool $isFile
     * @throws \Exception
     */
    public function multiQuery(string $sqlMultiQueryOrFile, bool $isFile = false): void {
        if ($isFile) {
            if (!file_exists($sqlMultiQueryOrFile)) {
                throw new \Exception('File not found: ' . $sqlMultiQueryOrFile);
            }
            $sqlMultiQuery = file_get_contents($sqlMultiQueryOrFile);
            if (false === $sqlMultiQuery) {
                throw new \Exception('Error on opening  ' . $sqlMultiQueryOrFile);
            }
        } else {
            $sqlMultiQuery = $sqlMultiQueryOrFile;
        }

        $sqlArr = explode(';', $this->filterMultiSql($sqlMultiQuery));
        foreach ($sqlArr as $sqlPart) {
            $sql = trim($sqlPart);
            if ($sql === '') {
                continue;
            }
            $this->query($sql);
        }
    }

    /**
     * 
     * @param string|null $dbName
     * @return list<string>
     * @throws \Exception
     */
    public function getTableList(?string $dbName = null) {
        if ($dbName && !$this->hasDatabase($dbName)) {
            throw new \Exception('Database does not exist: ' . $dbName);
        }
        $query = "SHOW TABLES";
        if ($dbName) {
            $query .= ' FROM ' . $this->escape($dbName);
        }
        $bufferArr = [];
        $stmt = $this->pdo->query($query);
        if (!$stmt) {
            return $bufferArr;
        }


        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $bufferArr[] = $row[0];
        }
        return $bufferArr;
    }

    /**
     * 
     * @param string $dbName
     * @return bool
     * 
     * SHOW DATABASES FROM '$dbName' 
     * does not work.
     */
    public function hasDatabase(string $dbName) {
        $query = "SHOW DATABASES";
        $result = $this->pdo->query($query);
        if (!$result) {
            return false;
        }
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            if ($row[0] === $dbName) {
                $result->closeCursor();
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * @return array<string>
     */
    public function getDatabaseList(): array {
        $bufferArr = [];
        $result = $this->pdo->query("SHOW DATABASES");
        if (!$result) {
            return $bufferArr;
        }
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $bufferArr[] = $row[0];
        }
        $result->closeCursor();
        return $bufferArr;
    }

    /**
     * An SQL dump is freed from empty lines and comments for 
     * subsequent splitting into single queries.
     * 
     * @param string $multisql
     * @return string
     */
    protected function filterMultiSql(string $multisql): string {
        $lineArr = explode("\n", $multisql);
        $cleanedSql = '';
        foreach ($lineArr as $line_) {
            $line = trim($line_);
            if ($line === '' || substr($line, 0, 2) === '--') {
                continue;
            }
            $cleanedSql .= $line . "\n";
        }
        return trim($cleanedSql);
    }

    /**
     * 
     * @param string $query
     * @param list<array<string|int,scalar|null>> | array<string,scalar|null> $bindArr
     * @param string $fieldsStr
     * @param string $valueStr
     * @param bool $isList
     * @param string $onDupl
     * @param array<string,scalar>|null $duplArr
     * @return int
     */
    protected function finishInsert(string $query, array $bindArr, string $fieldsStr, string $valueStr, bool $isList, string $onDupl, ?array $duplArr): int {
        $cleanedBindArr = $this->cleanBindArr($bindArr);
        if (is_array($duplArr)) {
            $cleanedBindArr = array_merge($cleanedBindArr, $duplArr);
        }

        $query .= $fieldsStr . $valueStr . $onDupl; //kann leer sein.
        $this->debugLog('Finish: ' . $query, __FILE__, __LINE__);
        $this->debugLog($cleanedBindArr, __FILE__, __LINE__);

        return $this->excInsertArr($cleanedBindArr, $query, $isList);
    }

    /**
     * 
     * @param mixed $bindArr
     * @return mixed
     */
    protected function cleanBindArr(mixed $bindArr): mixed {
        $cleanedBindArr = [];
        if (array_is_list($bindArr)) {
            if (array_is_list($bindArr[0])) {
                return $bindArr;
            }
            for ($i = 0; $i < count($bindArr); $i++) {
                $cleanedBindArr[$i] = [];
                foreach ($bindArr[$i] as $k => $v) {
                    $cleanedBindArr[$i][$this->cleanNamedParam($k)] = $v;
                }
            }
            return $cleanedBindArr;
        }
        foreach ($bindArr as $k => $v) {
            $cleanedBindArr[$this->cleanNamedParam($k)] = $v;
        }
        return $cleanedBindArr;
    }

    /**
     * Logs a message or different kinds of arrays
     * 
     * @param string|list<array<string|int,scalar|null>> | array<string,scalar|null> $msg
     * @param string $file
     * @param int $line
     * @return void
     */
    protected function debugLog(mixed $msg, string $file, int $line): void {
        if (!$this->debug) {
            return;
        }

        if (is_string($msg) && $this->maxDebugQueryOutput > 0) {
            $msg = substr($msg, 0, $this->maxDebugQueryOutput);
        }
        //else if 0 the full length will be logged

        if (class_exists('\\PbClasses\\Debug\\Logging')) {
            new \PbClasses\Debug\Logging($msg, $file, $line, $this->debugTestLog);
        } else {
            error_log(print_r($msg, true) . 'File: ' . $file . ' Line: ' . $line, 0, $this->debugTestLog);
        }
    }

    /**
     * 
     * @param mixed $checkParam
     * @param string $msg
     * @param string $file
     * @param int $line
     * @param bool $isAllowedNull
     * @return string | array<string> | array<string, scalar|null> | list<array<string, scalar|null>> | list<list<scalar>> | list<scalar>
     * @throws \TypeError
     */
    protected function checkArrayOrStringParam(mixed $checkParam, string $msg, string $file, int $line, bool $isAllowedNull = false): mixed {
        if (is_array($checkParam)) {
            return $checkParam;
        }
        if (is_string($checkParam)) {
            return trim($checkParam);
        }
        if ($isAllowedNull && is_null($checkParam)) {
            return '';
        }
        throw new \TypeError($msg . ' File: ' . $file . ' Line: ' . $line);
    }

    /**
     * 
     * @param mixed $checkParam
     * @param string $msg
     * @param string $file
     * @param int $line
     * @param bool $isAllowedNull
     * @return string |  array<string>
     * @throws \TypeError
     */
    protected function checkWhatParam(mixed $checkParam, string $msg, string $file, int $line, bool $isAllowedNull = false): mixed {
        if (is_array($checkParam)) {
            return $checkParam;
        }
        if (is_string($checkParam)) {
            return trim($checkParam);
        }
        if ($isAllowedNull && is_null($checkParam)) {
            return '';
        }
        throw new \TypeError($msg . ' File: ' . $file . ' Line: ' . $line);
    }
}

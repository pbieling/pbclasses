<?php

declare(strict_types=1);

namespace PbClasses\Tasks;

use PbClasses\Conf;
use PbClasses\Exception\Logging;
use RuntimeException;

/**
 * Manages and dispatches background PHP task processes.
 *
 * This class reads scheduled tasks from a database table and launches
 * them as background processes using the PHP CLI. It supports both
 * Windows and Linux/macOS environments.
 *
 * Expected database table columns:
 *  - ID         (int)     Primary key
 *  - Status     (string)  'ON' | 'Running' | 'OFF'
 *  - Nextrun    (datetime) Next scheduled execution time
 *  - Minutes    (int)     Interval in minutes (1440 = daily, preserves time-of-day)
 *  - TaskClass  (string)  Identifier of the task class to execute
 *  - LastStart  (datetime) Timestamp of the last launch
 *  - Heartbeat  (datetime) Alive-signal updated by the running task
 *  - Fingerprint (string) Unique token per run, prevents duplicate launches
 *
 * @author Peter Bieling
 */
class Tasks
{
    /**
     * Path to the task-starter bootstrap script, relative to the application root.
     */
    private const TASK_STARTER = 'system/taskstarter.php';

    /**
     * Default log file name written to system/log/.
     */
    private const DEFAULT_LOG_FILE = 'task-runner.log';

    /**
     * Database connection instance.
     */
    protected mixed $db;

    /**
     * Name of the database table that holds the task definitions.
     */
    protected string $tasksTable;

    /**
     * Log file name (relative to system/log/).
     */
    protected string $logFile;

    /**
     * Absolute or resolvable path to the PHP CLI binary.
     */
    protected string $phpCliPath;

    /**
     * @param string      $tasksTable  Name of the tasks table in the database.
     * @param mixed|null  $db          Database connection. Falls back to Conf::get('db')
     *                                 when null.
     * @param string|null $logFile     Optional custom log file name.
     */
    public function __construct(
        string $tasksTable,
        mixed $db = null,
        ?string $logFile = null
    ) {
        $this->db         = $db ?? Conf::get('db');
        $this->tasksTable = $tasksTable;
        $this->logFile    = $logFile ?? self::DEFAULT_LOG_FILE;
        $this->phpCliPath = Conf::get('phpcli_path') ?? 'php';
    }

    /**
     * Queries all due tasks and launches each one as a background process.
     *
     * A task is considered due when its Status is 'ON' and its Nextrun
     * timestamp is in the past or now. For every qualifying task the method:
     *  1. Generates a unique fingerprint.
     *  2. Atomically updates the row to Status = 'Running' (preventing
     *     duplicate launches from concurrent scheduler calls).
     *  3. Advances Nextrun by the configured minute interval, or by exactly
     *     one day (preserving the original time-of-day) when Minutes = 1440.
     *  4. Spawns a detached background process via the OS-appropriate command.
     *
     * @return bool Always true when execution completes without a fatal error.
     *
     * @throws RuntimeException When the task-starter bootstrap script is missing.
     */
    public function run(): bool
    {
        $taskStarterPath = Conf::get('APATH') . self::TASK_STARTER;

        if (!file_exists($taskStarterPath)) {
            throw new RuntimeException(
                sprintf('Task starter script not found: %s', $taskStarterPath)
            );
        }

        $dueTasks = $this->db->selectAssoc(
            $this->tasksTable,
            '',                             // all columns
            "Status = 'ON' AND Nextrun <= NOW()"
        );

        if (empty($dueTasks)) {
            return true;
        }

        foreach ($dueTasks as $task) {
            if (!$this->claimTask($task)) {
                continue;
            }

            $this->spawnProcess($task, $taskStarterPath);
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Atomically claims a task row by flipping its status to 'Running'.
     *
     * The WHERE clause includes Status = 'ON' so that a concurrent scheduler
     * call cannot claim the same row twice.
     *
     * @param  array<string, mixed> $task Row data from the tasks table.
     * @return bool True when the row was successfully claimed.
     */
    private function claimTask(array $task): bool
    {
        $fingerprint = md5(uniqid((string) mt_rand(), true));

        /*
         * Nextrun advancement logic:
         *   - Minutes = 1440  → add exactly 1 DAY so the time-of-day is preserved
         *                        (e.g. a 03:00 task stays at 03:00 next day).
         *   - Any other value → add the interval from NOW() so that tasks which
         *                        were overdue catch up to a future slot immediately.
         */
        $updateExpression =
            "Nextrun = IF(
                Minutes = 1440,
                DATE_ADD(Nextrun, INTERVAL 1 DAY),
                DATE_ADD(NOW(),   INTERVAL {$task['Minutes']} MINUTE)
            ),
            Status      = 'Running',
            LastStart   = NOW(),
            Heartbeat   = NOW(),
            Fingerprint = '$fingerprint'";

        $whereClause = [
            'ID'     => $task['ID'],
            'Status' => 'ON',   // guard against concurrent claims
        ];

        try {
            $updated = $this->db->update(
                $this->tasksTable,
                $updateExpression,
                '',
                $whereClause
            );
        } catch (\Exception $e) {
            $this->handleException($e);
            return false;
        }

        if (!$updated) {
            $this->log(
                sprintf(
                    'Task ID %d could not be claimed – Status was no longer ON.',
                    $task['ID']
                ),
                __FILE__,
                __LINE__
            );
            return false;
        }

        // Store the fingerprint back so spawnProcess can forward it.
        $task['_fingerprint'] = $fingerprint;

        return true;
    }

    /**
     * Launches the task as a detached background process.
     *
     * The spawned process receives three CLI arguments:
     *   1. Tasks table name
     *   2. Task row ID
     *   3. Fingerprint – used by the child to verify it holds the lock
     *
     * @param  array<string, mixed> $task            Row data (must include _fingerprint).
     * @param  string               $taskStarterPath Absolute path to the starter script.
     */
    private function spawnProcess(array $task, string $taskStarterPath): void
    {
        $label       = sprintf('%s (table: %s)', $task['TaskClass'], $this->tasksTable);
        $fingerprint = $task['_fingerprint'] ?? md5(uniqid((string) mt_rand(), true));

        $baseCommand = implode(' ', [
            escapeshellcmd($this->phpCliPath),
            escapeshellarg($taskStarterPath),
            escapeshellarg($this->tasksTable),
            (int) $task['ID'],
            escapeshellarg($fingerprint),
        ]);

        $this->log("Launching task: $label", __FILE__, __LINE__);

        if ($this->isWindows()) {
            // "start /B" launches the process without opening a new console window.
            // Credit: https://code-boxx.com/php-background-process/
            pclose(popen("start /B $baseCommand", 'r'));
        } else {
            // Redirect stdout/stderr to /dev/null and detach from the parent.
            exec("$baseCommand > /dev/null 2>&1 &");
        }

        $this->log("Task launched: $label", __FILE__, __LINE__);
    }

    /**
     * Determines whether the current environment is Windows.
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(php_uname('s'), 0, 3)) === 'WIN';
    }

    /**
     * Handles an exception by logging it to both the exception log and the
     * task-runner log file.
     *
     * @param \Exception $exception The caught exception.
     */
    private function handleException(\Exception $exception): void
    {
        $message = $exception->getMessage();
        new Logging($message, __FILE__, __LINE__);
        $this->log($message, __FILE__, __LINE__);
    }

    /**
     * Writes a timestamped entry to the task-runner log file.
     *
     * @param string $message The message to log.
     * @param string $file    The source file path (__FILE__).
     * @param int    $line    The source line number (__LINE__).
     */
    protected function log(string $message, string $file, int $line): void
    {
        $logPath = Conf::get('APATH') . 'system/log/' . $this->logFile;
        new Logging($message, $file, $line, $logPath);
    }
}
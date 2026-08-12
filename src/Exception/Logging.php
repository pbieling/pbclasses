<?php
/**
 * Exception and Error Logging Service
 * 
 * @author Peter Bieling
 */
declare(strict_types=1);

namespace PbClasses\Exception;

use PbClasses\Conf;
use RuntimeException;

class Logging
{
    private const DEFAULT_OUTFILE = 'system/log/exception';
    private const DEFAULT_OUTFILE_EXT = '.log';
    private const FILE_PERMISSIONS = 0666;
    private const SEPARATOR     = "-------------------------------" . PHP_EOL;
    private const END_SEPARATOR =  PHP_EOL . PHP_EOL;

    private string $logfile;

    /**
     * Create and write a log entry
     *
     * @param mixed $message The message or data to log
     * @param string $errorfile The file where the error occurred (use __FILE__)
     * @param int|string $line The line number where the error occurred (use __LINE__)
     * @param string $outfile Custom log file path (optional)
     * @throws RuntimeException If the log file cannot be opened or written
     */
    public function __construct(
        mixed $message,
        string $errorfile = '',
        int|string $line = '',
        string $outfile = ''
    ) {
        $this->logfile = $this->determineLogFile($outfile);
        $this->writeLog($message, $errorfile, $line);
    }

    /**
     * Determine the appropriate log file path
     */
    private function determineLogFile(string $outfile): string
    {
        if ($outfile !== '') {
            return $outfile;
        }

        $apath = Conf::get('APATH');
        $cli = $this->isCli() ? '_cli' : '';
        
        return $apath . self::DEFAULT_OUTFILE . $cli . self::DEFAULT_OUTFILE_EXT;
    }

    /**
     * Check if running in CLI mode
     */
    private function isCli(): bool
    {
        return empty($_SERVER['DOCUMENT_ROOT']) || PHP_SAPI === 'cli';
    }

    /**
     * Write the log entry to file
     */
    private function writeLog(mixed $message, string $errorfile, int|string $line): void
    {
        $fileExistedBefore = file_exists($this->logfile);
        $message = $this->formatMessage($message);
        $entry = $this->buildLogEntry($message, $errorfile, $line);

        try {
            $this->writeToFile($entry);
            
            if (!$fileExistedBefore) {
                @chmod($this->logfile, self::FILE_PERMISSIONS);
            }
        } catch (RuntimeException $e) {
            trigger_error(
                sprintf(
                    'Log error: %s - File: %s Line: %d',
                    $e->getMessage(),
                    __FILE__,
                    __LINE__
                ),
                E_USER_WARNING
            );
        }
    }

    /**
     * Format message for logging
     */
    private function formatMessage(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        return print_r($message, true);
    }

    /**
     * Build the complete log entry
     */
    private function buildLogEntry(string $message, string $errorfile, int|string $line): string
    {
        $uid = $_SESSION['uid'] ?? 0;
        $timestamp = date('Y-m-d H:i:s');
        
        $parts = [
            self::SEPARATOR,
            $timestamp,
            "UID: {$uid}",
            $message,
        ];

        if ($errorfile !== '') {
            $parts[] = "{$errorfile} Line: {$line}";
        }

        $parts[] = self::END_SEPARATOR;

        return implode("\n", $parts);
    }

    /**
     * Write content to the log file
     *
     * @throws RuntimeException If file cannot be opened or written
     */
    private function writeToFile(string $content): void
    {
        $fh = @fopen($this->logfile, 'a');
        
        if ($fh === false) {
            throw new RuntimeException(
                "Cannot open log file: {$this->logfile}"
            );
        }

        try {
            if (@fwrite($fh, $content) === false) {
                throw new RuntimeException(
                    "Cannot write to log file: {$this->logfile}"
                );
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * Get the current log file path
     */
    public function getLogFile(): string
    {
        return $this->logfile;
    }
}

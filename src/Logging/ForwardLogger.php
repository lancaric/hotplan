<?php

declare(strict_types=1);

namespace HotPlan\Logging;

use HotPlan\Config\ConfigLoader;

/**
 * Forward Logger
 * 
 * Logs forwarding decisions and device communication.
 */
class ForwardLogger
{
    private string $logFile;
    private string $deviceLogFile;
    private string $auditLogFile;
    private bool $enabled;
    private ConfigLoader $config;
    
    /**
     * Log levels
     */
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    
    /**
     * Log level priorities
     */
    private const LEVEL_PRIORITIES = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4,
    ];
    
    public function __construct(?ConfigLoader $config = null)
    {
        $this->config = $config ?? ConfigLoader::getInstance();
        $this->enabled = $this->config->get('behavior.enable_logging', true);
        
        $this->logFile = $this->resolveLogPath(
            (string) ($this->config->get('logging.file', 'logs/hotplan.log') ?? 'logs/hotplan.log')
        );
        $this->deviceLogFile = $this->resolveLogPath(
            (string) ($this->config->get('logging.device_log', 'logs/device.log') ?? 'logs/device.log')
        );
        $this->auditLogFile = $this->resolveLogPath(
            (string) ($this->config->get('logging.audit_log', 'logs/audit.log') ?? 'logs/audit.log')
        );
        
        $this->ensureLogDirectories();
    }

    /**
     * Resolve relative log paths from the project root while allowing absolute paths.
     */
    private function resolveLogPath(string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~', $path) === 1) {
            return $path;
        }

        return dirname(__DIR__, 2) . '/' . ltrim($path, '/\\');
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectories(): void
    {
        foreach (array_unique([
            dirname($this->logFile),
            dirname($this->deviceLogFile),
            dirname($this->auditLogFile),
        ]) as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("Unable to create log directory: {$dir}");
            }
        }
    }
    
    /**
     * Log a message
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $levelPriority = self::LEVEL_PRIORITIES[$level] ?? 0;
        $configLevel = self::LEVEL_PRIORITIES[$this->config->get('app.log_level', 'info')] ?? 1;
        
        if ($levelPriority < $configLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        $this->writeLine($this->logFile, $line);
    }
    
    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log device communication
     */
    public function logDevice(string $action, string $forwardTo, bool $success, ?string $error = null, array $metadata = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $errorStr = $error ? " Error: {$error}" : '';
        $metadataStr = !empty($metadata) ? ' ' . json_encode($metadata) : '';
        
        $line = "[{$timestamp}] [{$status}] {$action} -> {$forwardTo}{$errorStr}{$metadataStr}\n";
        
        $this->writeLine($this->deviceLogFile, $line);
    }
    
    /**
     * Log audit event
     */
    public function logAudit(string $action, string $entityType, ?int $entityId, array $details = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $user = $details['user'] ?? 'system';
        $oldValue = isset($details['old_value']) ? json_encode($details['old_value']) : 'null';
        $newValue = isset($details['new_value']) ? json_encode($details['new_value']) : 'null';
        
        $line = "[{$timestamp}] [{$action}] {$entityType}";
        if ($entityId !== null) {
            $line .= " #{$entityId}";
        }
        $line .= " by {$user}";
        $line .= " Old: {$oldValue} New: {$newValue}\n";
        
        $this->writeLine($this->auditLogFile, $line);
    }
    
    /**
     * Log forwarding decision
     */
    public function logDecision(
        string $forwardTo,
        ?string $previousForwardTo,
        string $reason,
        ?string $ruleName,
        ?string $ruleType,
        bool $deviceUpdated,
        ?string $deviceError,
    ): void {
        $changed = $previousForwardTo !== $forwardTo;
        
        $this->info('Forwarding decision', [
            'forward_to' => $forwardTo,
            'previous_forward_to' => $previousForwardTo,
            'changed' => $changed,
            'reason' => $reason,
            'rule_name' => $ruleName,
            'rule_type' => $ruleType,
            'device_updated' => $deviceUpdated,
            'device_error' => $deviceError,
        ]);
    }
    
    /**
     * Get recent log entries
     */
    public function getRecentLogs(int $lines = 100, ?string $level = null): array
    {
        $entries = $this->readLastLines($this->logFile, $lines);

        if ($level !== null) {
            $entries = array_filter(
                $entries,
                fn($line) => str_contains($line, "[{$level}]")
            );
        }

        return array_values($entries);
    }
    
    /**
     * Get recent device logs
     */
    public function getRecentDeviceLogs(int $lines = 100): array
    {
        return $this->readLastLines($this->deviceLogFile, $lines);
    }

    /**
     * Append one complete entry and enforce the configured size limit.
     */
    private function writeLine(string $file, string $line): void
    {
        $lock = fopen($file . '.lock', 'c');

        if ($lock === false) {
            throw new \RuntimeException("Unable to open log lock: {$file}.lock");
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock log file: {$file}");
            }

            $maxSize = $this->parseSize(
                (string) ($this->config->get('logging.max_size', '10M') ?? '10M')
            );
            $maxFiles = max(1, (int) ($this->config->get('logging.max_files', 5) ?? 5));

            clearstatcache(true, $file);
            $currentSize = is_file($file) ? filesize($file) : 0;

            if ($maxSize > 0 && $currentSize !== false && $currentSize + strlen($line) > $maxSize) {
                $this->rotateLogFile($file, $maxSize, $maxFiles, true);
            }

            if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException("Unable to write log file: {$file}");
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Read the last N lines from a log file without loading the whole file.
     */
    private function readLastLines(string $file, int $lines): array
    {
        if (!is_file($file) || $lines <= 0) {
            return [];
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            if (fseek($handle, 0, SEEK_END) !== 0) {
                return [];
            }

            $position = ftell($handle);

            if ($position === false || $position === 0) {
                return [];
            }

            $buffer = '';

            while ($position > 0 && substr_count($buffer, "\n") <= $lines) {
                $readSize = min(8192, $position);
                $position -= $readSize;

                fseek($handle, $position);
                $chunk = fread($handle, $readSize);

                if ($chunk === false) {
                    break;
                }

                $buffer = $chunk . $buffer;
            }

            $entries = explode("\n", rtrim($buffer, "\r\n"));

            if ($entries === ['']) {
                return [];
            }

            return array_values(array_map('trim', array_slice($entries, -$lines)));
        } finally {
            fclose($handle);
        }
    }
    
    /**
     * Rotate logs if they exceed max size
     */
    public function rotateLogs(): void
    {
        $maxSize = $this->parseSize(
            (string) ($this->config->get('logging.max_size', '10M') ?? '10M')
        );
        $maxFiles = (int) ($this->config->get('logging.max_files', 5) ?? 5);

        if ($maxSize <= 0 || $maxFiles <= 0) {
            return;
        }
        
        $this->rotateLogFile($this->logFile, $maxSize, $maxFiles);
        $this->rotateLogFile($this->deviceLogFile, $maxSize, $maxFiles);
        $this->rotateLogFile($this->auditLogFile, $maxSize, $maxFiles);
    }
    
    /**
     * Rotate a single log file
     */
    private function rotateLogFile(string $file, int $maxSize, int $maxFiles, bool $force = false): void
    {
        if (!file_exists($file)) {
            return;
        }
        
        $size = filesize($file);
        
        if ($size === false || (!$force && $size < $maxSize)) {
            return;
        }

        $oldestFile = "{$file}.{$maxFiles}";
        if (is_file($oldestFile) && !unlink($oldestFile)) {
            throw new \RuntimeException("Unable to remove old log file: {$oldestFile}");
        }
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i > 0; $i--) {
            $oldFile = "{$file}.{$i}";
            $newFile = "{$file}." . ($i + 1);
            
            if (is_file($oldFile) && !rename($oldFile, $newFile)) {
                throw new \RuntimeException("Unable to rotate log file: {$oldFile}");
            }
        }
        
        // Move current to .1
        if (!rename($file, "{$file}.1")) {
            throw new \RuntimeException("Unable to rotate log file: {$file}");
        }
    }
    
    /**
     * Parse size string (e.g., "10M" -> bytes)
     */
    private function parseSize(string $size): int
    {
        $unit = strtoupper(substr($size, -1));
        $value = (int) $size;
        
        return match ($unit) {
            'K' => $value * 1024,
            'M' => $value * 1024 * 1024,
            'G' => $value * 1024 * 1024 * 1024,
            default => $value,
        };
    }
    
    /**
     * Clear all logs
     */
    public function clearLogs(): void
    {
        file_put_contents($this->logFile, '');
        file_put_contents($this->deviceLogFile, '');
        file_put_contents($this->auditLogFile, '');
    }
    
    /**
     * Get log statistics
     */
    public function getStats(): array
    {
        return [
            'main_log' => [
                'file' => $this->logFile,
                'size' => file_exists($this->logFile) ? filesize($this->logFile) : 0,
                'lines' => $this->countLogLines($this->logFile),
            ],
            'device_log' => [
                'file' => $this->deviceLogFile,
                'size' => file_exists($this->deviceLogFile) ? filesize($this->deviceLogFile) : 0,
                'lines' => $this->countLogLines($this->deviceLogFile),
            ],
            'audit_log' => [
                'file' => $this->auditLogFile,
                'size' => file_exists($this->auditLogFile) ? filesize($this->auditLogFile) : 0,
                'lines' => $this->countLogLines($this->auditLogFile),
            ],
        ];
    }

    /**
     * Count log lines without loading the full file.
     */
    private function countLogLines(string $file): int
    {
        if (!is_file($file)) {
            return 0;
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            return 0;
        }

        $count = 0;
        $lastCharacter = '';

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 65536);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $count += substr_count($chunk, "\n");
                $lastCharacter = substr($chunk, -1);
            }
        } finally {
            fclose($handle);
        }

        return $count + ($lastCharacter !== '' && $lastCharacter !== "\n" ? 1 : 0);
    }
}

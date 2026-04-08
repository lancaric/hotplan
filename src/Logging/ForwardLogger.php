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
        
        $basePath = dirname(__DIR__, 2) . '/';
        $this->logFile = $basePath . ($this->config->get('logging.file', 'logs/hotplan.log') ?? 'logs/hotplan.log');
        $this->deviceLogFile = $basePath . ($this->config->get('logging.device_log', 'logs/device.log') ?? 'logs/device.log');
        $this->auditLogFile = $basePath . ($this->config->get('logging.audit_log', 'logs/audit.log') ?? 'logs/audit.log');
        
        $this->ensureLogDirectory();
    }
    
    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
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
        
        file_put_contents($this->logFile, $line, FILE_APPEND);
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
        
        file_put_contents($this->deviceLogFile, $line, FILE_APPEND);
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
        
        file_put_contents($this->auditLogFile, $line, FILE_APPEND);
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
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file($this->logFile);
        $entries = array_slice($content, -$lines);
        
        if ($level !== null) {
            $entries = array_filter($entries, fn($line) => str_contains($line, "[{$level}]"));
        }
        
        return array_map('trim', $entries);
    }
    
    /**
     * Get recent device logs
     */
    public function getRecentDeviceLogs(int $lines = 100): array
    {
        if (!file_exists($this->deviceLogFile)) {
            return [];
        }
        
        $content = file($this->deviceLogFile);
        $entries = array_slice($content, -$lines);
        
        return array_map('trim', $entries);
    }
    
    /**
     * Rotate logs if they exceed max size
     */
    public function rotateLogs(): void
    {
        $maxSize = $this->parseSize($this->config->get('logging.max_size', '10M') ?? '10M');
        $maxFiles = (int) ($this->config->get('logging.max_files', 5) ?? 5);
        
        $this->rotateLogFile($this->logFile, $maxSize, $maxFiles);
        $this->rotateLogFile($this->deviceLogFile, $maxSize, $maxFiles);
        $this->rotateLogFile($this->auditLogFile, $maxSize, $maxFiles);
    }
    
    /**
     * Rotate a single log file
     */
    private function rotateLogFile(string $file, int $maxSize, int $maxFiles): void
    {
        if (!file_exists($file)) {
            return;
        }
        
        $size = filesize($file);
        
        if ($size < $maxSize) {
            return;
        }
        
        // Rotate existing files
        for ($i = $maxFiles - 1; $i > 0; $i--) {
            $oldFile = "{$file}.{$i}";
            $newFile = "{$file}." . ($i + 1);
            
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }
        
        // Move current to .1
        rename($file, "{$file}.1");
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
                'lines' => file_exists($this->logFile) ? count(file($this->logFile)) : 0,
            ],
            'device_log' => [
                'file' => $this->deviceLogFile,
                'size' => file_exists($this->deviceLogFile) ? filesize($this->deviceLogFile) : 0,
                'lines' => file_exists($this->deviceLogFile) ? count(file($this->deviceLogFile)) : 0,
            ],
            'audit_log' => [
                'file' => $this->auditLogFile,
                'size' => file_exists($this->auditLogFile) ? filesize($this->auditLogFile) : 0,
                'lines' => file_exists($this->auditLogFile) ? count(file($this->auditLogFile)) : 0,
            ],
        ];
    }
}

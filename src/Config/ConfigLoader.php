<?php

declare(strict_types=1);

namespace HotPlan\Config;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration Loader
 * 
 * Loads and manages application configuration from YAML files
 * with support for environment variable substitution.
 */
class ConfigLoader
{
    private array $config = [];
    private string $configPath;
    private static ?ConfigLoader $instance = null;
    
    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 2) . '/config/config.yaml';
        $this->load();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(?string $configPath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($configPath);
        }
        return self::$instance;
    }
    
    /**
     * Reset singleton (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
    
    /**
     * Load configuration from YAML file
     */
    private function load(): void
    {
        if (!file_exists($this->configPath)) {
            throw new \RuntimeException("Configuration file not found: {$this->configPath}");
        }
        
        $content = file_get_contents($this->configPath);
        $content = $this->substituteEnvVariables($content);

        $this->config = $this->parseYaml($content, $this->configPath);
    }

    /**
     * Parse YAML content into array config
     */
    private function parseYaml(string $content, string $sourcePathForErrors = 'config'): array
    {
        $parsed = null;

        // Prefer a pure-PHP YAML parser to avoid requiring ext-yaml.
        if (class_exists(Yaml::class)) {
            try {
                $parsed = Yaml::parse($content);
            } catch (ParseException $e) {
                throw new \RuntimeException(
                    "Failed to parse configuration file: {$sourcePathForErrors}. " . $e->getMessage(),
                    0,
                    $e
                );
            }
        } elseif (function_exists('yaml_parse')) {
            $parsed = yaml_parse($content);
            if ($parsed === false) {
                throw new \RuntimeException("Failed to parse configuration file: {$sourcePathForErrors}");
            }
        } else {
            throw new \RuntimeException(
                "No YAML parser available. Install symfony/yaml (recommended) or enable the PHP yaml extension."
            );
        }

        if ($parsed === null) {
            return [];
        }

        if (!is_array($parsed)) {
            throw new \RuntimeException("Configuration root must be a YAML mapping/object: {$sourcePathForErrors}");
        }

        return $parsed;
    }

    /**
     * Substitute environment variables in format ${VAR_NAME}
     */
    private function substituteEnvVariables(string $content): string
    {
        return preg_replace_callback('/\$\{([A-Z_][A-Z0-9_]*)\}/', function ($matches) {
            $envVar = $matches[1];
            $value = getenv($envVar);
            return $value !== false ? $value : $matches[0];
        }, $content);
    }
    
    /**
     * Get configuration value by dot-notation path
     * 
     * @param string $path e.g., "voip.host" or "defaults.forward_internal"
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $keys = explode('.', $path);
        $value = $this->config;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        
        return $value;
    }
    
    /**
     * Set configuration value
     */
    public function set(string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $config = &$this->config;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $config[$key] = $value;
            } else {
                if (!isset($config[$key]) || !is_array($config[$key])) {
                    $config[$key] = [];
                }
                $config = &$config[$key];
            }
        }
    }
    
    /**
     * Check if configuration key exists
     */
    public function has(string $path): bool
    {
        return $this->get($path) !== null;
    }
    
    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }
    
    /**
     * Get VoIP device configuration
     */
    public function getVoipConfig(): array
    {
        return [
            'provider' => $this->get('voip.provider', 'sipura'),
            'host' => $this->get('voip.host', 'localhost'),
            'port' => (int) $this->get('voip.port', 80),
            'path' => $this->get('voip.path', '/admin/bsipura.spa'),
            'timeout' => (int) $this->get('voip.timeout', 30),
            'retry_count' => (int) $this->get('voip.retry_count', 3),
            'retry_delay' => (int) $this->get('voip.retry_delay', 5),
            'forward_param' => $this->get('voip.forward_param', '43567'),
            'forward_prefix' => $this->get('voip.forward_prefix', ''),
            'auth_type' => $this->get('voip.auth_type', 'digest'),
        ];
    }
    
    /**
     * Get VoIP credentials
     */
    public function getVoipCredentials(): array
    {
        return [
            'username' => $this->get('credentials.voip_username', ''),
            'password' => $this->get('credentials.voip_password', ''),
        ];
    }
    
    /**
     * Get behavior configuration
     */
    public function getBehaviorConfig(): array
    {
        return [
            'on_no_rule' => $this->get('behavior.on_no_rule', 'fallback'),
            'on_device_error' => $this->get('behavior.on_device_error', 'keep_last'),
            'on_multiple_match' => $this->get('behavior.on_multiple_match', 'priority'),
            'enable_logging' => (bool) $this->get('behavior.enable_logging', true),
            'log_retention_days' => (int) $this->get('behavior.log_retention_days', 90),
        ];
    }
    
    /**
     * Get scheduler configuration
     */
    public function getSchedulerConfig(): array
    {
        return [
            'enabled' => (bool) $this->get('scheduler.enabled', true),
            'check_interval' => (int) $this->get('scheduler.check_interval', 60),
            'preload_minutes' => (int) $this->get('scheduler.preload_minutes', 5),
        ];
    }
    
    /**
     * Get default forwarding numbers
     */
    public function getDefaults(): array
    {
        return [
            'forward_internal' => $this->get('defaults.forward_internal', '100'),
            'forward_external' => $this->get('defaults.forward_external', ''),
            'forward_voicemail' => $this->get('defaults.forward_voicemail', '*97'),
            'fallback' => $this->get('defaults.fallback', ''),
        ];
    }
    
    /**
     * Reload configuration from file
     */
    public function reload(): void
    {
        $this->load();
    }
    
    /**
     * Save configuration to file
     */
    public function save(): void
    {
        $yaml = $this->dumpYaml($this->config);
        file_put_contents($this->configPath, $yaml);
    }

    /**
     * Dump config array to YAML string
     */
    private function dumpYaml(array $config): string
    {
        if (class_exists(Yaml::class)) {
            return Yaml::dump($config, 10, 2);
        }

        // Fallback (minimal YAML) if symfony/yaml is not installed.
        return $this->arrayToYaml($config);
    }

    /**
     * Convert array to YAML string
     */
    private function arrayToYaml(array $array, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);
        
        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value) && $this->isAssociativeArray($value)) {
                $yaml .= "{$spaces}{$key}:\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } elseif (is_array($value)) {
                $yaml .= "{$spaces}{$key}:\n";
                foreach ($value as $item) {
                    $yaml .= "{$spaces}  - {$item}\n";
                }
            } elseif (is_bool($value)) {
                $yaml .= "{$spaces}{$key}: " . ($value ? 'true' : 'false') . "\n";
            } elseif (is_numeric($value)) {
                $yaml .= "{$spaces}{$key}: {$value}\n";
            } else {
                $yaml .= "{$spaces}{$key}: \"{$value}\"\n";
            }
        }
        
        return $yaml;
    }
    
    /**
     * Check if array is associative
     */
    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

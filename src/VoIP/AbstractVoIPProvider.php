<?php

declare(strict_types=1);

namespace HotPlan\VoIP;

/**
 * Base implementation for VoIP providers.
 */
abstract class AbstractVoIPProvider implements VoIPProviderInterface
{
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isReachable(): bool
    {
        $url = $this->buildUrl();

        $connectTimeout = (int)($this->config['connect_timeout'] ?? 2);
        $timeout = (int)($this->config['timeout'] ?? 5);
        $timeout = max(1, min(5, $timeout));
        $connectTimeout = max(1, min($timeout, $connectTimeout));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            // Some devices/proxies don't support HEAD reliably; do a tiny GET instead.
            CURLOPT_HTTPGET => true,
            CURLOPT_RANGE => '0-0',
        ]);

        $ok = curl_exec($ch) !== false;
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $ok && $http > 0;
    }

    public function getDeviceStatus(): array
    {
        $reachable = $this->isReachable();

        return [
            'reachable' => $reachable,
            'host' => $this->config['host'] ?? null,
            'port' => $this->config['port'] ?? null,
            'path' => $this->config['path'] ?? null,
            'provider' => $this->getType(),
        ];
    }

    public function testConnection(): DeviceResponse
    {
        return $this->isReachable()
            ? DeviceResponse::success('reachable')
            : DeviceResponse::error('Device not reachable', 0);
    }

    protected function buildUrl(?string $pathOverride = null, array $query = []): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = (int)($this->config['port'] ?? 80);
        $path = $pathOverride ?? ($this->config['path'] ?? '/');

        $scheme = ($port === 443) ? 'https' : 'http';
        $base = "{$scheme}://{$host}:{$port}{$path}";

        if (empty($query)) {
            return $base;
        }

        return $base . '?' . http_build_query($query);
    }

    protected function request(string $method, string $url, array $options = []): DeviceResponse
    {
        $ch = curl_init($url);

        $connectTimeout = (int)($this->config['connect_timeout'] ?? 5);
        $timeout = (int)($this->config['timeout'] ?? 30);
        $timeout = max(1, $timeout);
        $connectTimeout = max(1, min($timeout, $connectTimeout));
        $username = $this->config['username'] ?? null;
        $password = $this->config['password'] ?? null;
        $authType = strtolower((string)($this->config['auth_type'] ?? 'none'));

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ];

        if (isset($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        if (isset($options['body'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }

        if ($username !== null && $password !== null && $authType !== 'none') {
            $curlOptions[CURLOPT_USERPWD] = $username . ':' . $password;
            $curlOptions[CURLOPT_HTTPAUTH] = match ($authType) {
                'basic' => CURLAUTH_BASIC,
                'digest' => CURLAUTH_DIGEST,
                default => CURLAUTH_ANY,
            };
        }

        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false) {
            return DeviceResponse::error($curlErr ?: 'cURL error', $httpCode);
        }

        if ($httpCode >= 400 || $httpCode === 0) {
            return DeviceResponse::error("HTTP {$httpCode}", $httpCode, (string)$body);
        }

        return DeviceResponse::success((string)$body, ['http_code' => $httpCode]);
    }
}

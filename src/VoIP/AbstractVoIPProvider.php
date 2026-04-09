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
        if (!$this->isReachable()) {
            return DeviceResponse::error('Device not reachable', 0);
        }

        // Try an authenticated GET against the configured path to validate auth/path.
        $resp = $this->request('GET', $this->buildUrl());
        if ($resp->isSuccess()) {
            return DeviceResponse::success('ok', $resp->httpCode, $resp->metadata);
        }

        return $resp;
    }

    protected function buildUrl(?string $pathOverride = null, array $query = []): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = (int)($this->config['port'] ?? 80);
        $path = $pathOverride ?? ($this->config['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

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

        if ($authType !== 'none') {
            $u = is_string($username) ? trim($username) : '';
            $p = is_string($password) ? trim($password) : '';
            if ($u === '' || $p === '') {
                return DeviceResponse::error(
                    'VoIP credentials missing (set credentials.voip_username / credentials.voip_password or HOTPLAN_VOIP_USERNAME/HOTPLAN_VOIP_PASSWORD)',
                    401
                );
            }
        }

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'HotPlan/1.0',
        ];

        if (isset($options['headers'])) {
            $curlOptions[CURLOPT_HTTPHEADER] = $options['headers'];
        }

        if (isset($options['body'])) {
            $curlOptions[CURLOPT_POSTFIELDS] = $options['body'];
        }

        if ($authType !== 'none') {
            $curlOptions[CURLOPT_USERPWD] = (string) $username . ':' . (string) $password;
            $curlOptions[CURLOPT_HTTPAUTH] = match ($authType) {
                'basic' => CURLAUTH_BASIC,
                'digest' => CURLAUTH_DIGEST,
                default => CURLAUTH_ANY,
            };
        }

        curl_setopt_array($ch, $curlOptions);

        $body = curl_exec($ch);
        $curlErr = curl_error($ch);
        $curlErrNo = (int) curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $totalTime = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME);

        if ($body === false) {
            return DeviceResponse::error(
                $curlErr ?: 'cURL error',
                $httpCode,
                null,
                [
                    'curl_errno' => $curlErrNo,
                    'http_code' => $httpCode,
                    'effective_url' => $effectiveUrl,
                    'content_type' => $contentType,
                    'total_time' => $totalTime,
                ]
            );
        }

        $bodyStr = (string) $body;
        $snippet = $this->snippetForLogs($bodyStr);
        $metadata = [
            'http_code' => $httpCode,
            'effective_url' => $effectiveUrl,
            'content_type' => $contentType,
            'total_time' => $totalTime,
            'body_len' => strlen($bodyStr),
            'body_snippet' => $snippet,
        ];

        if ($httpCode >= 400 || $httpCode === 0) {
            $msg = $this->extractHttpErrorMessage($httpCode, $bodyStr) ?? "HTTP {$httpCode}";
            return DeviceResponse::error($msg, $httpCode, null, $metadata);
        }

        // Some firmwares respond with HTTP 200 but show a "Login denied" page.
        if ($authType !== 'none') {
            $text = preg_replace('/\\s+/', ' ', strip_tags($bodyStr)) ?? '';
            if (preg_match('/\\bLogin\\s+denied\\b/i', $text)) {
                return DeviceResponse::error('Login denied (wrong username/password or auth_type)', 401, null, $metadata);
            }
        }

        return DeviceResponse::success($bodyStr, $httpCode, $metadata);
    }

    private function snippetForLogs(string $body, int $maxLen = 300): string
    {
        $text = trim(preg_replace('/\\s+/', ' ', strip_tags($body)) ?? '');
        if ($text === '') {
            $text = trim(preg_replace('/\\s+/', ' ', $body) ?? '');
        }
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        return substr($text, 0, $maxLen) . '…';
    }

    private function extractHttpErrorMessage(int $httpCode, string $body): ?string
    {
        $text = preg_replace('/\\s+/', ' ', strip_tags($body)) ?? '';
        if (preg_match('/\\bLogin\\s+denied\\b/i', $text)) {
            return 'Login denied (wrong username/password or auth_type)';
        }
        if ($httpCode === 401) {
            return 'Unauthorized (check username/password and auth_type)';
        }
        if ($httpCode === 404) {
            return 'Not Found (check voip.path)';
        }
        return null;
    }
}

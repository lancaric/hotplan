<?php

declare(strict_types=1);

namespace HotPlan\VoIP\Sipura;

use HotPlan\VoIP\AbstractVoIPProvider;
use HotPlan\VoIP\DeviceResponse;

/**
 * Sipura/Linksys SPA provider (basic implementation).
 *
 * Note: device firmwares differ; treat this as a starting point.
 */
class SipuraProvider extends AbstractVoIPProvider
{
    public function getType(): string
    {
        return 'sipura';
    }

    public function getName(): string
    {
        return 'Sipura/Linksys SPA';
    }

    public function getCapabilities(): array
    {
        return [
            'set_forward' => true,
            'clear_forward' => true,
            'read_forward' => false,
        ];
    }

    public function setForwardTo(string $number): DeviceResponse
    {
        $forwardParam = (string)($this->config['forward_param'] ?? '');
        if ($forwardParam === '') {
            return DeviceResponse::error('Missing config: voip.forward_param');
        }

        $prefix = (string)($this->config['forward_prefix'] ?? '');
        $value = $prefix . $number;

        // Prefer simulating the HTML form submit; some firmwares ignore bare querystring updates.
        $submitted = $this->submitFormField($forwardParam, $value);
        if ($submitted instanceof DeviceResponse) {
            return $submitted;
        }

        // Fallback: submit config via querystring; exact endpoint differs by firmware.
        $url = $this->buildUrl(null, [$forwardParam => $value]);
        return $this->request('GET', $url);
    }

    public function getCurrentForwardTo(): DeviceResponse
    {
        return DeviceResponse::error('Not implemented for Sipura provider', 501);
    }

    public function clearForward(): DeviceResponse
    {
        $forwardParam = (string)($this->config['forward_param'] ?? '');
        if ($forwardParam === '') {
            return DeviceResponse::error('Missing config: voip.forward_param');
        }

        $submitted = $this->submitFormField($forwardParam, '');
        if ($submitted instanceof DeviceResponse) {
            return $submitted;
        }

        $url = $this->buildUrl(null, [$forwardParam => '']);
        return $this->request('GET', $url);
    }

    public function executeCommand(string $command, array $parameters = []): DeviceResponse
    {
        $url = $this->buildUrl(null, array_merge(['cmd' => $command], $parameters));
        return $this->request('GET', $url);
    }

    /**
     * Try to submit via HTML form. Returns:
     * - DeviceResponse on success or on a hard failure (auth/path errors)
     * - null when form submit strategy isn't applicable (so caller may fallback)
     */
    private function submitFormField(string $fieldName, string $fieldValue): ?DeviceResponse
    {
        $pageUrl = $this->buildUrl();
        $page = $this->request('GET', $pageUrl);
        if (!$page->isSuccess()) {
            return DeviceResponse::error(
                "Failed to load Sipura config page (check voip.path/auth): {$page->error}",
                $page->httpCode,
                null,
                array_merge($page->metadata, ['stage' => 'get_page'])
            );
        }
        if ($page->data === null || $page->data === '') {
            return DeviceResponse::error(
                'Sipura config page returned empty body',
                $page->httpCode,
                null,
                array_merge($page->metadata, ['stage' => 'get_page'])
            );
        }

        $form = $this->parseFirstForm($page->data);
        if ($form === null) {
            return null;
        }

        $fields = $form['fields'];
        $fields[$fieldName] = $fieldValue;

        // Try to include a submit button if present, to mimic "Submit All Changes".
        if (!isset($fields['_submit']) && isset($form['submit'])) {
            $fields[$form['submit']['name']] = $form['submit']['value'];
        }

        $body = http_build_query($fields);
        $resp = $this->request('POST', $form['action'], [
            'headers' => [
                'Content-Type: application/x-www-form-urlencoded',
                "Referer: {$pageUrl}",
            ],
            'body' => $body,
        ]);

        if (!$resp->isSuccess()) {
            return $resp;
        }

        // Best-effort verification: re-fetch and check the value is reflected in the form.
        $verify = $this->request('GET', $this->buildUrl());
        if ($verify->isSuccess() && is_string($verify->data) && $verify->data !== '') {
            $pattern = '/\\bname\\s*=\\s*([\"\\\'])' . preg_quote($fieldName, '/') . '\\1\\b[^>]*\\bvalue\\s*=\\s*([\"\\\'])(.*?)\\2/i';
            if (preg_match($pattern, $verify->data, $vm)) {
                $currentValue = html_entity_decode((string) $vm[3], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                if ($currentValue === $fieldValue) {
                    return DeviceResponse::success('applied', 200, ['verified' => true]);
                }

                return DeviceResponse::error(
                    "Device responded OK but did not apply the value (current '{$currentValue}')",
                    502,
                    null,
                    ['verified' => true]
                );
            }
        }

        // Still report success if the device accepted the request; just signal not verified.
        return DeviceResponse::success($resp->data, 200, ['verified' => false]);
    }

    /**
     * @return array{action:string,method:string,fields:array<string,string>,submit?:array{name:string,value:string}}|null
     */
    private function parseFirstForm(string $html): ?array
    {
        if (!preg_match('/<form\\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $formTag = $m[0][0];
        $formPos = $m[0][1];
        $after = substr($html, $formPos);
        $endPos = stripos($after, '</form>');
        if ($endPos === false) {
            return null;
        }

        $formHtml = substr($after, 0, $endPos);

        $action = $this->buildUrl();
        if (preg_match('/\\baction\\s*=\\s*([\"\\\'])(.*?)\\1/i', $formTag, $am)) {
            $raw = trim((string) $am[2]);
            if ($raw !== '') {
                if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                    $action = $raw;
                } elseif (str_starts_with($raw, '/')) {
                    // Absolute path on same host
                    $action = $this->buildUrl($raw);
                } else {
                    // Relative path on same base. Resolve against the configured path as a directory when it ends with "/".
                    $basePath = str_replace('\\', '/', (string) ($this->config['path'] ?? '/'));
                    $baseDir = str_ends_with($basePath, '/')
                        ? rtrim($basePath, '/')
                        : rtrim(str_replace('\\', '/', dirname($basePath)), '/');

                    $actionPath = rtrim($baseDir !== '' ? $baseDir : '/', '/') . '/' . ltrim($raw, '/');
                    $action = $this->buildUrl($actionPath);
                }
            }
        }

        $method = 'POST';
        if (preg_match('/\\bmethod\\s*=\\s*([\"\\\'])(.*?)\\1/i', $formTag, $mm)) {
            $method = strtoupper(trim((string) $mm[2])) ?: 'POST';
        }

        $fields = [];
        $submit = null;

        if (preg_match_all('/<input\\b[^>]*>/i', $formHtml, $inputs)) {
            foreach ($inputs[0] as $inputTag) {
                $name = $this->attr($inputTag, 'name');
                if ($name === null || $name === '') {
                    continue;
                }

                $type = strtolower($this->attr($inputTag, 'type') ?? 'text');
                $value = $this->attr($inputTag, 'value') ?? '';

                if ($type === 'hidden') {
                    $fields[$name] = $value;
                    continue;
                }

                if ($type === 'submit' && $submit === null) {
                    $v = strtolower($value);
                    if ($v === '' || str_contains($v, 'submit') || str_contains($v, 'save') || str_contains($v, 'apply')) {
                        $submit = ['name' => $name, 'value' => $value];
                    }
                }
            }
        }

        if ($fields === []) {
            // Without hidden fields we still can try direct post, but parsing probably failed.
            $fields = [];
        }

        return ['action' => $action, 'method' => $method, 'fields' => $fields, 'submit' => $submit];
    }

    private function attr(string $tag, string $attr): ?string
    {
        if (preg_match('/\\b' . preg_quote($attr, '/') . '\\s*=\\s*([\"\\\'])(.*?)\\1/i', $tag, $m)) {
            return html_entity_decode((string) $m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        return null;
    }
}

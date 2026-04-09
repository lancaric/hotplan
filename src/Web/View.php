<?php

declare(strict_types=1);

namespace HotPlan\Web;

final class View
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function dateValue(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        // Accept "YYYY-MM-DD ..." and keep the date part.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) {
            return $m[1];
        }
        return '';
    }

    public static function timeValue(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        // Accept "HH:MM" or "HH:MM:SS", return "HH:MM".
        if (preg_match('/^(\d{2}):(\d{2})/', $v, $m)) {
            return $m[1] . ':' . $m[2];
        }
        return '';
    }

    public static function dateTimeLocalValue(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        // Accept "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM[:SS]" and return "YYYY-MM-DDTHH:MM".
        $v = str_replace(' ', 'T', $v);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})/', $v, $m)) {
            return $m[1] . 'T' . $m[2] . ':' . $m[3];
        }
        return '';
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function render(string $template, array $params = []): void
    {
        $base = dirname(__DIR__) . '/Web/views';
        $file = $base . '/' . ltrim($template, '/');

        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($params, EXTR_SKIP);
        require $file;
    }
}

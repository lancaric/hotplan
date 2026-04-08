<?php

declare(strict_types=1);

namespace HotPlan\Web;

final class View
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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


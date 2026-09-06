<?php
declare(strict_types=1);

namespace Grippartner;

final class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = app_path('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $safeContext = self::redact($context);
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $safeContext !== [] ? json_encode($safeContext, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
        error_log($message);
    }

    /** @param array<string,mixed> $context */
    private static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $k = strtolower((string) $key);
            if (str_contains($k, 'pass') || str_contains($k, 'secret') || str_contains($k, 'api_key') || str_contains($k, 'token')) {
                $out[$key] = '[redacted]';
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}

<?php
declare(strict_types=1);

/**
 * Grippartner bootstrap: autoload, config, sessie, security headers.
 */

namespace Grippartner;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Grippartner\\';
            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_readable($path)) {
                require_once $path;
            }
        });
    }
}

Autoloader::register();
require_once __DIR__ . '/helpers.php';

Config::load();
Security::bootstrapSession();
Security::sendHeaders();

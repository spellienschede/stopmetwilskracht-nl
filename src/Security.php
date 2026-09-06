<?php
declare(strict_types=1);

namespace Grippartner;

final class Security
{
    public static function bootstrapSession(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_name('grippartner_sess');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: frame-ancestors 'self'");
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        $session = $_SESSION['_csrf'] ?? '';
        return is_string($token) && is_string($session) && $session !== '' && hash_equals($session, $token);
    }

    public static function requireCsrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        if (!self::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo 'Ongeldige sessie. Vernieuw de pagina en probeer opnieuw.';
            exit;
        }
    }

    public static function honeypotFilled(): bool
    {
        $value = $_POST['website_url'] ?? '';
        return is_string($value) && trim($value) !== '';
    }

    public static function rotateSession(): void
    {
        session_regenerate_id(true);
    }
}

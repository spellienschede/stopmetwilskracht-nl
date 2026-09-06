<?php
declare(strict_types=1);

/**
 * Globale helperfuncties voor views.
 */

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money_cents(int $cents, string $currency = 'EUR'): string
{
    if (strtoupper($currency) === 'EUR') {
        return '€ ' . number_format($cents / 100, 2, ',', '.');
    }
    return number_format($cents / 100, 2, ',', '.') . ' ' . $currency;
}

function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? $ip : '0.0.0.0';
}

function app_path(string $relative = ''): string
{
    $base = dirname(__DIR__);
    return $relative === '' ? $base : $base . '/' . ltrim($relative, '/');
}

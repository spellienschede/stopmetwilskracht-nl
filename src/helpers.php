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

function clean_utm(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $value = mb_substr($value, 0, 120);
    if (!preg_match('/^[A-Za-z0-9._:-]+$/', $value)) {
        return null;
    }
    return $value;
}

function capture_campaign_attribution(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $key) {
        $raw = $_GET[$key] ?? null;
        if (!is_string($raw)) {
            continue;
        }
        $clean = clean_utm($raw);
        if ($clean !== null) {
            $_SESSION[$key] = $clean;
        }
    }
}

/**
 * Hidden fields winnen van de sessie, zodat de landingspagina de advertentie vasthoudt.
 *
 * @param array<string,mixed> $input
 * @return array{utm_source:?string,utm_medium:?string,utm_campaign:?string,utm_content:?string}
 */
function campaign_attribution_from_request(array $input): array
{
    $out = [];
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'] as $key) {
        $fromInput = isset($input[$key]) && is_string($input[$key]) ? clean_utm($input[$key]) : null;
        $fromSession = (isset($_SESSION[$key]) && is_string($_SESSION[$key])) ? clean_utm($_SESSION[$key]) : null;
        $out[$key] = $fromInput ?? $fromSession;
    }
    return $out;
}

<?php
declare(strict_types=1);

namespace Grippartner;

/**
 * Meta Conversions API (server-side) for pixel event dedupe + reliable Purchase.
 */
final class MetaCapi
{
    public static function send(
        string $eventName,
        string $eventId,
        array $customData,
        array $userData = [],
        ?string $eventSourceUrl = null
    ): bool {
        $pixelId = Config::string('META_PIXEL_ID');
        $token = Config::string('META_CAPI_ACCESS_TOKEN');
        if ($pixelId === '' || $token === '') {
            return false;
        }

        $event = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => self::normalizeUserData($userData),
            'custom_data' => $customData,
        ];
        if ($eventSourceUrl) {
            $event['event_source_url'] = $eventSourceUrl;
        }

        $payload = ['data' => [$event]];
        $testCode = Config::string('META_CAPI_TEST_EVENT_CODE');
        if ($testCode !== '') {
            $payload['test_event_code'] = $testCode;
        }

        $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($pixelId) . '/events?access_token=' . rawurlencode($token);
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0 || $status >= 400) {
            Logger::error('Meta CAPI failed', [
                'event' => $eventName,
                'event_id' => $eventId,
                'status' => $status,
                'errno' => $errno,
                'body' => is_string($raw) ? substr($raw, 0, 500) : null,
            ]);
            return false;
        }
        return true;
    }

    /** @param array<string,mixed> $order */
    public static function sendPurchase(array $order): bool
    {
        $public = (string) ($order['public_order_number'] ?? '');
        if ($public === '') {
            return false;
        }
        $eventId = 'purchase:' . $public;
        return self::send(
            'Purchase',
            $eventId,
            [
                'currency' => (string) ($order['currency'] ?? 'EUR'),
                'value' => ((int) ($order['total_cents'] ?? 0)) / 100,
                'content_ids' => ['stop-met-wilskracht'],
                'content_type' => 'product',
                'content_name' => Config::string('BOOK_TITLE'),
                'num_items' => max(1, (int) ($order['quantity'] ?? 1)),
                'order_id' => $public,
            ],
            self::userDataFromOrder($order),
            Config::baseUrl() . '/betaalstatus.php?order=' . rawurlencode($public)
        );
    }

    /** @param array<string,mixed> $app Promo application row */
    public static function sendLead(array $app): bool
    {
        $public = (string) ($app['public_application_number'] ?? '');
        if ($public === '') {
            return false;
        }
        return self::send(
            'Lead',
            'lead:' . $public,
            [
                'content_name' => Config::string('BOOK_TITLE'),
                'content_category' => 'promo',
                'status' => 'submitted',
            ],
            self::userDataFromOrder($app),
            Config::baseUrl() . '/promo.php'
        );
    }

    /** @param array<string,mixed> $order */
    public static function sendInitiateCheckout(array $order): bool
    {
        $public = (string) ($order['public_order_number'] ?? '');
        if ($public === '') {
            return false;
        }
        return self::send(
            'InitiateCheckout',
            'ic:' . $public,
            [
                'currency' => (string) ($order['currency'] ?? 'EUR'),
                'value' => ((int) ($order['total_cents'] ?? 0)) / 100,
                'content_ids' => ['stop-met-wilskracht'],
                'content_type' => 'product',
                'content_name' => Config::string('BOOK_TITLE'),
                'num_items' => max(1, (int) ($order['quantity'] ?? 1)),
            ],
            self::userDataFromOrder($order),
            Config::baseUrl() . '/bestelling-bevestigen.php'
        );
    }

    /** @param array<string,mixed> $order @return array<string,string> */
    public static function userDataFromOrder(array $order): array
    {
        $data = [
            'em' => (string) ($order['email'] ?? ''),
            'fn' => (string) ($order['first_name'] ?? ''),
            'ln' => (string) ($order['last_name'] ?? ''),
            'ct' => (string) ($order['city'] ?? ''),
            'zp' => (string) ($order['postal_code'] ?? ''),
            'country' => strtolower((string) ($order['country'] ?? 'nl')),
        ];
        if (!empty($order['meta_fbp'])) {
            $data['fbp'] = (string) $order['meta_fbp'];
        } elseif (!empty($_SESSION['meta_fbp'])) {
            $data['fbp'] = (string) $_SESSION['meta_fbp'];
        }
        if (!empty($order['meta_fbc'])) {
            $data['fbc'] = (string) $order['meta_fbc'];
        } elseif (!empty($_SESSION['meta_fbc'])) {
            $data['fbc'] = (string) $_SESSION['meta_fbc'];
        }
        $ip = client_ip();
        if ($ip !== '') {
            $data['client_ip_address'] = $ip;
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (is_string($ua) && $ua !== '') {
            $data['client_user_agent'] = $ua;
        }
        return $data;
    }

    public static function captureBrowserIds(): void
    {
        $fbp = $_COOKIE['_fbp'] ?? '';
        $fbc = $_COOKIE['_fbc'] ?? '';
        if (is_string($fbp) && $fbp !== '') {
            $_SESSION['meta_fbp'] = $fbp;
        }
        if (is_string($fbc) && $fbc !== '') {
            $_SESSION['meta_fbc'] = $fbc;
        }
        // fbclid from ads → construct fbc if cookie missing
        $fbclid = $_GET['fbclid'] ?? '';
        if (is_string($fbclid) && $fbclid !== '' && empty($_SESSION['meta_fbc'])) {
            $_SESSION['meta_fbc'] = 'fb.1.' . time() . '.' . $fbclid;
        }
    }

    /** @param array<string,string> $userData */
    private static function normalizeUserData(array $userData): array
    {
        $out = [];
        foreach (['em', 'fn', 'ln', 'ct', 'zp', 'country'] as $key) {
            $val = trim((string) ($userData[$key] ?? ''));
            if ($val === '') {
                continue;
            }
            if ($key === 'em') {
                $val = strtolower($val);
            } elseif ($key === 'country') {
                $val = strtolower($val);
            } elseif ($key === 'zp') {
                $val = strtolower(str_replace(' ', '', $val));
            } else {
                $val = strtolower($val);
            }
            $out[$key] = hash('sha256', $val);
        }
        foreach (['fbp', 'fbc', 'client_ip_address', 'client_user_agent'] as $key) {
            $val = trim((string) ($userData[$key] ?? ''));
            if ($val !== '') {
                $out[$key] = $val;
            }
        }
        return $out;
    }
}

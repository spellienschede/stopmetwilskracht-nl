<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\Logger;
use Grippartner\OrderService;

require __DIR__ . '/src/bootstrap.php';

if (Config::isActionSite()) {
    http_response_code(404);
    header('X-Robots-Tag: noindex, nofollow');
    echo 'disabled';
    exit;
}

// Mollie stuurt id via POST
$paymentId = trim((string) ($_POST['id'] ?? ''));
if ($paymentId === '') {
    http_response_code(400);
    echo 'missing id';
    exit;
}

try {
    OrderService::processMollieWebhook($paymentId);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    Logger::error('Webhook failed', ['m' => $e->getMessage()]);
    http_response_code(500);
    echo 'error';
}

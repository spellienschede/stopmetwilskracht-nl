<?php
declare(strict_types=1);

use Grippartner\Logger;
use Grippartner\OrderService;

require __DIR__ . '/src/bootstrap.php';

// Black Friday-bestellingen worden op deze site afgerekend. De webhook
// hoort bij de betaling die hier is aangemaakt, niet bij grippartner.nl.

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

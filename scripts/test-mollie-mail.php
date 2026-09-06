<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Grippartner\Config;
use Grippartner\MollieClient;
use Grippartner\Mailer;

echo 'MOLLIE key: ' . (Config::string('MOLLIE_API_KEY') !== '' ? 'set (' . substr(Config::string('MOLLIE_API_KEY'), 0, 8) . '...)' : 'EMPTY') . PHP_EOL;
echo 'SMTP: ' . Config::string('SMTP_HOST') . ':' . Config::int('SMTP_PORT') . PHP_EOL;

try {
    $c = new MollieClient();
    $p = $c->createPayment([
        'amount' => ['currency' => 'EUR', 'value' => '0.01'],
        'description' => 'Grippartner test',
        'redirectUrl' => 'http://grippartner.nl.test/betaalstatus.php?order=TEST',
        'method' => 'ideal',
    ]);
    echo 'Mollie OK id=' . ($p['id'] ?? '?') . PHP_EOL;
    echo 'checkout=' . ($p['_links']['checkout']['href'] ?? 'none') . PHP_EOL;
} catch (Throwable $e) {
    echo 'Mollie FAIL: ' . $e->getMessage() . PHP_EOL;
}

$ok = Mailer::send(Mailer::adminEmail(), 'Grippartner testmail', '<p>Test</p>', 'Test');
echo 'Mail send: ' . ($ok ? 'OK' : 'FAIL') . ' -> ' . Mailer::adminEmail() . PHP_EOL;

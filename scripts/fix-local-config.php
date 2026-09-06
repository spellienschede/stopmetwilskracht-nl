<?php
$path = 'c:/laragon/www/grippartner.nl/config.local.php';
$content = <<<'PHP'
<?php
declare(strict_types=1);

return [
    'APP_BASE_URL' => 'http://grippartner.nl.test',
    'APP_ENV' => 'local',
    'APP_DEBUG' => true,
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'grippartner',
    'DB_USER' => 'grip',
    'DB_PASS' => 'hbY814eLaJlhbh',
    'MOLLIE_API_KEY' => '',
    'MAIL_FROM_ADDRESS' => 'info@kornepot.nl',
    'MAIL_FROM_NAME' => 'Korne Pot / Grippartner',
    'ADMIN_NOTIFICATION_EMAIL' => 'info@kornepot.nl',
];
PHP;
file_put_contents($path, $content);
echo "ok\n";

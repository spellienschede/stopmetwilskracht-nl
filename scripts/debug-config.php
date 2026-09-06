<?php
declare(strict_types=1);

echo "a\n";
require __DIR__ . '/../src/helpers.php';
echo "b\n";
require __DIR__ . '/../src/Config.php';
echo "c\n";

$local = dirname(__DIR__) . '/config.local.php';
echo "local=$local readable=" . (is_readable($local) ? '1' : '0') . "\n";
echo "d\n";
$values = require $local;
echo "e keys=" . count($values) . "\n";
echo "f\n";
Grippartner\Config::load();
echo "g host=" . Grippartner\Config::string('DB_HOST') . "\n";

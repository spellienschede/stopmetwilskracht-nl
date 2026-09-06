<?php
declare(strict_types=1);

/**
 * Voer alle SQL-migraties in migrations/ uit (alfabetisch).
 * Gebruik: php scripts/migrate.php
 */

$root = dirname(__DIR__);
$local = is_readable($root . '/config.local.php') ? require $root . '/config.local.php' : [];
$host = $local['DB_HOST'] ?? '127.0.0.1';
$name = $local['DB_NAME'] ?? 'grippartner';
$user = $local['DB_USER'] ?? 'grip';
$pass = $local['DB_PASS'] ?? '';

$pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$files = glob($root . '/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
if ($files === []) {
    fwrite(STDERR, "Geen migraties gevonden\n");
    exit(1);
}

foreach ($files as $sqlFile) {
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        fwrite(STDERR, "Niet leesbaar: {$sqlFile}\n");
        exit(1);
    }
    $pdo->exec($sql);
    echo 'OK ' . basename($sqlFile) . "\n";
}

echo "Migratie klaar op {$name}@{$host}\n";

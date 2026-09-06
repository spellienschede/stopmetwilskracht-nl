<?php
foreach (['127.0.0.1', 'localhost'] as $host) {
    $start = microtime(true);
    try {
        $p = new PDO(
            "mysql:host={$host};dbname=grippartner;charset=utf8mb4",
            'grip',
            'hbY814eLaJlhbh',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        echo "$host OK in " . round((microtime(true) - $start) * 1000) . "ms\n";
    } catch (Throwable $e) {
        echo "$host FAIL: " . $e->getMessage() . "\n";
    }
}

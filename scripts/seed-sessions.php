<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Grippartner\SessionService;

SessionService::ensureSchema();
$s = SessionService::findBySlug('3-geheimen');
if (!$s) {
    fwrite(STDERR, "MISSING\n");
    exit(1);
}
echo 'OK id=' . $s['id'] . ' title=' . $s['title'] . ' starts=' . $s['starts_at'] . PHP_EOL;
echo 'count=' . SessionService::countSignups((int) $s['id']) . PHP_EOL;

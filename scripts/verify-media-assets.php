<?php
require dirname(__DIR__) . '/src/bootstrap.php';
foreach (['author', 'illustrations', 'book-cover'] as $cat) {
    echo "== $cat ==\n";
    foreach (Grippartner\MediaKit::assetsByCategory($cat) as $x) {
        echo ($x['downloadable'] ? 'DL' : '--') . ' ' . $x['id'] . ' ' . ($x['path'] ?? '') . "\n";
    }
}
echo 'AUTHOR_PHOTO_PATH=' . Grippartner\Config::string('AUTHOR_PHOTO_PATH') . "\n";

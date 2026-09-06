<?php
$files = glob('c:/laragon/www/grippartner.nl/assets/media/press/{author,media}/*.{png,jpg,jpeg}', GLOB_BRACE) ?: [];
sort($files);
foreach ($files as $f) {
    $i = @getimagesize($f);
    if (!$i) {
        echo "MISS " . basename($f) . PHP_EOL;
        continue;
    }
    $kb = (int) round(filesize($f) / 1024);
    echo sprintf("%4dx%-4d  %5d KB  %s\n", $i[0], $i[1], $kb, basename($f));
}

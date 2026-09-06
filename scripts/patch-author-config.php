<?php
$p = dirname(__DIR__) . '/config.local.php';
$c = require $p;
if (!is_array($c)) {
    exit(1);
}
$c['AUTHOR_PHOTO_PATH'] = '/assets/img/korne-pot-author.jpg';
$c['AUTHOR_PHOTO_PORTRAIT'] = '/assets/media/press/author/korne-pot-portrait.jpg';
$c['AUTHOR_PHOTO_LANDSCAPE'] = '/assets/media/press/author/korne-pot-landscape.jpg';
$c['AUTHOR_PHOTO_SQUARE'] = '/assets/media/press/author/korne-pot-square.jpg';
// Cover bewust nog niet als definitief
unset($c['BOOK_COVER_HIGH_RES'], $c['BOOK_COVER_WEB']);
file_put_contents($p, "<?php\n\nreturn " . var_export($c, true) . ";\n");
echo "config author photos gezet\n";

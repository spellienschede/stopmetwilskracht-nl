<?php
declare(strict_types=1);

use Grippartner\Config;

require __DIR__ . '/src/bootstrap.php';

$cover = Config::string('BOOK_COVER_PATH');
$productPhoto = Config::string('BOOK_PRODUCT_PATH');
$authorPhoto = Config::string('AUTHOR_PHOTO_PATH');
$cta = Config::orderCta();
$verb = Config::orderVerb();
$price = Config::priceFormatted();
$release = Config::formatReleaseDate();
$presaleEnd = Config::formatPresaleEndDate();
$presaleActive = Config::isPresaleActive();
$released = Config::isReleased();

$title = Config::string('BOOK_TITLE') . ' – boek van Korne Pot';
$description = $presaleActive
    ? (Config::string('BOOK_TITLE') . ' — herken je dit patroon? Pre-order met € 10 korting + 2 bonussen tot ' . Config::formatPresaleEndDateShort() . '.')
    : (Config::string('BOOK_TITLE') . ' van Korne Pot. ' . $price . ' incl. btw en verzending.');
$canonical = Config::baseUrl() . '/';
$metaPixelEvents = [[
    'ViewContent',
    [
        'content_name' => Config::string('BOOK_TITLE'),
        'content_ids' => ['stop-met-wilskracht'],
        'content_type' => 'product',
        'value' => Config::currentPriceCents() / 100,
        'currency' => Config::string('BOOK_CURRENCY'),
    ],
]];

ob_start();
require __DIR__ . '/templates/home.php';
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

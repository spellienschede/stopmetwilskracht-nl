<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MediaKit;
use Grippartner\MediaRequestService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\Validator;

require __DIR__ . '/src/bootstrap.php';

$errors = [];
$success = false;
$publicNr = '';
$data = Validator::normalizeMediaRequestInput([]);

if (is_post()) {
    Security::requireCsrf();
    if (Security::honeypotFilled()) {
        $success = true;
    } elseif (RateLimiter::tooMany('media:' . client_ip(), 8, 3600)) {
        $errors['form'] = 'Te veel aanvragen. Probeer later opnieuw.';
    } else {
        $data = Validator::normalizeMediaRequestInput($_POST);
        $errors = Validator::mediaRequest($_POST);
        if ($errors === []) {
            try {
                $row = MediaRequestService::create($data);
                $success = true;
                $publicNr = (string) ($row['public_request_number'] ?? '');
            } catch (Throwable $e) {
                \Grippartner\Logger::error('Media request failed', ['m' => $e->getMessage()]);
                $errors['form'] = 'Aanvraag kon niet worden opgeslagen. Probeer het opnieuw.';
            }
        }
    }
}

$kitZip = MediaKit::findAsset('kit-zip');
$coverWeb = MediaKit::findAsset('cover-web');
$coverHigh = MediaKit::findAsset('cover-high');
$authorAssets = MediaKit::assetsByCategory('author');
$authorReady = array_values(array_filter($authorAssets, static fn (array $a): bool => !empty($a['downloadable'])));

$title = 'Mediakit Grippartner – boekinformatie, persbeelden en interviews';
$description = 'Download boekinformatie en persmateriaal over Grippartner van Korne Pot, met biografie, interviewonderwerpen, kerngegevens en contactinformatie.';
$canonical = Config::baseUrl() . '/media';
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $title,
    'description' => $description,
    'url' => $canonical,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => 'Grippartner',
        'url' => Config::baseUrl() . '/',
    ],
    'about' => [
        '@type' => 'Book',
        'name' => Config::string('BOOK_TITLE'),
        'author' => ['@type' => 'Person', 'name' => Config::string('BOOK_AUTHOR')],
        'datePublished' => Config::string('BOOK_RELEASE_DATE'),
    ],
];

ob_start();
require __DIR__ . '/templates/media.php';
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

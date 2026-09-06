<?php
declare(strict_types=1);

/**
 * Kopieer naar config.local.php (staat in .gitignore).
 * Actiesite: geen eigen Mollie/checkout — bestellen via Grip.
 */
return [
    'APP_BASE_URL' => 'https://www.stopmetwilskracht.nl',
    'APP_ENV' => 'production',
    'APP_DEBUG' => false,

    'SITE_MODE' => 'action',
    'CANONICAL_ORIGIN' => 'https://www.grippartner.nl',
    'ORDER_URL' => 'https://www.grippartner.nl/bestellen.php',

    // Geen productiedatabase/Mollie nodig op de actiesite
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'stopmetwilskracht',
    'DB_USER' => '',
    'DB_PASS' => '',
    'MOLLIE_API_KEY' => '',
    'MOLLIE_WEBHOOK_URL' => '',

    'ADMIN_NOTIFICATION_EMAIL' => 'info@kornepot.nl',
    'MAIL_BCC_ADMIN' => true,
    'SMTP_HOST' => '',
    'SMTP_PORT' => 465,
    'SMTP_USER' => '',
    'SMTP_PASS' => '',
    'SMTP_SECURE' => 'ssl',

    'ADMIN_USERNAME' => 'grip',
    'ADMIN_PASSWORD' => '',

    'LEGAL_BUSINESS_NAME' => 'Het 2e kwadrant',
    'LEGAL_ADDRESS' => 'Haaksbergerstraat 709, 7545 PH Enschede',
    'LEGAL_KVK' => '99420694',
    'LEGAL_BTW' => 'NL868983019B01',

    'BOOK_COVER_PATH' => '/assets/img/cover.png',
    'MEDIA_CONTACT_EMAIL' => 'info@kornepot.nl',
    'META_PIXEL_ID' => '',
    'GA_MEASUREMENT_ID' => '',
];

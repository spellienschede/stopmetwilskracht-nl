<?php
declare(strict_types=1);

namespace Grippartner;

final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        date_default_timezone_set('Europe/Amsterdam');

        $defaults = [
            // stopmetwilskracht branch: actiesite defaults (override in config.local.php if needed)
            'APP_BASE_URL' => 'https://www.stopmetwilskracht.nl',
            // action = noindex + canonical naar CANONICAL_ORIGIN; ORDER_URL voor CTAs
            'SITE_MODE' => 'action',
            'CANONICAL_ORIGIN' => 'https://www.grippartner.nl',
            'ORDER_URL' => 'https://www.grippartner.nl/bestellen.php',
            'APP_ENV' => 'production',
            'APP_DEBUG' => false,
            'DB_HOST' => '127.0.0.1',
            'DB_NAME' => 'grippartner',
            'DB_USER' => 'grip',
            'DB_PASS' => '',
            'BOOK_TITLE' => 'Stop met wilskracht',
            'BOOK_SUBTITLE' => 'Waarom jij niet zoekt naar een grippartner, maar er wel één nodig hebt',
            'BOOK_HOOK' => 'Weet jij wat je allemaal NIET ziet aan jezelf?',
            'BOOK_AUTHOR' => 'Korne Pot',
            'BOOK_PRICE_CENTS' => 4900,
            'BOOK_PRESALE_PRICE_CENTS' => 3900,
            'BOOK_CURRENCY' => 'EUR',
            'BOOK_PRESALE_END_DATE' => '2026-09-14',
            'BOOK_RELEASE_DATE' => '2026-10-03',
            'BOOK_PRESENTATION_DATE' => '2026-10-05',
            'BOOK_PRESENTATION_TIME' => '19:30',
            'BOOK_ISBN' => '',
            'BOOK_PAGE_COUNT' => '',
            'BOOK_FORMAT' => '',
            'BOOK_PUBLISHER' => '',
            'DEFAULT_COUNTRY' => 'NL',
            'TIMEZONE' => 'Europe/Amsterdam',
            'MOLLIE_API_KEY' => '',
            'MOLLIE_WEBHOOK_URL' => '',
            'MAIL_FROM_ADDRESS' => 'info@kornepot.nl',
            'MAIL_FROM_NAME' => 'Korne Pot',
            'ADMIN_NOTIFICATION_EMAIL' => 'info@kornepot.nl',
            'ADMIN_USERNAME' => 'grip',
            'ADMIN_PASSWORD' => '',
            'MEDIA_CONTACT_EMAIL' => 'info@kornepot.nl',
            'MEDIA_KIT_ZIP' => '',
            'BOOK_COVER_HIGH_RES' => '',
            'BOOK_COVER_WEB' => '',
            'AUTHOR_PHOTO_PORTRAIT' => '',
            'AUTHOR_PHOTO_LANDSCAPE' => '',
            'AUTHOR_PHOTO_SQUARE' => '',
            'SMTP_HOST' => '',
            'SMTP_PORT' => 465,
            'SMTP_USER' => '',
            'SMTP_PASS' => '',
            'SMTP_SECURE' => 'ssl',
            'LEGAL_BUSINESS_NAME' => 'Het 2e kwadrant',
            'LEGAL_ADDRESS' => 'Haaksbergerstraat 709, 7545 PH Enschede',
            'LEGAL_KVK' => '99420694',
            'LEGAL_BTW' => 'NL868983019B01',
            'LEGAL_ISBN' => '',
            'BOOK_COVER_PATH' => '/assets/img/cover.png',
            'BOOK_PRODUCT_PATH' => '/assets/img/book-product.png',
            'AUTHOR_PHOTO_PATH' => '',
            'GA_MEASUREMENT_ID' => 'G-D2TE99HQZQ',
            'META_PIXEL_ID' => '',
            'META_CAPI_ACCESS_TOKEN' => '',
            'META_CAPI_TEST_EVENT_CODE' => '',
        ];

        self::$values = $defaults;

        foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $const) {
            if (defined($const)) {
                self::$values[$const] = constant($const);
            }
        }

        self::loadEnvFile(dirname(__DIR__) . '/.env');

        $local = dirname(__DIR__) . '/config.local.php';
        if (is_readable($local)) {
            /** @var mixed $localValues */
            $localValues = include $local; // include i.p.v. require: mag opnieuw
            if (is_array($localValues)) {
                foreach ($localValues as $key => $value) {
                    self::$values[(string) $key] = $value;
                }
            }
        }

        // Optional runtime override for CAPI token (written via admin tool).
        $capiFile = dirname(__DIR__) . '/storage/meta_capi_token.php';
        if (is_readable($capiFile)) {
            /** @var mixed $capiValues */
            $capiValues = include $capiFile;
            if (is_array($capiValues)) {
                foreach (['META_CAPI_ACCESS_TOKEN', 'META_CAPI_TEST_EVENT_CODE'] as $key) {
                    if (!empty($capiValues[$key]) && is_string($capiValues[$key])) {
                        self::$values[$key] = $capiValues[$key];
                    }
                }
            }
        }

        $tz = (string) (self::$values['TIMEZONE'] ?? 'Europe/Amsterdam');
        date_default_timezone_set($tz);
        self::$loaded = true;
    }

    private static function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (!self::$loaded) {
            self::load();
        }
        return self::$values[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function baseUrl(): string
    {
        return rtrim(self::string('APP_BASE_URL', 'https://www.grippartner.nl'), '/');
    }

    public static function isActionSite(): bool
    {
        return strtolower(self::string('SITE_MODE', 'full')) === 'action'
            || self::bool('SEO_NOINDEX', false);
    }

    public static function canonicalOrigin(): string
    {
        $origin = rtrim(self::string('CANONICAL_ORIGIN'), '/');
        if ($origin !== '') {
            return $origin;
        }
        return self::isActionSite() ? 'https://www.grippartner.nl' : self::baseUrl();
    }

    /** Absolute order/checkout URL (action site → Grip bestellen). */
    public static function orderUrl(): string
    {
        $url = trim(self::string('ORDER_URL'));
        if ($url !== '') {
            return $url;
        }
        if (self::isActionSite()) {
            return 'https://www.grippartner.nl/bestellen.php';
        }
        return self::baseUrl() . '/bestellen.php';
    }

    /** Canonical URL for the current request path on action sites (maps to Grip). */
    public static function canonicalForPath(?string $path = null): string
    {
        if ($path === null) {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        }
        $path = '/' . ltrim($path, '/');
        if ($path === '/index.php') {
            $path = '/';
        }
        $origin = self::isActionSite() ? self::canonicalOrigin() : self::baseUrl();
        if ($path === '/' || $path === '') {
            return $origin . '/';
        }
        return $origin . $path;
    }

    public static function isPresaleActive(?\DateTimeImmutable $now = null): bool
    {
        $end = self::presaleEndDateImmutable();
        if (!$end) {
            return false;
        }
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(self::string('TIMEZONE', 'Europe/Amsterdam')));
        return $now < $end->setTime(23, 59, 59);
    }

    public static function isReleased(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(self::string('TIMEZONE', 'Europe/Amsterdam')));
        $release = self::releaseDateImmutable();
        if (!$release) {
            return false;
        }
        return $now >= $release;
    }

    public static function orderVerb(): string
    {
        return self::isPresaleActive() ? 'Pre-order' : 'Bestel';
    }

    public static function orderCta(): string
    {
        $title = self::string('BOOK_TITLE');
        return self::isPresaleActive() ? 'Pre-order ' . $title : 'Bestel ' . $title;
    }

    public static function availabilityText(): string
    {
        $release = self::formatReleaseDate();
        if (self::isReleased()) {
            return 'Beschikbaar sinds ' . $release . '. Verzending zo snel mogelijk.';
        }
        if (self::isPresaleActive()) {
            return 'Pre-order nu open tot ' . self::formatPresaleEndDate()
                . ' · verwachte verzending rond ' . $release;
        }
        return 'Bestel nu · verwachte verzending rond ' . $release;
    }

    public static function presaleEndDateImmutable(): ?\DateTimeImmutable
    {
        return self::dateFromConfig('BOOK_PRESALE_END_DATE', '2026-09-14');
    }

    public static function releaseDateImmutable(): ?\DateTimeImmutable
    {
        return self::dateFromConfig('BOOK_RELEASE_DATE', '2026-10-03');
    }

    public static function presentationDateImmutable(): ?\DateTimeImmutable
    {
        return self::dateFromConfig('BOOK_PRESENTATION_DATE', '2026-10-05');
    }

    /** Online boekpresentatie: datum + tijd (Europe/Amsterdam). */
    public static function presentationDateTimeImmutable(): ?\DateTimeImmutable
    {
        $date = self::presentationDateImmutable();
        if (!$date) {
            return null;
        }
        $time = self::string('BOOK_PRESENTATION_TIME', '19:30');
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            $m = [0, 19, 30];
        }
        return $date->setTime((int) $m[1], (int) $m[2]);
    }

    public static function isPresentationOpen(?\DateTimeImmutable $now = null): bool
    {
        $when = self::presentationDateTimeImmutable();
        if (!$when) {
            return false;
        }
        $tz = new \DateTimeZone(self::string('TIMEZONE', 'Europe/Amsterdam'));
        $now = ($now ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz);
        return $now < $when;
    }

    public static function formatPresentationDate(): string
    {
        return self::formatDutchDate(self::presentationDateImmutable(), true);
    }

    public static function formatPresentationDateShort(): string
    {
        return self::formatDutchDate(self::presentationDateImmutable(), false);
    }

    public static function formatPresentationTime(): string
    {
        return self::string('BOOK_PRESENTATION_TIME', '19:30');
    }

    /** Bijv. "maandag 5 oktober 2026 om 19:30". */
    public static function formatPresentationLabel(): string
    {
        $date = self::formatPresentationDate();
        $time = self::formatPresentationTime();
        if ($date === '') {
            return '';
        }
        return $date . ' om ' . $time;
    }

    /** Dagen tot einde pre-order (0 op de laatste dag, negatief erna). */
    public static function daysUntilPresaleEnd(?\DateTimeImmutable $now = null): ?int
    {
        return self::daysUntil(self::presaleEndDateImmutable(), $now);
    }

    /** Dagen tot release (0 op de releasedag, negatief erna). */
    public static function daysUntilRelease(?\DateTimeImmutable $now = null): ?int
    {
        return self::daysUntil(self::releaseDateImmutable(), $now);
    }

    public static function urgencyBarText(): string
    {
        $short = self::formatPresaleEndDateShort();
        $days = self::daysUntilPresaleEnd();
        $countdown = '';
        if ($days !== null && $days > 0) {
            $countdown = ' · nog ' . $days . ' ' . ($days === 1 ? 'dag' : 'dagen');
        } elseif ($days === 0) {
            $countdown = ' · laatste dag';
        }
        return 'Nu pre-orderen · tot ' . $short . $countdown . ' · € 10 korting + 2 bonussen';
    }

    public static function countdownLabel(): string
    {
        $days = self::daysUntilPresaleEnd();
        if ($days === null) {
            return '';
        }
        if ($days > 1) {
            return 'Nog ' . $days . ' dagen tot de deadline';
        }
        if ($days === 1) {
            return 'Nog 1 dag tot de deadline';
        }
        if ($days === 0) {
            return 'Laatste dag om te pre-orderen';
        }
        return '';
    }

    public static function formatPresaleEndDate(): string
    {
        return self::formatDutchDate(self::presaleEndDateImmutable(), true);
    }

    /** Korte datum zonder weekdag, voor pre-orderdeadline. */
    public static function formatPresaleEndDateShort(): string
    {
        return self::formatDutchDate(self::presaleEndDateImmutable(), false);
    }

    public static function formatReleaseDate(): string
    {
        return self::formatDutchDate(self::releaseDateImmutable(), true);
    }

    /** Korte datum zonder weekdag, voor publicatie. */
    public static function formatReleaseDateShort(): string
    {
        return self::formatDutchDate(self::releaseDateImmutable(), false);
    }

    /**
     * Pre-orderbonussen (alleen bij pre-order vóór BOOK_PRESALE_END_DATE; niet bij gratis promo).
     * Prijsvoordeel (€ 10) staat apart van deze bonussen.
     *
     * @return list<array{title: string, text: string}>
     */
    public static function presaleBonuses(): array
    {
        return [
            [
                'title' => 'Bonus 1 — Gesigneerde editie',
                'text' => 'Geen gewone versie, maar een gesigneerd exemplaar met persoonlijke boodschap.',
            ],
            [
                'title' => 'Bonus 2 — Online omgeving',
                'text' => 'Toegang tot een online omgeving met alle nuttige tools bij het boek.',
            ],
        ];
    }

    public static function presaleBonusCount(): int
    {
        return count(self::presaleBonuses());
    }

    /**
     * Lezersreviews voor de homepage.
     *
     * @return list<array{name: string, place: string, quote: string}>
     */
    public static function bookReviews(): array
    {
        return [
            [
                'name' => 'Rink',
                'place' => 'Hardegarijp',
                'quote' => 'Ik heb dit boek met veel plezier gelezen; het bleef me tot het einde boeien. Korne combineert een overtuigende methode met persoonlijke en soms kwetsbare verhalen. De praktische uitleg zorgt ervoor dat je precies begrijpt hoe je zelf kunt beginnen.',
            ],
            [
                'name' => 'Moniek',
                'place' => 'Enschede',
                'quote' => 'Dit boek laat op een open en persoonlijke manier zien wat een grippartner kan betekenen, juist wanneer het leven over veel meer gaat dan werk en doelen halen. Het helpt je uitzoomen en opnieuw aandacht geven aan de mensen en onderwerpen die werkelijk belangrijk voor je zijn. Mooie methode om gewoontes echt vol te houden.',
            ],
            [
                'name' => 'Derk',
                'place' => 'Barneveld',
                'quote' => 'Stop met wilskracht is leuk geschreven en leest lekker weg. Vooral het gedeelte over nieuwsgierigheid vond ik prachtig. Goede vragen stellen zonder direct iets voor een ander in te vullen, is niet alleen belangrijk voor grippartners, maar een mooie les voor het leven.',
            ],
            [
                'name' => 'Paul',
                'place' => 'Amstelveen',
                'quote' => 'Als grippartner van Korne sinds 2020 herken ik de verhalen en situaties in dit boek. Het laat eerlijk zien hoe één vast gesprek, een gedeeld document en goede vragen je helpen patronen te herkennen en aandacht te blijven geven aan wat je werkelijk belangrijk vindt.',
            ],
        ];
    }

    /** Korte samenvatting van pre-orderprijs + bonussen + deadline. */
    public static function presaleBonusText(): string
    {
        $short = self::formatPresaleEndDateShort();
        return 'Pre-order nu open tot ' . $short . ': je betaalt '
            . self::priceFormatted() . ' i.p.v. ' . self::regularPriceFormatted()
            . ' (€ 10 korting) én krijgt 2 bonussen — (1) een gesigneerd exemplaar met persoonlijke boodschap en (2) toegang tot een online omgeving met alle nuttige tools bij het boek.';
    }

    /** Actuele verkoopprijs in centen (pre-orderkorting vóór releasedatum). */
    public static function currentPriceCents(?\DateTimeImmutable $now = null): int
    {
        if (self::isPresaleActive($now)) {
            return self::int('BOOK_PRESALE_PRICE_CENTS', 3900);
        }
        return self::int('BOOK_PRICE_CENTS', 4900);
    }

    private static function dateFromConfig(string $key, string $default): ?\DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            self::string($key, $default),
            new \DateTimeZone(self::string('TIMEZONE', 'Europe/Amsterdam'))
        );
        return $dt ?: null;
    }

    private static function daysUntil(?\DateTimeImmutable $target, ?\DateTimeImmutable $now = null): ?int
    {
        if (!$target) {
            return null;
        }
        $tz = new \DateTimeZone(self::string('TIMEZONE', 'Europe/Amsterdam'));
        $now = ($now ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz)->setTime(0, 0);
        $target = $target->setTime(0, 0);
        return (int) $now->diff($target)->format('%r%a');
    }

    private static function formatDutchDate(?\DateTimeImmutable $dt, bool $withWeekday): string
    {
        if (!$dt) {
            return '';
        }
        $months = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december',
        ];
        $short = (int) $dt->format('j') . ' ' . $months[(int) $dt->format('n')] . ' ' . $dt->format('Y');
        if (!$withWeekday) {
            return $short;
        }
        $days = [
            1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag',
            5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag',
        ];
        return $days[(int) $dt->format('N')] . ' ' . $short;
    }

    public static function regularPriceCents(): int
    {
        return self::int('BOOK_PRICE_CENTS', 4900);
    }

    public static function formatCents(int $cents): string
    {
        return '€ ' . number_format($cents / 100, 2, ',', '.') . ' Euro';
    }

    public static function priceFormatted(): string
    {
        return self::formatCents(self::currentPriceCents());
    }

    public static function regularPriceFormatted(): string
    {
        return self::formatCents(self::regularPriceCents());
    }
}

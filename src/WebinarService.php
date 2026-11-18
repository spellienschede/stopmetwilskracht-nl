<?php
declare(strict_types=1);

namespace Grippartner;

/**
 * Inschrijvingen voor het webinar op /webinar.
 * Zonder WEBINAR_JOIN_URL in config.local.php gaat de deelnamelink later per mail.
 */
final class WebinarService
{
    public const EDITION = '2026-12-01';
    public const PIXEL_ID = '882449387955199';
    public const START = '2026-12-01T19:30:00';
    public const DURATION_MINUTES = 30;
    public const TZ = 'Europe/Amsterdam';
    public const TITLE = 'Waarom je afspraken met anderen wél nakomt, maar die met jezelf niet';

    private static bool $schemaChecked = false;

    public static function when(): string
    {
        return 'dinsdag 1 december 2026, 19:30–20:00';
    }

    public static function badge(): string
    {
        return '1 december · 19:30-20:00';
    }

    public static function captionDate(): string
    {
        return 'Dinsdag 1 december';
    }

    public static function captionTime(): string
    {
        return '19:30-20:00';
    }

    public static function joinUrl(): string
    {
        $url = trim(Config::string('WEBINAR_JOIN_URL'));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            return '';
        }

        return $url;
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS webinar_signups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                first_name VARCHAR(80) NOT NULL,
                token CHAR(32) NOT NULL,
                mail_ok TINYINT(1) NOT NULL DEFAULT 0,
                edition VARCHAR(32) NOT NULL,
                event_id VARCHAR(36) NULL,
                fbclid VARCHAR(200) NULL,
                utm_source VARCHAR(120) NULL,
                utm_medium VARCHAR(120) NULL,
                utm_campaign VARCHAR(120) NULL,
                utm_content VARCHAR(120) NULL,
                utm_term VARCHAR(120) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_webinar_token (token),
                UNIQUE KEY uq_webinar_email_edition (email, edition)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::ensureAttributionColumns();
        self::$schemaChecked = true;
    }

    private static function ensureAttributionColumns(): void
    {
        $pdo = Database::pdo();
        $have = [];
        foreach ($pdo->query('SHOW COLUMNS FROM webinar_signups') as $column) {
            if (is_array($column)) {
                $have[] = (string) ($column['Field'] ?? '');
            }
        }
        $add = [
            'event_id' => 'VARCHAR(36) NULL',
            'fbclid' => 'VARCHAR(200) NULL',
            'utm_source' => 'VARCHAR(120) NULL',
            'utm_medium' => 'VARCHAR(120) NULL',
            'utm_campaign' => 'VARCHAR(120) NULL',
            'utm_content' => 'VARCHAR(120) NULL',
            'utm_term' => 'VARCHAR(120) NULL',
        ];
        foreach ($add as $name => $definition) {
            if (!in_array($name, $have, true)) {
                $pdo->exec('ALTER TABLE webinar_signups ADD COLUMN ' . $name . ' ' . $definition);
            }
        }
    }

    /** @param array<string,string> $attribution */
    public static function rememberVisit(array $query): void
    {
        $fbclid = self::cleanTracking(self::queryValue($query, 'fbclid'), 200);
        if ($fbclid !== '') {
            $_SESSION['webinar_fbclid'] = $fbclid;
            self::ensureFbcCookie($fbclid);
        }
        $sawUtm = false;
        foreach (self::utmKeys() as $key) {
            $value = self::cleanTracking(self::queryValue($query, $key), 120);
            if ($value !== '') {
                $_SESSION['webinar_' . $key] = $value;
                $sawUtm = true;
            }
        }
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $url = rtrim(Config::baseUrl(), '/') . '/webinar';
        if (is_string($qs) && $qs !== '') {
            $url .= '?' . substr($qs, 0, 400);
        }
        if ($fbclid !== '' || $sawUtm || empty($_SESSION['webinar_source_url'])) {
            $_SESSION['webinar_source_url'] = $url;
        }
    }

    /** @return array<string,string> */
    public static function attributionForForm(): array
    {
        $out = ['fbclid' => (string) ($_SESSION['webinar_fbclid'] ?? '')];
        foreach (self::utmKeys() as $key) {
            $out[$key] = (string) ($_SESSION['webinar_' . $key] ?? '');
        }
        return $out;
    }

    /** @param array<string,string> $post @return array<string,string> */
    public static function attributionFromPost(array $post): array
    {
        $fbclid = self::cleanTracking(self::queryValue($post, 'fbclid'), 200);
        if ($fbclid === '') {
            $fbclid = (string) ($_SESSION['webinar_fbclid'] ?? '');
        }
        $out = ['fbclid' => $fbclid];
        foreach (self::utmKeys() as $key) {
            $value = self::cleanTracking(self::queryValue($post, $key), 120);
            if ($value === '') {
                $value = (string) ($_SESSION['webinar_' . $key] ?? '');
            }
            $out[$key] = $value;
        }
        $fbp = self::cleanTracking((string) ($_COOKIE['_fbp'] ?? ''), 200);
        $fbc = self::cleanTracking((string) ($_COOKIE['_fbc'] ?? ''), 400);
        if ($fbc === '' && $fbclid !== '') {
            $fbc = 'fb.1.' . (string) (int) round(microtime(true) * 1000) . '.' . $fbclid;
        }
        $out['fbp'] = $fbp;
        $out['fbc'] = $fbc;
        $source = (string) ($_SESSION['webinar_source_url'] ?? '');
        $out['source_url'] = $source !== '' ? $source : (rtrim(Config::baseUrl(), '/') . '/webinar');
        return $out;
    }

    /**
     * @param array<string,string> $attribution
     * @return array{ok:true,token:string,mail_ok:bool,existing:bool,event_id:string}|array{ok:false,error:string}
     */
    public static function register(string $first, string $email, string $ip, array $attribution = []): array
    {
        $nameError = self::validateName($first);
        if ($nameError !== null) {
            return ['ok' => false, 'error' => $nameError];
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            return ['ok' => false, 'error' => 'Vul een geldig e-mailadres in.'];
        }
        if (RateLimiter::tooMany('webinar:' . $ip, 10, 3600)) {
            return ['ok' => false, 'error' => 'Te veel pogingen. Probeer het over een uur opnieuw.'];
        }

        self::ensureSchema();
        $pdo = Database::pdo();
        $existing = self::findByEmail($email);
        if ($existing !== null) {
            $existing['first_name'] = $first;
            $mailOk = self::sendParticipantMail($existing);
            $pdo->prepare('UPDATE webinar_signups SET first_name = ?, mail_ok = ? WHERE id = ?')
                ->execute([$first, $mailOk ? 1 : 0, (int) $existing['id']]);

            return [
                'ok' => true,
                'token' => (string) $existing['token'],
                'mail_ok' => $mailOk,
                'existing' => true,
                'event_id' => (string) ($existing['event_id'] ?? ''),
            ];
        }

        $token = bin2hex(random_bytes(16));
        $eventId = self::uuid();
        try {
            $pdo->prepare(
                'INSERT INTO webinar_signups (
                    email, first_name, token, mail_ok, edition, event_id, fbclid,
                    utm_source, utm_medium, utm_campaign, utm_content, utm_term
                ) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $email,
                $first,
                $token,
                self::EDITION,
                $eventId,
                self::nullable($attribution['fbclid'] ?? ''),
                self::nullable($attribution['utm_source'] ?? ''),
                self::nullable($attribution['utm_medium'] ?? ''),
                self::nullable($attribution['utm_campaign'] ?? ''),
                self::nullable($attribution['utm_content'] ?? ''),
                self::nullable($attribution['utm_term'] ?? ''),
            ]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                $again = self::findByEmail($email);
                if ($again !== null) {
                    return [
                        'ok' => true,
                    'token' => (string) $again['token'],
                    'mail_ok' => (int) $again['mail_ok'] === 1,
                    'existing' => true,
                    'event_id' => (string) ($again['event_id'] ?? ''),
                ];
                }
            }
            Logger::error('Webinar-inschrijving mislukt', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => 'Inschrijven lukt op dit moment niet. Probeer het over een paar minuten opnieuw.'];
        }

        $row = self::findByEmail($email);
        if ($row === null) {
            return ['ok' => false, 'error' => 'Inschrijven lukt op dit moment niet. Probeer het over een paar minuten opnieuw.'];
        }

        $mailOk = self::sendParticipantMail($row);
        $pdo->prepare('UPDATE webinar_signups SET mail_ok = ? WHERE id = ?')->execute([$mailOk ? 1 : 0, (int) $row['id']]);
        self::notifyAdmin($row);
        MetaCapi::sendWebinarRegistration($eventId, [
            'em' => $email,
            'fn' => $first,
            'fbp' => (string) ($attribution['fbp'] ?? ''),
            'fbc' => (string) ($attribution['fbc'] ?? ''),
            'client_ip_address' => $ip,
            'client_user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ], (string) ($attribution['source_url'] ?? (rtrim(Config::baseUrl(), '/') . '/webinar')));

        return [
            'ok' => true,
            'token' => (string) $row['token'],
            'mail_ok' => $mailOk,
            'existing' => false,
            'event_id' => $eventId,
        ];
    }

    /** @return array<string,mixed>|null */
    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM webinar_signups WHERE token = ? AND edition = ? LIMIT 1'
        );
        $stmt->execute([$token, self::EDITION]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private static function findByEmail(string $email): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM webinar_signups WHERE email = ? AND edition = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email)), self::EDITION]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $row */
    public static function ics(array $row): string
    {
        $start = new \DateTimeImmutable(self::START, new \DateTimeZone(self::TZ));
        $end = $start->modify('+' . self::DURATION_MINUTES . ' minutes');
        $join = self::joinUrl();
        $page = rtrim(Config::baseUrl(), '/') . '/webinar';
        $description = self::TITLE . "\n" . self::when() . "\n" . $page;
        if ($join !== '') {
            $description .= "\nDeelnemen: " . $join;
        } else {
            $description .= "\nDe link om mee te doen staat in je mail, of volgt voor dinsdag.";
        }
        $uid = 'webinar-' . substr(hash('sha256', (string) $row['token']), 0, 16) . '@stopmetwilskracht.nl';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Stop met wilskracht//Webinar//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTEND:' . $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'SUMMARY:' . self::icsEscape(self::TITLE),
            'DESCRIPTION:' . self::icsEscape($description),
            'LOCATION:Online',
        ];
        if ($join !== '') {
            $lines[] = 'URL:' . self::icsEscape($join);
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    /** @param array<string,mixed> $row */
    public static function googleCalendarUrl(array $row): string
    {
        $start = new \DateTimeImmutable(self::START, new \DateTimeZone(self::TZ));
        $end = $start->modify('+' . self::DURATION_MINUTES . ' minutes');
        $dates = $start->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z')
            . '/'
            . $end->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $join = self::joinUrl();
        $details = self::TITLE . "\n" . self::when();
        if ($join !== '') {
            $details .= "\n\nDeelnemen:\n" . $join;
        } else {
            $details .= "\n\nDe link om mee te doen staat in je mail, of volgt voor dinsdag.";
        }

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
            . '&text=' . rawurlencode(self::TITLE)
            . '&dates=' . rawurlencode($dates)
            . '&details=' . rawurlencode($details)
            . '&location=' . rawurlencode('Online');
    }

    public static function agendaUrl(string $token): string
    {
        return rtrim(Config::baseUrl(), '/') . '/webinar/agenda?t=' . rawurlencode($token);
    }

    /** @param array<string,mixed> $row */
    private static function sendParticipantMail(array $row): bool
    {
        $first = (string) $row['first_name'];
        $email = (string) $row['email'];
        $token = (string) $row['token'];
        $join = self::joinUrl();
        $agenda = self::agendaUrl($token);
        $google = self::googleCalendarUrl($row);

        $subject = 'Je staat erbij, dinsdag 1 december 19:30';
        $text = "Hoi {$first},\r\n\r\n"
            . "Je bent ingeschreven voor het webinar.\r\n\r\n"
            . self::TITLE . "\r\n"
            . self::when() . ". Online, 30 minuten.\r\n\r\n"
            . "Ben je erbij, dan krijg je een gratis bonus: de handleiding jaarreview.\r\n\r\n";
        if ($join !== '') {
            $text .= "Dit is jouw link. Daarmee kom je binnen. Deel hem niet.\r\n\r\n{$join}\r\n\r\n";
        } else {
            $text .= "De link om mee te doen stuur ik je voor dinsdag.\r\n\r\n";
        }
        $text .= "Zet het meteen in je agenda:\r\n{$google}\r\n\r\n"
            . "Of download het agendapunt:\r\n{$agenda}\r\n\r\n"
            . "Tot dinsdag,\r\nKorne\r\n";

        $html = '<p>Hoi ' . e($first) . ',</p>'
            . '<p>Je bent ingeschreven voor het webinar.</p>'
            . '<p><strong>' . e(self::TITLE) . '</strong><br>' . e(self::when()) . '. Online, 30 minuten.</p>'
            . '<p>Ben je erbij, dan krijg je een gratis bonus: de handleiding jaarreview.</p>';
        if ($join !== '') {
            $html .= '<p>Dit is jouw link. Daarmee kom je binnen. Deel hem niet.</p>'
                . '<p><a href="' . e($join) . '">' . e($join) . '</a></p>';
        } else {
            $html .= '<p>De link om mee te doen stuur ik je voor dinsdag.</p>';
        }
        $html .= '<p><a href="' . e($google) . '">Zet in Google-agenda</a><br>'
            . '<a href="' . e($agenda) . '">Download het agendapunt</a></p>'
            . '<p>Tot dinsdag,<br>Korne</p>';

        return Mailer::send($email, $subject, Mailer::wrapHtml($subject, $html), $text);
    }

    /** @param array<string,mixed> $row */
    private static function notifyAdmin(array $row): void
    {
        $id = (int) $row['id'];
        $name = (string) $row['first_name'];
        $email = (string) $row['email'];
        $subject = 'Nieuwe webinar-inschrijving – ' . $email;
        $text = "Nieuwe inschrijving voor het webinar.\r\n\r\n{$name}\r\n{$email}\r\n" . self::when() . "\r\n";
        $html = '<p>Nieuwe inschrijving voor het webinar.</p>'
            . '<p><strong>' . e($name) . '</strong><br>' . e($email) . '</p>'
            . '<p>' . e(self::when()) . '</p>';
        Mailer::sendOnce(
            'webinar_signup_admin:' . $id,
            Mailer::adminEmail(),
            $subject,
            Mailer::wrapHtml($subject, $html),
            $text
        );
    }

    private static function validateName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            return 'Vul je voornaam in.';
        }
        if (preg_match('/[<>@]|https?:/i', $name)) {
            return 'Vul je voornaam in zonder link of e-mailadres.';
        }

        return null;
    }

    private static function icsEscape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $value);
    }

    /** @return list<string> */
    private static function utmKeys(): array
    {
        return ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    }

    private static function queryValue(array $query, string $key): string
    {
        $value = $query[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    private static function cleanTracking(string $value, int $max): string
    {
        $value = trim($value);
        $value = str_replace(["\r", "\n", "\0"], '', $value);
        if ($value === '' || preg_match('/[<>]/', $value)) {
            return '';
        }
        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }
        return $value;
    }

    private static function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    private static function ensureFbcCookie(string $fbclid): void
    {
        $existing = $_COOKIE['_fbc'] ?? '';
        if (is_string($existing) && $existing !== '') {
            return;
        }
        $value = 'fb.1.' . (string) (int) round(microtime(true) * 1000) . '.' . $fbclid;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        setcookie('_fbc', $value, [
            'expires' => time() + 90 * 86400,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        $_COOKIE['_fbc'] = $value;
    }
}

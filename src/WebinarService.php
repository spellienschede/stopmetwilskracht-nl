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
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_webinar_token (token),
                UNIQUE KEY uq_webinar_email_edition (email, edition)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaChecked = true;
    }

    /**
     * @return array{ok:true,token:string,mail_ok:bool,existing:bool}|array{ok:false,error:string}
     */
    public static function register(string $first, string $email, string $ip): array
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
            ];
        }

        $token = bin2hex(random_bytes(16));
        try {
            $pdo->prepare(
                'INSERT INTO webinar_signups (email, first_name, token, mail_ok, edition) VALUES (?, ?, ?, 0, ?)'
            )->execute([$email, $first, $token, self::EDITION]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                $again = self::findByEmail($email);
                if ($again !== null) {
                    return [
                        'ok' => true,
                        'token' => (string) $again['token'],
                        'mail_ok' => (int) $again['mail_ok'] === 1,
                        'existing' => true,
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

        return [
            'ok' => true,
            'token' => (string) $row['token'],
            'mail_ok' => $mailOk,
            'existing' => false,
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
}

<?php
declare(strict_types=1);

namespace Grippartner;

final class SessionService
{
    private static bool $schemaChecked = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS live_sessions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(80) NOT NULL,
                title VARCHAR(255) NOT NULL,
                hosts VARCHAR(255) NOT NULL DEFAULT \'\',
                intro TEXT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NULL,
                meeting_url VARCHAR(500) NULL,
                signup_open TINYINT(1) NOT NULL DEFAULT 1,
                is_published TINYINT(1) NOT NULL DEFAULT 1,
                host1_name VARCHAR(120) NULL,
                host1_role VARCHAR(160) NULL,
                host1_photo VARCHAR(255) NULL,
                host2_name VARCHAR(120) NULL,
                host2_role VARCHAR(160) NULL,
                host2_photo VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_live_sessions_slug (slug),
                INDEX idx_live_sessions_starts (starts_at),
                INDEX idx_live_sessions_published (is_published)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS live_session_signups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id INT UNSIGNED NOT NULL,
                public_signup_number VARCHAR(32) NOT NULL,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_lss_public (public_signup_number),
                UNIQUE KEY uq_lss_email_session (session_id, email),
                INDEX idx_lss_session (session_id),
                INDEX idx_lss_created (created_at),
                CONSTRAINT fk_lss_session FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaChecked = true;
        self::seedDefaultSession();
        self::migrateSessions();
    }

    private static function defaultSessionIntro(): string
    {
        return 'Je denkt dat je controle hebt — tot je merkt hoeveel er langs je heen gaat. In 30 minuten delen Bas en Korné drie geheimen over echte controle op je leven. Kort, concreet, gratis. Meld je aan; de Zoom-link volgt per mail.';
    }

    private static function nooitMeerTeDrukIntro(): string
    {
        return 'Je agenda barst, je to-dolijst groeit — en toch schiet wat er écht toe doet erbij in. In 30 minuten laat Korné zien hoe je stopt met “te druk” als standaardmodus. Kort, concreet, gratis. Meld je aan; de Zoom-link volgt per mail.';
    }

    /** @return array<string,mixed> */
    private static function nooitMeerTeDrukPayload(): array
    {
        return [
            'slug' => 'nooit-meer-te-druk',
            'title' => 'Nooit meer te druk?',
            'hosts' => 'Korné Pot',
            'intro' => self::nooitMeerTeDrukIntro(),
            'starts_at' => '2026-09-29 19:30:00',
            'ends_at' => '2026-09-29 20:00:00',
            'meeting_url' => '',
            'signup_open' => true,
            'is_published' => true,
            'host1_name' => 'Korné Pot',
            'host1_role' => 'Auteur Stop met wilskracht',
            'host1_photo' => '/assets/img/sessions/korne-pot-adidas.jpg',
            'host2_name' => '',
            'host2_role' => '',
            'host2_photo' => '',
        ];
    }

    /** Cliffhangers voor de landingspagina (per slug). */
    public static function cliffhangers(array $session): array
    {
        return match ((string) ($session['slug'] ?? '')) {
            '3-geheimen' => [
                'Waarom méér wilskracht je vaak juist minder controle geeft',
                'Het moment waarop je denkt dat jij kiest — terwijl iets anders stuurt',
                'Wat bijna niemand ziet aan het verschil tussen druk zijn en controle hebben',
            ],
            'nooit-meer-te-druk' => [
                'Waarom “ik heb het te druk” bijna nooit over tijd gaat',
                'Het verschil tussen druk zijn en de belangrijke dingen doen',
                'Hoe je in één week merkt dat je agenda weer van jou is',
            ],
            default => [],
        };
    }

    /** Eenmalig: oude 100-miljoen-views-sessie weg, Nooit meer te druk? (29 sep 2026) erin. */
    private static function migrateSessions(): void
    {
        $old = self::findBySlug('100-miljoen-views');
        if ($old) {
            $pdo = Database::pdo();
            $del = $pdo->prepare('DELETE FROM live_sessions WHERE id = ?');
            $del->execute([(int) $old['id']]);
        }
        $payload = self::nooitMeerTeDrukPayload();
        $existing = self::findBySlug('nooit-meer-te-druk');
        if ($existing) {
            $hosts = (string) ($existing['hosts'] ?? '');
            if (str_contains($hosts, 'Bas') || (string) ($existing['host2_name'] ?? '') !== '') {
                if (!empty($existing['meeting_url'])) {
                    $payload['meeting_url'] = (string) $existing['meeting_url'];
                }
                self::update((int) $existing['id'], $payload);
            }
            return;
        }
        self::create($payload);
    }

    private static function seedDefaultSession(): void
    {
        $intro = self::defaultSessionIntro();
        $existing = self::findBySlug('3-geheimen');
        if ($existing) {
            $current = (string) ($existing['intro'] ?? '');
            if ($current === '' || str_contains($current, 'grip op je leven')) {
                $stmt = Database::pdo()->prepare('UPDATE live_sessions SET intro = ? WHERE id = ?');
                $stmt->execute([$intro, (int) $existing['id']]);
            }
            return;
        }
        self::create([
            'slug' => '3-geheimen',
            'title' => '3 geheimen over controle op je leven',
            'hosts' => 'Bas Oude Luttikhuis en Korné Pot',
            'intro' => $intro,
            'starts_at' => '2026-09-14 19:30:00',
            'ends_at' => '2026-09-14 20:00:00',
            'meeting_url' => '',
            'signup_open' => true,
            'is_published' => true,
            'host1_name' => 'Bas Oude Luttikhuis',
            'host1_role' => 'Ondernemer & coach',
            'host1_photo' => '/assets/img/sessions/bas-oude-luttikhuis.jpg',
            'host2_name' => 'Korné Pot',
            'host2_role' => 'Auteur Stop met wilskracht',
            'host2_photo' => '/assets/img/sessions/korne-pot-adidas.jpg',
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->query(
            'SELECT s.*, (SELECT COUNT(*) FROM live_session_signups x WHERE x.session_id = s.id) AS signup_count
             FROM live_sessions s
             ORDER BY s.starts_at DESC, s.id DESC'
        );
        return $stmt->fetchAll() ?: [];
    }

    public static function findById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM live_sessions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        self::ensureSchema();
        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM live_sessions WHERE slug = ?');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array{
     *   slug:string,title:string,hosts:string,intro:string,
     *   starts_at:string,ends_at:?string,meeting_url:string,
     *   signup_open:bool,is_published:bool,
     *   host1_name:string,host1_role:string,host1_photo:string,
     *   host2_name:string,host2_role:string,host2_photo:string
     * } $data
     */
    public static function create(array $data): array
    {
        self::ensureSchema();
        $slug = self::uniqueSlug($data['slug']);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO live_sessions (
                slug, title, hosts, intro, starts_at, ends_at, meeting_url,
                signup_open, is_published,
                host1_name, host1_role, host1_photo,
                host2_name, host2_role, host2_photo
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $slug,
            $data['title'],
            $data['hosts'],
            $data['intro'] !== '' ? $data['intro'] : null,
            $data['starts_at'],
            $data['ends_at'] !== null && $data['ends_at'] !== '' ? $data['ends_at'] : null,
            $data['meeting_url'] !== '' ? $data['meeting_url'] : null,
            $data['signup_open'] ? 1 : 0,
            $data['is_published'] ? 1 : 0,
            $data['host1_name'] !== '' ? $data['host1_name'] : null,
            $data['host1_role'] !== '' ? $data['host1_role'] : null,
            $data['host1_photo'] !== '' ? $data['host1_photo'] : null,
            $data['host2_name'] !== '' ? $data['host2_name'] : null,
            $data['host2_role'] !== '' ? $data['host2_role'] : null,
            $data['host2_photo'] !== '' ? $data['host2_photo'] : null,
        ]);
        $row = self::findById((int) Database::pdo()->lastInsertId());
        return $row ?? [];
    }

    /**
     * @param array{
     *   slug:string,title:string,hosts:string,intro:string,
     *   starts_at:string,ends_at:?string,meeting_url:string,
     *   signup_open:bool,is_published:bool,
     *   host1_name:string,host1_role:string,host1_photo:string,
     *   host2_name:string,host2_role:string,host2_photo:string
     * } $data
     */
    public static function update(int $id, array $data): ?array
    {
        self::ensureSchema();
        $current = self::findById($id);
        if (!$current) {
            return null;
        }
        $slug = self::normalizeSlug($data['slug']);
        if ($slug === '' || ($slug !== (string) $current['slug'] && self::findBySlug($slug))) {
            $slug = self::uniqueSlug($data['slug'] !== '' ? $data['slug'] : (string) $current['title'], $id);
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE live_sessions SET
                slug = ?, title = ?, hosts = ?, intro = ?, starts_at = ?, ends_at = ?, meeting_url = ?,
                signup_open = ?, is_published = ?,
                host1_name = ?, host1_role = ?, host1_photo = ?,
                host2_name = ?, host2_role = ?, host2_photo = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $slug,
            $data['title'],
            $data['hosts'],
            $data['intro'] !== '' ? $data['intro'] : null,
            $data['starts_at'],
            $data['ends_at'] !== null && $data['ends_at'] !== '' ? $data['ends_at'] : null,
            $data['meeting_url'] !== '' ? $data['meeting_url'] : null,
            $data['signup_open'] ? 1 : 0,
            $data['is_published'] ? 1 : 0,
            $data['host1_name'] !== '' ? $data['host1_name'] : null,
            $data['host1_role'] !== '' ? $data['host1_role'] : null,
            $data['host1_photo'] !== '' ? $data['host1_photo'] : null,
            $data['host2_name'] !== '' ? $data['host2_name'] : null,
            $data['host2_role'] !== '' ? $data['host2_role'] : null,
            $data['host2_photo'] !== '' ? $data['host2_photo'] : null,
            $id,
        ]);
        return self::findById($id);
    }

    /** @param array{name:string,email:string} $data */
    public static function signup(int $sessionId, array $data): array
    {
        self::ensureSchema();
        $session = self::findById($sessionId);
        if (!$session) {
            throw new \RuntimeException('Sessie niet gevonden.');
        }
        $email = strtolower(trim($data['email']));
        $existing = self::findSignupByEmail($sessionId, $email);
        if ($existing) {
            return $existing + ['_already' => true, '_session' => $session];
        }

        $public = PublicId::session();
        $stmt = Database::pdo()->prepare(
            'INSERT INTO live_session_signups (session_id, public_signup_number, name, email) VALUES (?,?,?,?)'
        );
        $stmt->execute([$sessionId, $public, $data['name'], $email]);
        $id = (int) Database::pdo()->lastInsertId();
        $row = self::findSignupById($id);
        if ($row) {
            self::sendSignupEmails($session, $row);
            $parts = preg_split('/\s+/', $data['name'], 2) ?: [];
            $first = $parts[0] ?? $data['name'];
            $last = $parts[1] ?? '-';
            MailingList::subscribe($email, $first, $last, 'live-session:' . (string) $session['slug'], true);
        }
        return ($row ?? []) + ['_session' => $session];
    }

    public static function findSignupById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM live_session_signups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findSignupByEmail(int $sessionId, string $email): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM live_session_signups WHERE session_id = ? AND email = ? LIMIT 1'
        );
        $stmt->execute([$sessionId, strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function countSignups(int $sessionId): int
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM live_session_signups WHERE session_id = ?');
        $stmt->execute([$sessionId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public static function listSignups(int $sessionId, string $q = '', int $limit = 500, int $offset = 0): array
    {
        self::ensureSchema();
        $where = ['session_id = ?'];
        $params = [$sessionId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(name LIKE ? OR email LIKE ? OR public_signup_number LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sqlWhere = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM live_session_signups WHERE {$sqlWhere} ORDER BY id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public static function countSignupsFiltered(int $sessionId, string $q = ''): int
    {
        self::ensureSchema();
        $where = ['session_id = ?'];
        $params = [$sessionId];
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(name LIKE ? OR email LIKE ? OR public_signup_number LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $sqlWhere = implode(' AND ', $where);
        $stmt = Database::pdo()->prepare("SELECT COUNT(*) FROM live_session_signups WHERE {$sqlWhere}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function isOpen(array $session, ?\DateTimeImmutable $now = null): bool
    {
        if (!(int) ($session['signup_open'] ?? 0) || !(int) ($session['is_published'] ?? 0)) {
            return false;
        }
        $starts = self::startsAt($session);
        if (!$starts) {
            return false;
        }
        $tz = new \DateTimeZone(Config::string('TIMEZONE', 'Europe/Amsterdam'));
        $now = ($now ?? new \DateTimeImmutable('now', $tz))->setTimezone($tz);
        return $now < $starts;
    }

    public static function startsAt(array $session): ?\DateTimeImmutable
    {
        $raw = (string) ($session['starts_at'] ?? '');
        if ($raw === '') {
            return null;
        }
        $tz = new \DateTimeZone(Config::string('TIMEZONE', 'Europe/Amsterdam'));
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        return $dt ?: null;
    }

    public static function endsAt(array $session): ?\DateTimeImmutable
    {
        $raw = (string) ($session['ends_at'] ?? '');
        if ($raw === '') {
            return null;
        }
        $tz = new \DateTimeZone(Config::string('TIMEZONE', 'Europe/Amsterdam'));
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $tz);
        return $dt ?: null;
    }

    /** Bijv. "maandag 14 september 2026 van 19:30 – 20:00". */
    public static function formatWhen(array $session): string
    {
        $starts = self::startsAt($session);
        if (!$starts) {
            return '';
        }
        $date = self::formatDutchDate($starts, true);
        $startTime = $starts->format('H:i');
        $ends = self::endsAt($session);
        if ($ends) {
            return $date . ' van ' . $startTime . ' – ' . $ends->format('H:i');
        }
        return $date . ' om ' . $startTime;
    }

    public static function formatWhenShort(array $session): string
    {
        $starts = self::startsAt($session);
        if (!$starts) {
            return '';
        }
        return self::formatDutchDate($starts, false) . ' · ' . $starts->format('H:i');
    }

    public static function publicUrl(array $session): string
    {
        return Config::baseUrl() . '/sessie/' . rawurlencode((string) $session['slug']);
    }

    /**
     * Stuur Zoom-/meetinglink naar alle aanmeldingen.
     * @return array{sent:int,skipped:int,failed:int}
     */
    public static function sendMeetingLinkToAll(int $sessionId): array
    {
        $session = self::findById($sessionId);
        if (!$session) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $url = trim((string) ($session['meeting_url'] ?? ''));
        if ($url === '') {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }
        $signups = self::listSignups($sessionId, '', 5000, 0);
        $sent = 0;
        $failed = 0;
        foreach ($signups as $signup) {
            $key = 'session_meeting:' . $sessionId . ':' . (int) $signup['id'] . ':' . substr(md5($url), 0, 12);
            $exists = Database::pdo()->prepare('SELECT 1 FROM outbound_mail_log WHERE mail_key = ? LIMIT 1');
            $exists->execute([substr($key, 0, 160)]);
            if ($exists->fetchColumn()) {
                continue;
            }
            [$html, $text] = EmailTemplates::sessionMeetingLink($session, $signup);
            $ok = Mailer::sendOnce(
                $key,
                (string) $signup['email'],
                'Je Zoom-link: ' . (string) $session['title'],
                $html,
                $text
            );
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'skipped' => 0, 'failed' => $failed];
    }

    /**
     * Custom mail naar alle aanmeldingen van een sessie.
     * Placeholders: {{name}}, {{title}}, {{when}}, {{meeting_url}}, {{link}}
     * @return array{sent:int,failed:int}
     */
    public static function sendCustomMail(int $sessionId, string $subject, string $body, string $campaignKey): array
    {
        $session = self::findById($sessionId);
        if (!$session) {
            return ['sent' => 0, 'failed' => 0];
        }
        $campaignKey = preg_replace('/[^a-z0-9_\-]/i', '', $campaignKey) ?: ('custom_' . date('YmdHis'));
        $signups = self::listSignups($sessionId, '', 5000, 0);
        $sent = 0;
        $failed = 0;
        foreach ($signups as $signup) {
            $filledSubject = self::fillPlaceholders($subject, $session, $signup);
            $filledBody = self::fillPlaceholders($body, $session, $signup);
            [$html, $text] = EmailTemplates::sessionCustom($filledSubject, $filledBody, (string) $signup['name']);
            $key = 'session_custom:' . $sessionId . ':' . $campaignKey . ':' . (int) $signup['id'];
            $ok = Mailer::sendOnce($key, (string) $signup['email'], $filledSubject, $html, $text);
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $signup */
    public static function fillPlaceholders(string $template, array $session, array $signup): string
    {
        $map = [
            '{{name}}' => (string) ($signup['name'] ?? ''),
            '{{email}}' => (string) ($signup['email'] ?? ''),
            '{{title}}' => (string) ($session['title'] ?? ''),
            '{{when}}' => self::formatWhen($session),
            '{{meeting_url}}' => (string) ($session['meeting_url'] ?? ''),
            '{{link}}' => self::publicUrl($session),
            '{{hosts}}' => (string) ($session['hosts'] ?? ''),
        ];
        return strtr($template, $map);
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return substr($slug, 0, 80);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = self::normalizeSlug($base);
        if ($slug === '') {
            $slug = 'sessie';
        }
        $candidate = $slug;
        $i = 2;
        while (true) {
            $existing = self::findBySlug($candidate);
            if (!$existing || ($ignoreId !== null && (int) $existing['id'] === $ignoreId)) {
                return $candidate;
            }
            $candidate = substr($slug, 0, 70) . '-' . $i;
            $i++;
        }
    }

    private static function formatDutchDate(\DateTimeImmutable $dt, bool $withWeekday): string
    {
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

    /** @param array<string,mixed> $session @param array<string,mixed> $row */
    private static function sendSignupEmails(array $session, array $row): void
    {
        $id = (int) $row['id'];
        [$htmlA, $textA] = EmailTemplates::sessionSignupApplicant($session, $row);
        Mailer::sendOnce(
            'session_signup_applicant:' . $id,
            (string) $row['email'],
            'Aanmelding ontvangen: ' . (string) $session['title'],
            $htmlA,
            $textA
        );

        [$htmlB, $textB] = EmailTemplates::sessionSignupAdmin($session, $row);
        Mailer::sendOnce(
            'session_signup_admin:' . $id,
            Mailer::adminEmail(),
            'Nieuwe sessie-aanmelding – ' . (string) $row['name'],
            $htmlB,
            $textB
        );
    }
}

<?php
declare(strict_types=1);

namespace Grippartner;

final class PresentationService
{
    private static bool $schemaChecked = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS presentation_signups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_signup_number VARCHAR(32) NOT NULL,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(255) NOT NULL,
                notes TEXT NULL,
                event_date DATE NOT NULL,
                event_time VARCHAR(10) NOT NULL DEFAULT \'19:30\',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_presentation_public_nr (public_signup_number),
                UNIQUE KEY uq_presentation_email_event (email, event_date),
                INDEX idx_presentation_created (created_at),
                INDEX idx_presentation_event (event_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaChecked = true;
    }

    /** @param array{name:string,email:string,notes:string} $data */
    public static function create(array $data): array
    {
        self::ensureSchema();
        $pdo = Database::pdo();
        $eventDate = Config::string('BOOK_PRESENTATION_DATE', '2026-10-05');
        $eventTime = Config::formatPresentationTime();
        $email = strtolower(trim($data['email']));

        $existing = self::findByEmailAndEvent($email, $eventDate);
        if ($existing) {
            return $existing + ['_already' => true];
        }

        $public = PublicId::presentation();
        $stmt = $pdo->prepare(
            'INSERT INTO presentation_signups (
                public_signup_number, name, email, notes, event_date, event_time
            ) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $public,
            $data['name'],
            $email,
            $data['notes'] !== '' ? $data['notes'] : null,
            $eventDate,
            $eventTime,
        ]);

        $id = (int) $pdo->lastInsertId();
        $row = self::findById($id);
        if ($row) {
            self::sendEmails($row);
            $parts = preg_split('/\s+/', $data['name'], 2) ?: [];
            $first = $parts[0] ?? $data['name'];
            $last = $parts[1] ?? '-';
            MailingList::subscribe($email, $first, $last, 'boekpresentatie', true);
        }
        return $row ?? [];
    }

    public static function findById(int $id): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare('SELECT * FROM presentation_signups WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmailAndEvent(string $email, string $eventDate): ?array
    {
        self::ensureSchema();
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM presentation_signups WHERE email = ? AND event_date = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email)), $eventDate]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function count(?string $eventDate = null): int
    {
        self::ensureSchema();
        if ($eventDate === null) {
            $eventDate = Config::string('BOOK_PRESENTATION_DATE', '2026-10-05');
        }
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM presentation_signups WHERE event_date = ?'
        );
        $stmt->execute([$eventDate]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string,mixed> $row */
    private static function sendEmails(array $row): void
    {
        $id = (int) $row['id'];
        [$htmlA, $textA] = EmailTemplates::presentationSignupApplicant($row);
        Mailer::sendOnce(
            'presentation_signup_applicant:' . $id,
            (string) $row['email'],
            'Aanmelding boekpresentatie ontvangen',
            $htmlA,
            $textA
        );

        [$htmlB, $textB] = EmailTemplates::presentationSignupAdmin($row);
        Mailer::sendOnce(
            'presentation_signup_admin:' . $id,
            Mailer::adminEmail(),
            'Nieuwe aanmelding boekpresentatie – ' . (string) $row['name'],
            $htmlB,
            $textB
        );
    }
}

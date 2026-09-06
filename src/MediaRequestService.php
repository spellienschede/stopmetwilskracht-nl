<?php
declare(strict_types=1);

namespace Grippartner;

final class MediaRequestService
{
    /** @var array<string,string> */
    public const STATUS_LABELS = [
        'nieuw' => 'Nieuw',
        'in_behandeling' => 'In behandeling',
        'afgerond' => 'Afgerond',
        'afgewezen' => 'Afgewezen',
    ];

    /** @param array<string,mixed> $data */
    public static function create(array $data): array
    {
        $pdo = Database::pdo();
        $public = PublicId::media();

        $stmt = $pdo->prepare(
            'INSERT INTO media_requests (
                public_request_number, name, organization, email, channel_url,
                request_type, message, preferred_date, audience_reach, status
            ) VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $public,
            $data['name'],
            $data['organization'],
            $data['email'],
            $data['channel_url'] !== '' ? $data['channel_url'] : null,
            $data['request_type'],
            $data['message'],
            $data['preferred_date'] !== '' ? $data['preferred_date'] : null,
            $data['audience_reach'] !== '' ? $data['audience_reach'] : null,
            'nieuw',
        ]);

        $id = (int) $pdo->lastInsertId();
        $row = self::findById($id);
        if ($row) {
            self::sendEmails($row);
        }
        return $row ?? [];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM media_requests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function updateStatus(int $id, string $status, ?int $adminId = null): void
    {
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new \InvalidArgumentException('Ongeldige status.');
        }
        $handled = in_array($status, ['afgerond', 'afgewezen'], true)
            ? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            : null;
        $stmt = Database::pdo()->prepare(
            'UPDATE media_requests SET status = ?, handled_at = COALESCE(?, handled_at), updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $handled, $id]);
        unset($adminId);
    }

    public static function saveNote(int $id, string $note): void
    {
        $note = mb_substr(trim($note), 0, 5000);
        Database::pdo()->prepare(
            'UPDATE media_requests SET internal_note = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$note === '' ? null : $note, $id]);
    }

    /** @param array<string,mixed> $row */
    private static function sendEmails(array $row): void
    {
        $id = (int) $row['id'];
        [$htmlA, $textA] = EmailTemplates::mediaRequestApplicant($row);
        Mailer::sendOnce(
            'media_request_applicant:' . $id,
            (string) $row['email'],
            'We hebben je aanvraag over Grippartner ontvangen',
            $htmlA,
            $textA
        );

        $adminTo = Mailer::adminEmail();
        $typeLabel = MediaKit::REQUEST_TYPES[$row['request_type']] ?? (string) $row['request_type'];
        [$htmlB, $textB] = EmailTemplates::mediaRequestAdmin($row);
        Mailer::sendOnce(
            'media_request_admin:' . $id,
            $adminTo,
            'Nieuwe media-aanvraag Grippartner – ' . $typeLabel,
            $htmlB,
            $textB
        );
    }
}

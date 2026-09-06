<?php
declare(strict_types=1);

namespace Grippartner;

final class MailingList
{
    /**
     * Zet iemand op de gedeelde nieuwsbrief (zelfde tabel als kornepot.nl).
     * Wie eerder heeft uitgeschreven, laten we met rust tenzij $allowReactivate.
     */
    public static function subscribe(
        string $email,
        string $firstName,
        string $lastName,
        string $source,
        bool $allowReactivate = false
    ): void {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, uitgeschreven FROM nieuwsbrief_aanmeldingen WHERE email = ?');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        if ($existing) {
            if ((int) $existing['uitgeschreven'] === 1) {
                if (!$allowReactivate) {
                    return;
                }
                $upd = $pdo->prepare(
                    'UPDATE nieuwsbrief_aanmeldingen
                     SET voornaam = ?, achternaam = ?, uitgeschreven = 0, uitgeschreven_datum = NULL,
                         consent_source = ?, consent_at = ?
                     WHERE id = ?'
                );
                $upd->execute([$firstName, $lastName, $source, $now, $existing['id']]);
                return;
            }

            // Al actief: bron bewaren als die nog leeg is
            $upd = $pdo->prepare(
                'UPDATE nieuwsbrief_aanmeldingen
                 SET consent_source = COALESCE(consent_source, ?),
                     consent_at = COALESCE(consent_at, ?)
                 WHERE id = ?'
            );
            $upd->execute([$source, $now, $existing['id']]);
            return;
        }

        $ins = $pdo->prepare(
            'INSERT INTO nieuwsbrief_aanmeldingen
             (voornaam, achternaam, email, aanmeldmoment, uitgeschreven, consent_source, consent_at)
             VALUES (?, ?, ?, ?, 0, ?, ?)'
        );
        $ins->execute([$firstName, $lastName, $email, $now, $source, $now]);
    }

    /** @deprecated Gebruik subscribe(); behouden voor oude call sites. */
    public static function subscribeIfConsented(
        string $email,
        string $firstName,
        string $lastName,
        string $source,
        bool $consent
    ): void {
        if (!$consent) {
            return;
        }
        self::subscribe($email, $firstName, $lastName, $source, true);
    }
}

<?php
declare(strict_types=1);

namespace Grippartner;

final class PromoService
{
    public const SUBMITTED = 'submitted';
    public const CHANGES_REQUESTED = 'changes_requested';
    public const IDEA_REJECTED = 'idea_rejected';
    public const APPROVED_AWAITING = 'approved_awaiting_execution';
    public const EXECUTION_SUBMITTED = 'execution_submitted';
    public const EXECUTION_CHANGES = 'execution_changes_requested';
    public const EXECUTION_REJECTED = 'execution_rejected';
    public const EXECUTION_APPROVED = 'execution_approved';
    public const REWARD_CREATED = 'reward_order_created';
    public const SHIPPED = 'shipped';
    public const EXPIRED = 'expired';

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::SUBMITTED => [self::CHANGES_REQUESTED, self::IDEA_REJECTED, self::EXECUTION_SUBMITTED, self::EXECUTION_APPROVED, self::EXECUTION_CHANGES, self::EXECUTION_REJECTED],
        self::CHANGES_REQUESTED => [self::SUBMITTED, self::EXECUTION_SUBMITTED],
        self::APPROVED_AWAITING => [self::EXECUTION_SUBMITTED, self::EXPIRED],
        self::EXECUTION_SUBMITTED => [self::EXECUTION_CHANGES, self::EXECUTION_REJECTED, self::EXECUTION_APPROVED],
        self::EXECUTION_CHANGES => [self::EXECUTION_SUBMITTED],
        self::EXECUTION_APPROVED => [self::REWARD_CREATED],
        self::REWARD_CREATED => [self::SHIPPED],
    ];

    /** @var array<string,string> */
    public const LABELS = [
        self::SUBMITTED => 'Promo gemeld (oud)',
        self::CHANGES_REQUESTED => 'Aanvulling gevraagd',
        self::IDEA_REJECTED => 'Afgewezen',
        self::APPROVED_AWAITING => 'Wacht op uitvoering (oud)',
        self::EXECUTION_SUBMITTED => 'Promo gemeld',
        self::EXECUTION_CHANGES => 'Aanvulling gevraagd',
        self::EXECUTION_REJECTED => 'Afgewezen',
        self::EXECUTION_APPROVED => 'Bevestigd',
        self::REWARD_CREATED => 'Gratis boek toegekend',
        self::SHIPPED => 'Gratis boek verzonden',
        self::EXPIRED => 'Verlopen',
    ];

    /** @param array<string,mixed> $data */
    public static function createApplication(array $data): array
    {
        $pdo = Database::pdo();
        $public = PublicId::promo();
        $description = (string) $data['idea_description'];
        $links = (string) ($data['promo_link'] ?? $data['execution_links'] ?? '');

        $stmt = $pdo->prepare(
            'INSERT INTO promo_applications (
                public_application_number, first_name, last_name, email,
                street, house_number, house_addition, postal_code, city, country,
                idea_description, audience_reach, proposed_planning,
                execution_description, execution_links, execution_submitted_at, status,
                marketing_consent, marketing_consent_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,?)'
        );
        $stmt->execute([
            $public,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            (string) ($data['street'] ?? ''),
            (string) ($data['house_number'] ?? ''),
            $data['house_addition'] ?? null,
            (string) ($data['postal_code'] ?? ''),
            (string) ($data['city'] ?? ''),
            (string) ($data['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL')),
            $description,
            '',
            '',
            $description,
            $links,
            self::EXECUTION_SUBMITTED,
            0,
            null,
        ]);

        $id = (int) $pdo->lastInsertId();
        self::logHistory($id, null, self::EXECUTION_SUBMITTED, 'applicant', null, 'Promo gemeld', null);

        $app = self::findById($id);
        if ($app) {
            self::emailReceived($app);
            try {
                MailingList::subscribe(
                    (string) $app['email'],
                    (string) $app['first_name'],
                    (string) $app['last_name'],
                    'grippartner_promo'
                );
            } catch (\Throwable $e) {
                Logger::error('Newsletter subscribe after promo failed', ['m' => $e->getMessage()]);
            }
        }
        return $app ?? [];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM promo_applications WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByPublic(string $number): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM promo_applications WHERE public_application_number = ?');
        $stmt->execute([$number]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = Database::pdo()->prepare('SELECT * FROM promo_applications WHERE execution_token_hash = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function requestIdeaChanges(int $id, string $publicNote, ?int $adminId): string
    {
        $app = self::requireApp($id);
        $pair = PublicId::tokenPair();
        self::transition($app, self::CHANGES_REQUESTED, 'admin', $adminId, $publicNote, null, [
            'idea_changes_note' => $publicNote,
            'idea_changes_requested_at' => date('Y-m-d H:i:s'),
            'execution_token_hash' => $pair['hash'],
            'execution_token_created_at' => date('Y-m-d H:i:s'),
        ]);
        $fresh = self::requireApp($id);
        self::emailIdeaChanges($fresh, $pair['token']);
        return $pair['token'];
    }

    public static function rejectIdea(int $id, string $reason, bool $sendEmail, ?int $adminId): void
    {
        $app = self::requireApp($id);
        self::transition($app, self::IDEA_REJECTED, 'admin', $adminId, $reason, null, [
            'idea_rejection_reason' => $reason,
            'idea_rejected_at' => date('Y-m-d H:i:s'),
        ]);
        if ($sendEmail) {
            $fresh = self::requireApp($id);
            self::emailIdeaRejected($fresh);
        }
    }

    public static function approveIdea(
        int $id,
        string $agreement,
        ?string $deadline,
        ?int $adminId
    ): string {
        $app = self::requireApp($id);
        if ($app['status'] !== self::SUBMITTED) {
            throw new \RuntimeException('Alleen ingediende ideeën kunnen voorlopig worden goedgekeurd.');
        }

        $pair = PublicId::tokenPair();
        self::transition($app, self::APPROVED_AWAITING, 'admin', $adminId, 'Idee voorlopig goedgekeurd', null, [
            'execution_agreement' => $agreement,
            'execution_deadline' => $deadline ?: null,
            'idea_approved_at' => date('Y-m-d H:i:s'),
            'execution_token_hash' => $pair['hash'],
            'execution_token_created_at' => date('Y-m-d H:i:s'),
        ]);

        // Geen gratis order hier — alleen token + mail
        $fresh = self::requireApp($id);
        self::emailIdeaApproved($fresh, $pair['token']);
        return $pair['token'];
    }

    public static function resubmitIdea(string $token, string $idea, string $audience = '', string $planning = ''): void
    {
        $app = self::findByToken($token);
        if (!$app) {
            throw new \RuntimeException('Ongeldige of verlopen link.');
        }
        if ($app['status'] !== self::CHANGES_REQUESTED) {
            throw new \RuntimeException('Je kunt het idee nu niet aanpassen.');
        }
        if (mb_strlen($idea) < 15) {
            throw new \RuntimeException('Schrijf iets meer over je idee.');
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE promo_applications SET idea_description = ?, audience_reach = ?, proposed_planning = ? WHERE id = ?'
        )->execute([
            mb_substr($idea, 0, 5000),
            mb_substr($audience, 0, 3000),
            mb_substr($planning, 0, 3000),
            $app['id'],
        ]);
        self::transition($app, self::SUBMITTED, 'applicant', null, 'Aangepast idee opnieuw ingediend', null);
        $fresh = self::requireApp((int) $app['id']);
        self::emailReceived($fresh, true);
    }

    public static function markExpired(int $id, ?int $adminId): void
    {
        $app = self::requireApp($id);
        self::transition($app, self::EXPIRED, 'admin', $adminId, 'Uitvoeringstermijn verstreken', null);
    }

    /** @param array<string,mixed> $payload */
    public static function submitExecution(string $token, array $payload): void
    {
        $app = self::findByToken($token);
        if (!$app) {
            throw new \RuntimeException('Ongeldige of verlopen link.');
        }
        if (!in_array($app['status'], [self::APPROVED_AWAITING, self::EXECUTION_CHANGES], true)) {
            throw new \RuntimeException('Je kunt nu geen uitvoering indienen voor deze aanvraag.');
        }

        $desc = trim((string) ($payload['execution_description'] ?? ''));
        if (mb_strlen($desc) < 20) {
            throw new \RuntimeException('Beschrijf wat je hebt uitgevoerd.');
        }

        $street = trim((string) ($payload['street'] ?? ''));
        $house = trim((string) ($payload['house_number'] ?? ''));
        $postal = trim((string) ($payload['postal_code'] ?? ''));
        $city = trim((string) ($payload['city'] ?? ''));
        if ($street === '' || $house === '' || $postal === '' || $city === '') {
            throw new \RuntimeException('Vul je afleveradres in voor het gratis exemplaar.');
        }

        $pdo = Database::pdo();
        $pdo->prepare(
            'UPDATE promo_applications SET
                execution_description = ?,
                execution_performed_on = ?,
                execution_links = ?,
                execution_reach_notes = ?,
                execution_extra_notes = ?,
                street = ?,
                house_number = ?,
                house_addition = ?,
                postal_code = ?,
                city = ?,
                country = ?,
                execution_submitted_at = NOW()
             WHERE id = ?'
        )->execute([
            mb_substr($desc, 0, 5000),
            $payload['execution_performed_on'] ?: null,
            mb_substr(trim((string) ($payload['execution_links'] ?? '')), 0, 5000),
            mb_substr(trim((string) ($payload['execution_reach_notes'] ?? '')), 0, 3000),
            mb_substr(trim((string) ($payload['execution_extra_notes'] ?? '')), 0, 3000),
            mb_substr($street, 0, 150),
            mb_substr($house, 0, 20),
            mb_substr(trim((string) ($payload['house_addition'] ?? '')), 0, 20) ?: null,
            mb_substr($postal, 0, 20),
            mb_substr($city, 0, 100),
            strtoupper(mb_substr(trim((string) ($payload['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL'))), 0, 2)),
            $app['id'],
        ]);

        self::transition($app, self::EXECUTION_SUBMITTED, 'applicant', null, 'Uitvoering ingediend', null);
        $fresh = self::requireApp((int) $app['id']);
        self::emailExecutionReceived($fresh);
    }

    public static function requestExecutionChanges(int $id, string $note, ?int $adminId): string
    {
        $app = self::requireApp($id);
        $pair = PublicId::tokenPair();
        self::transition($app, self::EXECUTION_CHANGES, 'admin', $adminId, $note, null, [
            'execution_changes_note' => $note,
            'execution_changes_requested_at' => date('Y-m-d H:i:s'),
            'execution_token_hash' => $pair['hash'],
            'execution_token_created_at' => date('Y-m-d H:i:s'),
        ]);
        $fresh = self::requireApp($id);
        self::emailExecutionChanges($fresh, $pair['token']);
        return $pair['token'];
    }

    public static function rejectExecution(int $id, string $reason, bool $sendEmail, ?int $adminId): void
    {
        $app = self::requireApp($id);
        self::transition($app, self::EXECUTION_REJECTED, 'admin', $adminId, $reason, null, [
            'execution_rejection_reason' => $reason,
            'execution_rejected_at' => date('Y-m-d H:i:s'),
        ]);
        if ($sendEmail) {
            $fresh = self::requireApp($id);
            self::emailExecutionRejected($fresh);
        }
    }

    /**
     * Definitieve goedkeuring → execution_approved → één gratis order → reward_order_created.
     * Idempotent en transactioneel.
     */
    public static function approveExecution(int $id, ?int $adminId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM promo_applications WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if (!$app) {
                throw new \RuntimeException('Aanvraag niet gevonden.');
            }

            if ($app['status'] === self::REWARD_CREATED || $app['status'] === self::SHIPPED || !empty($app['reward_order_id'])) {
                $pdo->commit();
                $order = OrderService::findById((int) $app['reward_order_id']);
                return ['application' => $app, 'order' => $order];
            }

            if (
                $app['status'] !== self::EXECUTION_SUBMITTED
                && $app['status'] !== self::EXECUTION_APPROVED
                && $app['status'] !== self::SUBMITTED
            ) {
                throw new \RuntimeException('Promo kan nu niet worden bevestigd.');
            }

            $street = trim((string) ($app['street'] ?? ''));
            $house = trim((string) ($app['house_number'] ?? ''));
            $postal = trim((string) ($app['postal_code'] ?? ''));
            $city = trim((string) ($app['city'] ?? ''));
            if ($street === '' || $house === '' || $postal === '' || $city === '') {
                throw new \RuntimeException('Nog geen afleveradres. Vraag de aanvrager dit aan te vullen.');
            }

            if ($app['status'] === self::EXECUTION_SUBMITTED || $app['status'] === self::SUBMITTED) {
                $from = (string) $app['status'];
                $pdo->prepare(
                    'UPDATE promo_applications SET status = ?, execution_approved_at = NOW() WHERE id = ?'
                )->execute([self::EXECUTION_APPROVED, $id]);
                self::logHistory($id, $from, self::EXECUTION_APPROVED, 'admin', $adminId, 'Promo bevestigd', null);
                $app['status'] = self::EXECUTION_APPROVED;
            }

            // Maak precies één gratis order
            $public = PublicId::order();
            $ins = $pdo->prepare(
                'INSERT INTO book_orders (
                    public_order_number, source, promo_application_id,
                    first_name, last_name, email,
                    street, house_number, house_addition, postal_code, city, country,
                    quantity, unit_price_cents, total_cents, currency,
                    payment_status, fulfilment_status,
                    marketing_consent, marketing_consent_at
                ) VALUES (
                    ?, \'approved_promo\', ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    1, 0, 0, ?,
                    ?, ?,
                    ?, ?
                )'
            );
            $ins->execute([
                $public,
                $id,
                $app['first_name'],
                $app['last_name'],
                $app['email'],
                $app['street'],
                $app['house_number'],
                $app['house_addition'],
                $app['postal_code'],
                $app['city'],
                $app['country'],
                Config::string('BOOK_CURRENCY', 'EUR'),
                OrderService::PAYMENT_NA,
                OrderService::FULFILMENT_READY,
                (int) $app['marketing_consent'],
                $app['marketing_consent_at'],
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'UPDATE promo_applications SET status = ?, reward_order_id = ? WHERE id = ? AND reward_order_id IS NULL'
            )->execute([self::REWARD_CREATED, $orderId, $id]);

            self::logHistory($id, self::EXECUTION_APPROVED, self::REWARD_CREATED, 'admin', $adminId, 'Gratis order aangemaakt', null);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fresh = self::requireApp($id);
        $order = OrderService::findById((int) $fresh['reward_order_id']);
        if ($order) {
            self::emailReward($fresh, $order);
        }
        return ['application' => $fresh, 'order' => $order];
    }

    public static function markShippedFromOrder(int $promoId, int $orderId): void
    {
        $app = self::findById($promoId);
        if (!$app || (int) $app['reward_order_id'] !== $orderId) {
            return;
        }
        if ($app['status'] === self::SHIPPED) {
            return;
        }
        if ($app['status'] !== self::REWARD_CREATED) {
            return;
        }
        self::transition($app, self::SHIPPED, 'admin', null, 'Gratis exemplaar verzonden', null);
        $fresh = self::requireApp($promoId);
        if (empty($fresh['shipped_email_sent_at'])) {
            // OrderService stuurt de verzendmail
            Database::pdo()->prepare('UPDATE promo_applications SET shipped_email_sent_at = NOW() WHERE id = ?')
                ->execute([$promoId]);
        }
    }

    /** @return array<string,int> */
    public static function dashboardCounts(): array
    {
        $pdo = Database::pdo();
        $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM promo_applications GROUP BY status')->fetchAll();
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    public static function history(int $promoId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM promo_status_history WHERE promo_application_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$promoId]);
        return $stmt->fetchAll();
    }

    private static function requireApp(int $id): array
    {
        $app = self::findById($id);
        if (!$app) {
            throw new \RuntimeException('Aanvraag niet gevonden.');
        }
        return $app;
    }

    /** @param array<string,mixed> $extra */
    private static function transition(
        array $app,
        string $to,
        string $actor,
        ?int $actorId,
        ?string $publicNote,
        ?string $internalNote,
        array $extra = []
    ): void {
        $from = (string) $app['status'];
        if (!self::canTransition($from, $to)) {
            throw new \RuntimeException('Statusovergang niet toegestaan: ' . $from . ' → ' . $to);
        }

        $sets = ['status = ?'];
        $params = [$to];
        foreach ($extra as $col => $val) {
            $sets[] = $col . ' = ?';
            $params[] = $val;
        }
        $params[] = $app['id'];

        Database::pdo()->prepare(
            'UPDATE promo_applications SET ' . implode(', ', $sets) . ' WHERE id = ?'
        )->execute($params);

        self::logHistory((int) $app['id'], $from, $to, $actor, $actorId, $publicNote, $internalNote);
    }

    private static function logHistory(
        int $promoId,
        ?string $old,
        string $new,
        string $actor,
        ?int $actorId,
        ?string $publicNote,
        ?string $internalNote
    ): void {
        Database::pdo()->prepare(
            'INSERT INTO promo_status_history
             (promo_application_id, old_status, new_status, actor, actor_id, public_note, internal_note)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$promoId, $old, $new, $actor, $actorId, $publicNote, $internalNote]);
    }

    private static function emailReceived(array $app, bool $resubmit = false): void
    {
        if (!$resubmit && empty($app['received_email_sent_at'])) {
            [$html, $text] = EmailTemplates::promoReceivedApplicant($app);
            if (Mailer::sendOnce('promo_received:' . $app['id'], (string) $app['email'], 'Je promo voor Grippartner is ontvangen', $html, $text)) {
                Database::pdo()->prepare('UPDATE promo_applications SET received_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
            }
        }
        $adminKey = $resubmit ? 'promo_received_admin_resubmit:' . $app['id'] . ':' . time() : 'promo_received_admin:' . $app['id'];
        // For resubmit allow new admin notice with unique key per day
        if ($resubmit) {
            $adminKey = 'promo_resubmit_admin:' . $app['id'] . ':' . date('Ymd');
        }
        if ($resubmit || empty($app['admin_received_email_sent_at'])) {
            [$html, $text] = EmailTemplates::promoReceivedAdmin($app);
            $admin = Config::string('ADMIN_NOTIFICATION_EMAIL', 'info@kornepot.nl');
            if ($admin === '') {
                $admin = 'info@kornepot.nl';
            }
            if (Mailer::sendOnce($adminKey, $admin, 'Nieuwe promo voor Grippartner – ' . $app['public_application_number'], $html, $text)) {
                if (!$resubmit) {
                    Database::pdo()->prepare('UPDATE promo_applications SET admin_received_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
                }
            }
        }
    }

    private static function emailIdeaChanges(array $app, string $token): void
    {
        $key = 'promo_idea_changes:' . $app['id'] . ':' . ($app['idea_changes_requested_at'] ?? 'x');
        [$html, $text] = EmailTemplates::promoIdeaChangesWithToken($app, $token);
        if (Mailer::sendOnce($key, (string) $app['email'], 'We hebben nog een vraag over je promo-idee', $html, $text)) {
            Database::pdo()->prepare('UPDATE promo_applications SET idea_changes_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
        }
    }

    private static function emailIdeaRejected(array $app): void
    {
        if (!empty($app['idea_rejected_email_sent_at'])) {
            return;
        }
        [$html, $text] = EmailTemplates::promoIdeaRejected($app);
        if (Mailer::sendOnce('promo_idea_rejected:' . $app['id'], (string) $app['email'], 'Reactie op je promo-idee voor Grippartner', $html, $text)) {
            Database::pdo()->prepare('UPDATE promo_applications SET idea_rejected_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
        }
    }

    private static function emailIdeaApproved(array $app, string $token): void
    {
        if (!empty($app['idea_approved_email_sent_at'])) {
            return;
        }
        [$html, $text] = EmailTemplates::promoIdeaApproved($app, $token);
        if (Mailer::sendOnce('promo_idea_approved:' . $app['id'], (string) $app['email'], 'Je promo-idee voor Grippartner is bevestigd – nu de uitvoering', $html, $text)) {
            Database::pdo()->prepare('UPDATE promo_applications SET idea_approved_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
        }
    }

    private static function emailExecutionReceived(array $app): void
    {
        $key = 'promo_exec_received:' . $app['id'] . ':' . ($app['execution_submitted_at'] ?? date('YmdHis'));
        [$html, $text] = EmailTemplates::promoExecutionReceivedApplicant($app);
        Mailer::sendOnce($key, (string) $app['email'], 'Je uitvoering is ontvangen', $html, $text);
        Database::pdo()->prepare('UPDATE promo_applications SET execution_received_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);

        $adminKey = 'promo_exec_admin:' . $app['id'] . ':' . ($app['execution_submitted_at'] ?? date('YmdHis'));
        [$htmlA, $textA] = EmailTemplates::promoExecutionReceivedAdmin($app);
        Mailer::sendOnce($adminKey, \Grippartner\Mailer::adminEmail(), 'Uitvoering promo-idee klaar voor beoordeling – ' . $app['public_application_number'], $htmlA, $textA);
        Database::pdo()->prepare('UPDATE promo_applications SET execution_admin_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
    }

    private static function emailExecutionChanges(array $app, string $token = ''): void
    {
        $key = 'promo_exec_changes:' . $app['id'] . ':' . ($app['execution_changes_requested_at'] ?? 'x');
        // Rotating token would invalidate old approval link; keep same hash and remind to use original link.
        [$html, $text] = EmailTemplates::promoExecutionChanges($app, $token);
        Mailer::sendOnce($key, (string) $app['email'], 'Kun je je promotie nog aanvullen?', $html, $text);
        Database::pdo()->prepare('UPDATE promo_applications SET execution_changes_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
    }

    private static function emailExecutionRejected(array $app): void
    {
        if (!empty($app['execution_rejected_email_sent_at'])) {
            return;
        }
        [$html, $text] = EmailTemplates::promoExecutionRejected($app);
        if (Mailer::sendOnce('promo_exec_rejected:' . $app['id'], (string) $app['email'], 'Reactie op de uitvoering van je promo-idee', $html, $text)) {
            Database::pdo()->prepare('UPDATE promo_applications SET execution_rejected_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
        }
    }

    private static function emailReward(array $app, array $order): void
    {
        if (empty($app['reward_email_sent_at'])) {
            [$html, $text] = EmailTemplates::promoRewardApplicant($app, $order);
            if (Mailer::sendOnce('promo_reward:' . $app['id'], (string) $app['email'], 'Dank je wel – je ontvangt een gratis exemplaar van Grippartner', $html, $text)) {
                Database::pdo()->prepare('UPDATE promo_applications SET reward_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
            }
        }
        if (empty($app['reward_admin_email_sent_at'])) {
            [$html, $text] = EmailTemplates::promoRewardAdmin($app, $order);
            if (Mailer::sendOnce('promo_reward_admin:' . $app['id'], \Grippartner\Mailer::adminEmail(), 'Gratis exemplaar toegekend – ' . $order['public_order_number'], $html, $text)) {
                Database::pdo()->prepare('UPDATE promo_applications SET reward_admin_email_sent_at = NOW() WHERE id = ?')->execute([$app['id']]);
            }
        }
    }
}

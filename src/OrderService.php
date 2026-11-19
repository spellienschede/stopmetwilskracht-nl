<?php
declare(strict_types=1);

namespace Grippartner;

final class OrderService
{
    public const PAYMENT_PENDING = 'pending_payment';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_CANCELED = 'canceled';
    public const PAYMENT_EXPIRED = 'expired';
    public const PAYMENT_REFUNDED = 'refunded';
    public const PAYMENT_NA = 'not_applicable';

    public const FULFILMENT_AWAITING = 'awaiting_payment';
    public const FULFILMENT_READY = 'ready_to_ship';
    public const FULFILMENT_SHIPPED = 'shipped';

    public static function isBlackFridayOrder(array $order): bool
    {
        return (string) ($order['promo'] ?? '') === Config::blackFridayPromoCode();
    }

    public static function purchaseEventId(array $order): string
    {
        $public = (string) ($order['public_order_number'] ?? '');
        if (self::isBlackFridayOrder($order)) {
            return 'purchase_' . $public;
        }
        return 'purchase:' . $public;
    }

    /**
     * @param array<string,mixed> $order
     * @return array{content_name:string,num_items:int,value:float,currency:string,content_ids:list<string>,item_price:float}
     */
    public static function commercePayload(array $order): array
    {
        $bf = self::isBlackFridayOrder($order);
        $qty = $bf ? 2 : max(1, (int) ($order['quantity'] ?? 1));
        $valueCents = (int) ($order['total_cents'] ?? 0);
        return [
            'content_name' => $bf ? 'Black Friday 1+1' : Config::string('BOOK_TITLE'),
            'num_items' => $qty,
            'value' => $valueCents / 100,
            'currency' => (string) ($order['currency'] ?? Config::string('BOOK_CURRENCY', 'EUR')),
            'content_ids' => ['stop-met-wilskracht'],
            'item_price' => ($valueCents / $qty) / 100,
        ];
    }

    /**
     * Black Friday: 2 boeken, te betalen is de gewone prijs van 1 boek.
     * De boekprijs is inclusief één zending; er is geen aparte verzendklasse.
     *
     * @param array<string,mixed> $data
     * @return array{quantity:int,unit_price_cents:int,total_cents:int,promo:?string}
     */
    public static function resolveCheckout(array $data, ?\DateTimeImmutable $now = null): array
    {
        if (($data['actie'] ?? '') === 'blackfriday') {
            if (Config::isBlackFridayActive($now)) {
                $unit = Config::regularPriceCents();
                return [
                    'quantity' => 2,
                    'unit_price_cents' => $unit,
                    'total_cents' => $unit,
                    'promo' => Config::blackFridayPromoCode(),
                ];
            }
            $unit = Config::currentPriceCents();
            return [
                'quantity' => 1,
                'unit_price_cents' => $unit,
                'total_cents' => $unit,
                'promo' => null,
            ];
        }
        $qty = max(1, min(50, (int) ($data['quantity'] ?? 1)));
        $unit = Config::currentPriceCents();
        return [
            'quantity' => $qty,
            'unit_price_cents' => $unit,
            'total_cents' => $unit * $qty,
            'promo' => null,
        ];
    }

    public static function ensureBlackFridayColumns(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $pdo = Database::pdo();
        $existing = [];
        $cols = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'book_orders'"
        );
        foreach ($cols->fetchAll() as $row) {
            $existing[(string) $row['COLUMN_NAME']] = true;
        }
        $add = [
            'promo' => 'ALTER TABLE book_orders ADD COLUMN promo VARCHAR(64) NULL',
            'utm_source' => 'ALTER TABLE book_orders ADD COLUMN utm_source VARCHAR(120) NULL',
            'utm_medium' => 'ALTER TABLE book_orders ADD COLUMN utm_medium VARCHAR(120) NULL',
            'utm_campaign' => 'ALTER TABLE book_orders ADD COLUMN utm_campaign VARCHAR(120) NULL',
            'utm_content' => 'ALTER TABLE book_orders ADD COLUMN utm_content VARCHAR(120) NULL',
        ];
        foreach ($add as $column => $sql) {
            if (!isset($existing[$column])) {
                $pdo->exec($sql);
            }
        }
        $index = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'book_orders' AND INDEX_NAME = 'idx_order_promo'"
        )->fetchColumn();
        if ((int) $index === 0) {
            $pdo->exec('ALTER TABLE book_orders ADD INDEX idx_order_promo (promo)');
        }
        $ready = true;
    }

    /** @param array<string,mixed> $data */
    public static function createPendingOrder(array $data): array
    {
        self::ensureBlackFridayColumns();
        $priced = self::resolveCheckout($data);
        $public = PublicId::order();
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'INSERT INTO book_orders (
                public_order_number, source, promo, first_name, last_name, email,
                street, house_number, house_addition, postal_code, city, country,
                quantity, unit_price_cents, total_cents, currency,
                payment_status, fulfilment_status,
                marketing_consent, marketing_consent_at,
                utm_source, utm_medium, utm_campaign, utm_content
            ) VALUES (
                ?, \'mollie\', ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?, ?
            )'
        );

        $consentAt = !empty($data['marketing_consent'])
            ? (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s')
            : null;

        $stmt->execute([
            $public,
            $priced['promo'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['street'],
            $data['house_number'],
            $data['house_addition'],
            $data['postal_code'],
            $data['city'],
            $data['country'],
            $priced['quantity'],
            $priced['unit_price_cents'],
            $priced['total_cents'],
            Config::string('BOOK_CURRENCY', 'EUR'),
            self::PAYMENT_PENDING,
            self::FULFILMENT_AWAITING,
            !empty($data['marketing_consent']) ? 1 : 0,
            $consentAt,
            $data['utm_source'] ?? null,
            $data['utm_medium'] ?? null,
            $data['utm_campaign'] ?? null,
            $data['utm_content'] ?? null,
        ]);

        $id = (int) $pdo->lastInsertId();
        return self::findById($id) ?? [];
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM book_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByPublicNumber(string $number): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM book_orders WHERE public_order_number = ?');
        $stmt->execute([$number]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByMollieId(string $paymentId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM book_orders WHERE mollie_payment_id = ?');
        $stmt->execute([$paymentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function startMolliePayment(array $order): array
    {
        $client = new MollieClient();
        $amountValue = number_format(((int) $order['total_cents']) / 100, 2, '.', '');
        $description = self::isBlackFridayOrder($order)
            ? Config::string('BOOK_TITLE') . ' – 2 voor 1 (' . $order['public_order_number'] . ')'
            : Config::string('BOOK_TITLE') . ' × ' . $order['quantity'] . ' (' . $order['public_order_number'] . ')';
        $siteHost = parse_url(Config::baseUrl(), PHP_URL_HOST);

        $payload = [
            'amount' => [
                'currency' => $order['currency'],
                'value' => $amountValue,
            ],
            'description' => $description,
            'redirectUrl' => Config::baseUrl() . '/betaalstatus.php?order=' . rawurlencode((string) $order['public_order_number']),
            'method' => 'ideal',
            'metadata' => [
                'order_id' => (int) $order['id'],
                'public_order_number' => $order['public_order_number'],
                'site' => is_string($siteHost) && $siteHost !== '' ? $siteHost : 'stopmetwilskracht.nl',
                'promo' => (string) ($order['promo'] ?? ''),
            ],
        ];

        // Webhook alleen meesturen als bereikbaar (niet op lokaal .test)
        $webhook = Config::string('MOLLIE_WEBHOOK_URL');
        if ($webhook === '' && Config::string('APP_ENV') !== 'local') {
            $webhook = Config::baseUrl() . '/webhook-mollie.php';
        }
        if ($webhook !== '' && !str_contains($webhook, '.test') && !str_contains($webhook, 'localhost')) {
            $payload['webhookUrl'] = $webhook;
        }

        $payment = $client->createPayment($payload);
        $paymentId = (string) ($payment['id'] ?? '');
        if ($paymentId === '') {
            throw new \RuntimeException('Mollie gaf geen payment id terug.');
        }

        $checkout = $payment['_links']['checkout']['href'] ?? null;
        if (!is_string($checkout) || $checkout === '') {
            throw new \RuntimeException('Mollie gaf geen checkout-URL terug.');
        }

        $upd = Database::pdo()->prepare('UPDATE book_orders SET mollie_payment_id = ? WHERE id = ? AND mollie_payment_id IS NULL');
        $upd->execute([$paymentId, $order['id']]);

        return [
            'payment_id' => $paymentId,
            'checkout_url' => $checkout,
        ];
    }

    /**
     * Webhook: haal actuele status op bij Mollie en werk order bij (idempotent).
     */
    public static function processMollieWebhook(string $paymentId): void
    {
        $client = new MollieClient();
        $payment = $client->getPayment($paymentId);
        $status = (string) ($payment['status'] ?? '');
        $eventKey = $paymentId . ':' . $status;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            try {
                $ins = $pdo->prepare(
                    'INSERT INTO payment_events (mollie_payment_id, event_key, order_id, mollie_status)
                     VALUES (?, ?, NULL, ?)'
                );
                $ins->execute([$paymentId, $eventKey, $status]);
            } catch (\PDOException $e) {
                if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                    $pdo->commit();
                    $existing = self::findByMollieId($paymentId);
                    if ($existing && $existing['payment_status'] === self::PAYMENT_PAID) {
                        self::sendPaidEmails($existing);
                        self::sendMetaPurchaseOnce($existing);
                    }
                    return;
                }
                throw $e;
            }

            $order = self::findByMollieId($paymentId);
            if (!$order && isset($payment['metadata']['order_id'])) {
                $order = self::findById((int) $payment['metadata']['order_id']);
            }
            if (!$order) {
                $pdo->commit();
                Logger::error('Webhook order not found', ['payment' => $paymentId]);
                return;
            }

            $updEvent = $pdo->prepare('UPDATE payment_events SET order_id = ? WHERE event_key = ?');
            $updEvent->execute([(int) $order['id'], $eventKey]);

            $mapped = self::mapMollieStatus($status);
            if ($mapped === self::PAYMENT_PAID) {
                // Alleen markeren als betaald als Mollie paid bevestigt
                if ($order['payment_status'] !== self::PAYMENT_PAID) {
                    $upd = $pdo->prepare(
                        'UPDATE book_orders
                         SET payment_status = ?, fulfilment_status = ?, paid_at = COALESCE(paid_at, NOW())
                         WHERE id = ? AND payment_status <> ?'
                    );
                    $upd->execute([self::PAYMENT_PAID, self::FULFILMENT_READY, $order['id'], self::PAYMENT_PAID]);
                }
            } elseif (in_array($mapped, [self::PAYMENT_FAILED, self::PAYMENT_CANCELED, self::PAYMENT_EXPIRED, self::PAYMENT_REFUNDED], true)) {
                if ($order['payment_status'] !== self::PAYMENT_PAID) {
                    $upd = $pdo->prepare('UPDATE book_orders SET payment_status = ? WHERE id = ? AND payment_status <> ?');
                    $upd->execute([$mapped, $order['id'], self::PAYMENT_PAID]);
                } elseif ($mapped === self::PAYMENT_REFUNDED) {
                    $upd = $pdo->prepare('UPDATE book_orders SET payment_status = ? WHERE id = ?');
                    $upd->execute([self::PAYMENT_REFUNDED, $order['id']]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fresh = self::findById((int) ($order['id'] ?? 0));
        if ($fresh && $fresh['payment_status'] === self::PAYMENT_PAID) {
            self::sendPaidEmails($fresh);
            self::sendMetaPurchaseOnce($fresh);
            try {
                MailingList::subscribe(
                    (string) $fresh['email'],
                    (string) $fresh['first_name'],
                    (string) $fresh['last_name'],
                    'grippartner_order'
                );
            } catch (\Throwable $e) {
                Logger::error('Newsletter subscribe after order failed', ['m' => $e->getMessage()]);
            }
        }
    }

    public static function attachMetaClickIds(array $order, ?string $fbp, ?string $fbc): void
    {
        $sets = [];
        $params = [];
        if ($fbp && empty($order['meta_fbp'])) {
            $sets[] = 'meta_fbp = ?';
            $params[] = $fbp;
        }
        if ($fbc && empty($order['meta_fbc'])) {
            $sets[] = 'meta_fbc = ?';
            $params[] = $fbc;
        }
        if ($sets === []) {
            return;
        }
        $params[] = (int) $order['id'];
        Database::pdo()->prepare('UPDATE book_orders SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public static function markMetaIcSent(int $orderId): void
    {
        Database::pdo()->prepare('UPDATE book_orders SET meta_ic_sent_at = COALESCE(meta_ic_sent_at, NOW()) WHERE id = ?')
            ->execute([$orderId]);
    }

    /** @return bool True als deze aanroep de Purchase als eerste claimt. */
    public static function markMetaPurchaseSent(int $orderId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE book_orders SET meta_purchase_sent_at = NOW() WHERE id = ? AND meta_purchase_sent_at IS NULL'
        );
        $stmt->execute([$orderId]);
        return $stmt->rowCount() > 0;
    }

    public static function releaseMetaPurchaseSend(int $orderId): void
    {
        Database::pdo()->prepare('UPDATE book_orders SET meta_purchase_sent_at = NULL WHERE id = ?')
            ->execute([$orderId]);
    }

    /** Server-Purchase één keer per order. Browser gebruikt hetzelfde event_id. */
    public static function sendMetaPurchaseOnce(array $order): void
    {
        if (($order['payment_status'] ?? '') !== self::PAYMENT_PAID) {
            return;
        }
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId === 0 || !self::markMetaPurchaseSent($orderId)) {
            return;
        }
        try {
            if (!MetaCapi::sendPurchase($order)) {
                self::releaseMetaPurchaseSend($orderId);
            }
        } catch (\Throwable $e) {
            self::releaseMetaPurchaseSend($orderId);
            Logger::error('Meta CAPI purchase failed', ['m' => $e->getMessage()]);
        }
    }

    public static function mapMollieStatus(string $status): string
    {
        return match ($status) {
            'paid' => self::PAYMENT_PAID,
            'failed' => self::PAYMENT_FAILED,
            'canceled', 'cancelled' => self::PAYMENT_CANCELED,
            'expired' => self::PAYMENT_EXPIRED,
            'refunded' => self::PAYMENT_REFUNDED,
            default => self::PAYMENT_PENDING,
        };
    }

    public static function sendPaidEmails(array $order): void
    {
        if (empty($order['customer_email_sent_at'])) {
            $subject = 'Je bestelling van het boek ' . Config::string('BOOK_TITLE') . ' is bevestigd';
            [$html, $text] = EmailTemplates::orderPaidCustomer($order);
            if (Mailer::sendOnce('order_paid_customer:' . $order['id'], (string) $order['email'], $subject, $html, $text)) {
                Database::pdo()->prepare('UPDATE book_orders SET customer_email_sent_at = NOW() WHERE id = ? AND customer_email_sent_at IS NULL')
                    ->execute([$order['id']]);
            }
        }

        if (empty($order['admin_email_sent_at'])) {
            $subject = 'Nieuwe betaalde bestelling ' . Config::string('BOOK_TITLE') . ' – ' . $order['public_order_number'];
            [$html, $text] = EmailTemplates::orderPaidAdmin($order);
            $admin = \Grippartner\Mailer::adminEmail();
            if (Mailer::sendOnce('order_paid_admin:' . $order['id'], $admin, $subject, $html, $text)) {
                Database::pdo()->prepare('UPDATE book_orders SET admin_email_sent_at = NOW() WHERE id = ? AND admin_email_sent_at IS NULL')
                    ->execute([$order['id']]);
            }
        }
    }

    /** Admin-mail: iemand startte checkout maar Mollie startte niet. */
    public static function notifyAdminPaymentStartFailed(array $order, string $reason): void
    {
        $admin = Mailer::adminEmail();
        $subject = 'Betaling mislukt te starten – ' . ($order['public_order_number'] ?? '?');
        [$html, $text] = EmailTemplates::orderPaymentStartFailedAdmin($order, $reason);
        Mailer::sendOnce(
            'order_pay_start_failed:' . ($order['id'] ?? 0) . ':' . substr(sha1($reason), 0, 8),
            $admin,
            $subject,
            $html,
            $text
        );
    }

    /** Admin-mail: nieuwe bestelling aangemaakt, wacht op betaling. */
    public static function notifyAdminOrderPending(array $order): void
    {
        $admin = Mailer::adminEmail();
        $subject = 'Nieuwe bestelling (wacht op betaling) – ' . ($order['public_order_number'] ?? '?');
        [$html, $text] = EmailTemplates::orderPendingAdmin($order);
        Mailer::sendOnce(
            'order_pending_admin:' . ($order['id'] ?? 0),
            $admin,
            $subject,
            $html,
            $text
        );
    }

    /** Haal status opnieuw op bij Mollie (voor terugkeerpagina / als webhook traag is). */
    public static function syncPaymentFromMollie(array $order): ?array
    {
        $paymentId = trim((string) ($order['mollie_payment_id'] ?? ''));
        if ($paymentId === '') {
            return $order;
        }
        try {
            self::processMollieWebhook($paymentId);
        } catch (\Throwable $e) {
            Logger::error('Mollie sync failed', ['m' => $e->getMessage(), 'payment' => $paymentId]);
        }
        return self::findById((int) $order['id']) ?? $order;
    }

    public static function markShipped(int $orderId, bool $sendEmail = true): void
    {
        $pdo = Database::pdo();
        $order = self::findById($orderId);
        if (!$order) {
            throw new \RuntimeException('Bestelling niet gevonden.');
        }
        if (!in_array($order['payment_status'], [self::PAYMENT_PAID, self::PAYMENT_NA], true)) {
            throw new \RuntimeException('Alleen betaalde of gratis bestellingen kunnen verzonden worden.');
        }
        if ($order['fulfilment_status'] === self::FULFILMENT_SHIPPED) {
            return;
        }

        $pdo->prepare(
            'UPDATE book_orders SET fulfilment_status = ?, shipped_at = NOW() WHERE id = ?'
        )->execute([self::FULFILMENT_SHIPPED, $orderId]);

        if ($order['source'] === 'approved_promo' && !empty($order['promo_application_id'])) {
            PromoService::markShippedFromOrder((int) $order['promo_application_id'], $orderId);
        }

        if ($sendEmail) {
            $fresh = self::findById($orderId);
            if ($fresh && empty($fresh['shipped_email_sent_at'])) {
                $subject = 'Je exemplaar van ' . Config::string('BOOK_TITLE') . ' is verzonden';
                [$html, $text] = EmailTemplates::orderShipped($fresh);
                if (Mailer::sendOnce('order_shipped:' . $orderId, (string) $fresh['email'], $subject, $html, $text)) {
                    $pdo->prepare('UPDATE book_orders SET shipped_email_sent_at = NOW() WHERE id = ?')->execute([$orderId]);
                }
            }
        }
    }

    /** @return array{paid_orders:int,paid_books:int,paid_revenue_cents:int,to_ship_books:int} */
    public static function dashboardStats(): array
    {
        $pdo = Database::pdo();
        $paid = $pdo->query(
            "SELECT COUNT(*) AS c, COALESCE(SUM(quantity),0) AS q, COALESCE(SUM(total_cents),0) AS t
             FROM book_orders WHERE payment_status = 'paid' AND source = 'mollie'"
        )->fetch() ?: ['c' => 0, 'q' => 0, 't' => 0];

        $ship = $pdo->query(
            "SELECT COALESCE(SUM(quantity),0) AS q FROM book_orders
             WHERE fulfilment_status = 'ready_to_ship'
               AND payment_status IN ('paid','not_applicable')"
        )->fetch() ?: ['q' => 0];

        return [
            'paid_orders' => (int) $paid['c'],
            'paid_books' => (int) $paid['q'],
            'paid_revenue_cents' => (int) $paid['t'],
            'to_ship_books' => (int) $ship['q'],
        ];
    }

    public static function formatAddress(array $order): string
    {
        $line = $order['street'] . ' ' . $order['house_number'];
        if (!empty($order['house_addition'])) {
            $line .= ' ' . $order['house_addition'];
        }
        $line .= "\n" . $order['postal_code'] . ' ' . $order['city'];
        $line .= "\n" . $order['country'];
        return $line;
    }
}

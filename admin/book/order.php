<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\OrderService;
use Grippartner\Security;

Auth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$order = OrderService::findById($id);
if (!$order) {
    http_response_code(404);
    echo 'Bestelling niet gevonden.';
    exit;
}

$paymentLabels = [
    OrderService::PAYMENT_PENDING => 'Wacht op betaling',
    OrderService::PAYMENT_PAID => 'Betaald',
    OrderService::PAYMENT_FAILED => 'Mislukt',
    OrderService::PAYMENT_CANCELED => 'Geannuleerd',
    OrderService::PAYMENT_EXPIRED => 'Verlopen',
    OrderService::PAYMENT_REFUNDED => 'Terugbetaald',
    OrderService::PAYMENT_NA => 'N.v.t. (promo)',
];

$fulfilmentLabels = [
    OrderService::FULFILMENT_AWAITING => 'Wacht op betaling',
    OrderService::FULFILMENT_READY => 'Klaar om te verzenden',
    OrderService::FULFILMENT_SHIPPED => 'Verzonden',
];

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_note') {
            $note = trim((string) ($_POST['internal_note'] ?? ''));
            Database::pdo()->prepare('UPDATE book_orders SET internal_note = ? WHERE id = ?')
                ->execute([$note === '' ? null : mb_substr($note, 0, 5000), $id]);
            $_SESSION['flash'] = 'Interne notitie opgeslagen.';
        } elseif ($action === 'mark_shipped') {
            $sendEmail = !empty($_POST['send_email']);
            OrderService::markShipped($id, $sendEmail);
            $_SESSION['flash'] = 'Bestelling gemarkeerd als verzonden.';
        } else {
            $_SESSION['flash_error'] = 'Onbekende actie.';
        }
    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    redirect('/admin/book/order.php?id=' . $id);
}

$order = OrderService::findById($id) ?? $order;
$canShip = in_array($order['payment_status'], [OrderService::PAYMENT_PAID, OrderService::PAYMENT_NA], true)
    && $order['fulfilment_status'] !== OrderService::FULFILMENT_SHIPPED;

$pageTitle = 'Bestelling ' . $order['public_order_number'];
require __DIR__ . '/_layout_start.php';
?>
<p><a href="/admin/book/orders.php">← Bestellingen</a></p>
<h1><?= e((string) $order['public_order_number']) ?></h1>

<?php if ($order['source'] === 'approved_promo'): ?>
  <p><span class="badge">Promo / gratis exemplaar</span>
  <?php if (!empty($order['promo_application_id'])): ?>
    — <a href="/admin/book/promo.php?id=<?= (int) $order['promo_application_id'] ?>">Promo-aanvraag #<?= (int) $order['promo_application_id'] ?></a>
  <?php endif; ?>
  </p>
<?php endif; ?>

<table class="data" style="margin:1rem 0">
  <tbody>
    <tr><th>Naam</th><td><?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?></td></tr>
    <tr><th>E-mail</th><td><a href="mailto:<?= e((string) $order['email']) ?>"><?= e((string) $order['email']) ?></a></td></tr>
    <tr><th>Adres</th><td><pre style="margin:0;font:inherit;white-space:pre-wrap"><?= e(OrderService::formatAddress($order)) ?></pre></td></tr>
    <tr><th>Aantal</th><td><?= (int) $order['quantity'] ?></td></tr>
    <tr><th>Prijs / stuk</th><td><?= e(money_cents((int) $order['unit_price_cents'], (string) $order['currency'])) ?></td></tr>
    <tr><th>Totaal</th><td><?= e(money_cents((int) $order['total_cents'], (string) $order['currency'])) ?></td></tr>
    <tr><th>Bron</th><td><?= e((string) $order['source']) ?></td></tr>
    <tr><th>Betaling</th><td><?= e($paymentLabels[$order['payment_status']] ?? (string) $order['payment_status']) ?></td></tr>
    <tr><th>Verzending</th><td><?= e($fulfilmentLabels[$order['fulfilment_status']] ?? (string) $order['fulfilment_status']) ?></td></tr>
    <tr><th>Mollie-id</th><td><?= e((string) ($order['mollie_payment_id'] ?? '—')) ?></td></tr>
    <tr><th>Marketingtoestemming</th><td><?= (int) $order['marketing_consent'] === 1 ? 'Ja' : 'Nee' ?><?= !empty($order['marketing_consent_at']) ? ' (' . e((string) $order['marketing_consent_at']) . ')' : '' ?></td></tr>
    <tr><th>Aangemaakt</th><td><?= e((string) $order['created_at']) ?></td></tr>
    <tr><th>Betaald op</th><td><?= e((string) ($order['paid_at'] ?? '—')) ?></td></tr>
    <tr><th>Verzonden op</th><td><?= e((string) ($order['shipped_at'] ?? '—')) ?></td></tr>
    <tr><th>Klantmail bevestiging</th><td><?= e((string) ($order['customer_email_sent_at'] ?? '—')) ?></td></tr>
    <tr><th>Adminmail</th><td><?= e((string) ($order['admin_email_sent_at'] ?? '—')) ?></td></tr>
    <tr><th>Verzendmail</th><td><?= e((string) ($order['shipped_email_sent_at'] ?? '—')) ?></td></tr>
  </tbody>
</table>

<section class="form-card" style="max-width:100%;margin-bottom:1.25rem">
  <h2 style="margin-top:0;font-size:1.05rem">Interne notitie</h2>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save_note">
    <label>
      <textarea name="internal_note" rows="4"><?= e((string) ($order['internal_note'] ?? '')) ?></textarea>
    </label>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-primary">Notitie opslaan</button></p>
  </form>
</section>

<?php if ($canShip): ?>
<section class="form-card" style="max-width:100%">
  <h2 style="margin-top:0;font-size:1.05rem">Markeer als verzonden</h2>
  <form method="post" onsubmit="return confirm('Bestelling als verzonden markeren?');">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="mark_shipped">
    <label class="check">
      <input type="checkbox" name="send_email" value="1" checked>
      <span>Verzendbevestiging per e-mail sturen</span>
    </label>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-primary">Verzonden</button></p>
  </form>
</section>
<?php elseif ($order['fulfilment_status'] === OrderService::FULFILMENT_SHIPPED): ?>
<p class="status-box status-ok">Deze bestelling is al verzonden.</p>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

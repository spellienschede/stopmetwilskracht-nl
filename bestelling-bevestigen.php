<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MetaCapi;
use Grippartner\OrderService;
use Grippartner\Security;

require __DIR__ . '/src/bootstrap.php';

MetaCapi::captureBrowserIds();
capture_campaign_attribution();

$orderId = (int) ($_SESSION['checkout_order_id'] ?? 0);
if (Config::isActionSite() && $orderId === 0) {
    redirect(Config::orderUrl());
}
$order = $orderId ? OrderService::findById($orderId) : null;
if (!$order || $order['payment_status'] !== OrderService::PAYMENT_PENDING) {
    redirect('/bestellen.php');
}

// Persist click IDs on the order for CAPI matching after Mollie return.
OrderService::attachMetaClickIds($order, $_SESSION['meta_fbp'] ?? null, $_SESSION['meta_fbc'] ?? null);
$order = OrderService::findById($orderId) ?? $order;

// Fire InitiateCheckout once via CAPI when order reaches confirm (browser uses same eventID).
$icEventId = 'ic:' . $order['public_order_number'];
if (empty($order['meta_ic_sent_at'])) {
    try {
        if (MetaCapi::sendInitiateCheckout($order)) {
            OrderService::markMetaIcSent((int) $order['id']);
        }
    } catch (Throwable $e) {
        \Grippartner\Logger::error('Meta CAPI IC failed', ['m' => $e->getMessage()]);
    }
}

$error = '';
if (is_post()) {
    Security::requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'pay') {
        try {
            $pay = OrderService::startMolliePayment($order);
            // Keep session until paid so customer can retry if Mollie fails.
            redirect($pay['checkout_url']);
        } catch (Throwable $e) {
            \Grippartner\Logger::error('Mollie start failed', ['m' => $e->getMessage()]);
            try {
                OrderService::notifyAdminPaymentStartFailed($order, $e->getMessage());
            } catch (Throwable $mailEx) {
                \Grippartner\Logger::error('Payment-fail admin mail failed', ['m' => $mailEx->getMessage()]);
            }
            $error = 'Betaling kon niet worden gestart. Probeer het opnieuw of mail info@kornepot.nl.';
        }
    } elseif ($action === 'edit') {
        $backToBlackFriday = OrderService::isBlackFridayOrder($order);
        unset($_SESSION['checkout_order_id']);
        redirect($backToBlackFriday ? '/bestellen.php?actie=blackfriday' : '/bestellen.php');
    }
}

$title = 'Controleer je bestelling';
$description = 'Controleer je bestelling vóór betaling.';
$canonical = Config::baseUrl() . '/bestelling-bevestigen.php';
$noindex = true;
$commerce = OrderService::commercePayload($order);
$isBlackFriday = OrderService::isBlackFridayOrder($order);
$metaPixelEvents = [[
    'InitiateCheckout',
    [
        'content_name' => $commerce['content_name'],
        'content_ids' => $commerce['content_ids'],
        'content_type' => 'product',
        'value' => $commerce['value'],
        'currency' => $commerce['currency'],
        'num_items' => $commerce['num_items'],
    ],
    $icEventId,
]];
$gaEvents = [[
    'begin_checkout',
    [
        'currency' => $commerce['currency'],
        'value' => $commerce['value'],
        'items' => [[
            'item_id' => 'stop-met-wilskracht',
            'item_name' => $commerce['content_name'],
            'quantity' => $commerce['num_items'],
            'price' => $commerce['item_price'],
        ]],
    ],
]];

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>Controleer je bestelling</h1>
    <?php if ($error): ?><div class="errors" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="form-card">
      <p><strong>Bestelnummer:</strong> <?= e($order['public_order_number']) ?></p>
      <?php if ($isBlackFriday): ?>
        <p><strong>Black Friday-actie: 2 boeken voor de prijs van 1.</strong> Eén voor jou, één voor je grippartner. Beide gaan in dezelfde zending.</p>
        <p><strong>Aantal:</strong> 2 boeken</p>
        <p><strong>Te betalen:</strong> <?= money_cents((int) $order['total_cents']) ?> (incl. btw en verzending)</p>
      <?php else: ?>
        <p><strong>Aantal:</strong> <?= (int) $order['quantity'] ?> × <?= money_cents((int) $order['unit_price_cents']) ?></p>
        <p><strong>Totaal:</strong> <?= money_cents((int) $order['total_cents']) ?> (incl. btw en verzending)</p>
      <?php endif; ?>
      <p><strong>Verwacht:</strong> <?= e(Config::availabilityText()) ?></p>
      <?php if (Config::isPresaleActive() && !$isBlackFriday): ?>
        <p class="bonus-note"><?= e(Config::presaleBonusText()) ?></p>
      <?php endif; ?>
      <p><strong>Afleveradres</strong><br><?= nl2br(e(OrderService::formatAddress($order))) ?></p>
      <p><strong>E-mail:</strong> <?= e($order['email']) ?></p>
      <p class="muted" style="margin:1rem 0 0">Veilig betalen via Mollie / iDEAL.<br>
        <?= e(Config::string('LEGAL_BUSINESS_NAME')) ?> · KvK <?= e(Config::string('LEGAL_KVK')) ?> · BTW <?= e(Config::string('LEGAL_BTW')) ?></p>
      <form method="post" style="margin-top:1.25rem" id="pay-form">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="pay">
        <button class="btn btn-primary btn-lg" type="submit" id="pay-btn">Betaal <?= money_cents((int) $order['total_cents']) ?> met iDEAL</button>
      </form>
      <form method="post" style="margin-top:0.75rem">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="edit">
        <button class="btn btn-ghost" type="submit">Gegevens wijzigen</button>
      </form>
    </div>
  </div>
</section>
<script>
document.getElementById('pay-form')?.addEventListener('submit', () => {
  if (typeof fbq === 'function') {
    fbq('track', 'AddPaymentInfo', {
      content_ids: ['stop-met-wilskracht'],
      content_type: 'product',
      value: <?= json_encode(((int) $order['total_cents']) / 100) ?>,
      currency: <?= json_encode(Config::string('BOOK_CURRENCY')) ?>
    });
  }
});
</script>
<?php
$content = ob_get_clean();
$hideMobileCta = true;
require __DIR__ . '/templates/layout.php';

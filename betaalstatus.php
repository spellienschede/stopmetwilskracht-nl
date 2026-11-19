<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\OrderService;

require __DIR__ . '/src/bootstrap.php';

$public = trim((string) ($_GET['order'] ?? ''));
$order = $public !== '' ? OrderService::findByPublicNumber($public) : null;

// Bij terugkeer van Mollie: status synchen (belangrijk lokaal zonder webhook)
if (
    $order
    && $order['payment_status'] === OrderService::PAYMENT_PENDING
    && !empty($order['mollie_payment_id'])
) {
    $order = OrderService::syncPaymentFromMollie($order);
}

$title = 'Betaalstatus';
$description = 'Status van je Grippartner-betaling.';
$canonical = Config::baseUrl() . '/betaalstatus.php';
$noindex = true;
$metaPixelEvents = [];
$gaEvents = [];
if ($order && $order['payment_status'] === OrderService::PAYMENT_PAID) {
    $purchaseEventId = OrderService::purchaseEventId($order);
    $commerce = OrderService::commercePayload($order);
    $alreadyTracked = !empty($_SESSION['meta_purchase_tracked'][$order['public_order_number']]);
    if (!$alreadyTracked) {
        $metaPixelEvents = [[
            'Purchase',
            [
                'content_name' => $commerce['content_name'],
                'content_ids' => $commerce['content_ids'],
                'content_type' => 'product',
                'value' => $commerce['value'],
                'currency' => $commerce['currency'],
                'num_items' => $commerce['num_items'],
                'order_id' => (string) $order['public_order_number'],
            ],
            $purchaseEventId,
        ]];
        $gaEvents = [[
            'purchase',
            [
                'transaction_id' => (string) $order['public_order_number'],
                'value' => $commerce['value'],
                'currency' => $commerce['currency'],
                'items' => [[
                    'item_id' => 'stop-met-wilskracht',
                    'item_name' => $commerce['content_name'],
                    'quantity' => $commerce['num_items'],
                    'price' => $commerce['item_price'],
                ]],
            ],
        ]];
        $_SESSION['meta_purchase_tracked'][$order['public_order_number']] = true;
    }
}

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>Betaalstatus</h1>
    <?php if (!$order): ?>
      <div class="status-box status-bad"><p>Bestelling niet gevonden.</p><p><a href="/bestellen.php">Opnieuw bestellen</a></p></div>
    <?php else:
      $status = $order['payment_status'];
      if ($status === OrderService::PAYMENT_PAID): ?>
        <div class="status-box status-ok">
          <h2>Betaling geslaagd</h2>
          <p>Bedankt. Je bestelling <strong><?= e($order['public_order_number']) ?></strong> is bevestigd. Je ontvangt een bevestigingsmail op <?= e($order['email']) ?>.</p>
          <?php if (OrderService::isBlackFridayOrder($order)): ?>
            <p><strong>Black Friday-actie: 2 boeken voor de prijs van 1.</strong> Eén voor jou, één voor je grippartner.</p>
          <?php endif; ?>
          <p>Zodra het boek onderweg is, krijg je bericht.</p>
          <?php if (Config::isPresaleActive() && !OrderService::isBlackFridayOrder($order)): ?>
            <p class="bonus-note"><?= e(Config::presaleBonusText()) ?></p>
          <?php endif; ?>
        </div>
      <?php elseif (in_array($status, [OrderService::PAYMENT_FAILED, OrderService::PAYMENT_CANCELED, OrderService::PAYMENT_EXPIRED], true)): ?>
        <div class="status-box status-bad">
          <h2>Betaling niet voltooid</h2>
          <p>De betaling is mislukt, geannuleerd of verlopen.</p>
          <p><a class="btn btn-primary" href="<?= OrderService::isBlackFridayOrder($order) ? '/bestellen.php?actie=blackfriday' : '/bestellen.php' ?>">Opnieuw proberen</a></p>
        </div>
      <?php else: ?>
        <div class="status-box status-wait">
          <h2>Betaling in behandeling</h2>
          <p>We wachten op de definitieve bevestiging van Mollie. Dit kan even duren. Vernieuw deze pagina over een moment. Je krijgt een mail zodra de betaling rond is.</p>
          <p><a href="?order=<?= e(rawurlencode($order['public_order_number'])) ?>">Status vernieuwen</a></p>
        </div>
      <?php endif; endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

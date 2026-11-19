<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\Database;
use Grippartner\OrderService;

Auth::requireLogin();
OrderService::ensureBlackFridayColumns();

$promo = Config::blackFridayPromoCode();
$pdo = Database::pdo();

$summaryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(total_cents), 0) AS revenue
     FROM book_orders
     WHERE promo = ? AND payment_status = 'paid'"
);
$summaryStmt->execute([$promo]);
$summary = $summaryStmt->fetch() ?: ['orders' => 0, 'revenue' => 0];
$paidOrders = (int) $summary['orders'];
$revenueCents = (int) $summary['revenue'];
$books = $paidOrders * 2;

$splitStmt = $pdo->prepare(
    "SELECT COALESCE(NULLIF(utm_content, ''), '(geen)') AS slot,
            COUNT(*) AS orders,
            COALESCE(SUM(total_cents), 0) AS revenue
     FROM book_orders
     WHERE promo = ? AND payment_status = 'paid'
     GROUP BY slot"
);
$splitStmt->execute([$promo]);
$bySlot = [];
foreach ($splitStmt->fetchAll() as $row) {
    $bySlot[(string) $row['slot']] = [
        'orders' => (int) $row['orders'],
        'revenue' => (int) $row['revenue'],
    ];
}

$slots = [];
for ($i = 1; $i <= 8; $i++) {
    $slots[] = 'bf' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
}
foreach (array_keys($bySlot) as $slot) {
    if (!in_array($slot, $slots, true)) {
        $slots[] = $slot;
    }
}

$pageTitle = 'Black Friday-verkopen';
require __DIR__ . '/_layout_start.php';
?>
<h1>Black Friday-verkopen</h1>
<p>Betaalde bestellingen met actie <code><?= e($promo) ?></code>. Een open of mislukte betaling telt niet mee. Elk betaald order is 2 boeken in één zending.</p>

<div class="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem;margin:1rem 0">
  <div class="stat"><span class="hint">Betaalde orders</span><strong><?= $paidOrders ?></strong></div>
  <div class="stat"><span class="hint">Omzet</span><strong><?= e(money_cents($revenueCents)) ?></strong></div>
  <div class="stat"><span class="hint">Boeken (orders × 2)</span><strong><?= $books ?></strong></div>
</div>

<table class="data">
  <thead>
    <tr>
      <th>Advertentie (utm_content)</th>
      <th>Betaalde orders</th>
      <th>Omzet</th>
      <th>Boeken</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($slots as $slot):
      $row = $bySlot[$slot] ?? ['orders' => 0, 'revenue' => 0];
      ?>
    <tr>
      <td><?= e($slot) ?></td>
      <td><?= (int) $row['orders'] ?></td>
      <td><?= e(money_cents((int) $row['revenue'])) ?></td>
      <td><?= (int) $row['orders'] * 2 ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/_layout_end.php'; ?>

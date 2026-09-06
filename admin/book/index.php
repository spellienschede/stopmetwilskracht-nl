<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\OrderService;
use Grippartner\PresentationService;
use Grippartner\PromoService;
use Grippartner\SessionService;

if (Auth::adminCount() === 0) {
    redirect('/admin/book/setup.php');
}
Auth::requireLogin();

$stats = OrderService::dashboardStats();
$promo = PromoService::dashboardCounts();
$presentationCount = 0;
try {
    $presentationCount = PresentationService::count();
} catch (Throwable $e) {
    $presentationCount = 0;
}
$sessionSignupTotal = 0;
try {
    SessionService::ensureSchema();
    foreach (SessionService::all() as $s) {
        $sessionSignupTotal += (int) ($s['signup_count'] ?? 0);
    }
} catch (Throwable $e) {
    $sessionSignupTotal = 0;
}

$pageTitle = 'Dashboard';
require __DIR__ . '/_layout_start.php';
?>
<h1>Dashboard</h1>

<h2 style="margin-top:1.5rem;font-size:1.1rem">Betaalde bestellingen (geen promo)</h2>
<div class="stats">
  <div class="stat"><span class="hint">Betaalde orders</span><strong><?= (int) $stats['paid_orders'] ?></strong></div>
  <div class="stat"><span class="hint">Betaalde boeken</span><strong><?= (int) $stats['paid_books'] ?></strong></div>
  <div class="stat"><span class="hint">Omzet (betaald)</span><strong><?= e(money_cents((int) $stats['paid_revenue_cents'])) ?></strong></div>
  <div class="stat"><span class="hint">Te verzenden (exemplaren)</span><strong><?= (int) $stats['to_ship_books'] ?></strong></div>
</div>

<p>
  <a class="btn btn-ghost" href="/admin/book/orders.php?status=paid">Bestellingen</a>
  <a class="btn btn-ghost" href="/admin/book/export.php">CSV verzending</a>
  <a class="btn btn-ghost" href="/admin/book/promos.php">Promo-aanvragen</a>
  <a class="btn btn-ghost" href="/admin/book/sessions.php">Sessies (<?= (int) $sessionSignupTotal ?>)</a>
  <a class="btn btn-ghost" href="/admin/book/presentation-signups.php">Boekpresentatie (<?= (int) $presentationCount ?>)</a>
  <a class="btn btn-ghost" href="/admin/book/media-requests.php">Media-aanvragen</a>
</p>

<h2 style="margin-top:2rem;font-size:1.1rem">Promo</h2>
<p class="hint" style="margin-bottom:0.75rem">
  Iemand deelt Grippartner, meldt wat er gedaan is, en jij bevestigt of wijst af.
</p>
<div class="stats">
  <?php
  $promoKeys = [
      PromoService::EXECUTION_SUBMITTED,
      PromoService::EXECUTION_CHANGES,
      PromoService::REWARD_CREATED,
      PromoService::SHIPPED,
      PromoService::EXECUTION_REJECTED,
      PromoService::SUBMITTED,
      PromoService::APPROVED_AWAITING,
  ];
  foreach ($promoKeys as $key):
      $label = PromoService::LABELS[$key] ?? $key;
      $count = (int) ($promo[$key] ?? 0);
  ?>
  <div class="stat">
    <span class="hint"><?= e($label) ?></span>
    <strong><a href="/admin/book/promos.php?status=<?= e(urlencode($key)) ?>" style="color:inherit;text-decoration:none"><?= $count ?></a></strong>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>

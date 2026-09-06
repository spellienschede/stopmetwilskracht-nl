<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\OrderService;

Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$paymentLabels = [
    OrderService::PAYMENT_PENDING => 'Wacht op betaling',
    OrderService::PAYMENT_PAID => 'Betaald',
    OrderService::PAYMENT_FAILED => 'Mislukt',
    OrderService::PAYMENT_CANCELED => 'Geannuleerd',
    OrderService::PAYMENT_EXPIRED => 'Verlopen',
    OrderService::PAYMENT_REFUNDED => 'Terugbetaald',
    OrderService::PAYMENT_NA => 'N.v.t. (promo)',
];

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR public_order_number LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '' && isset($paymentLabels[$status])) {
    $where[] = 'payment_status = ?';
    $params[] = $status;
}

$sqlWhere = implode(' AND ', $where);
$pdo = Database::pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM book_orders WHERE {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;
$stmt = $pdo->prepare(
    "SELECT * FROM book_orders WHERE {$sqlWhere} ORDER BY id DESC LIMIT ? OFFSET ?"
);
foreach ($listParams as $i => $val) {
    $stmt->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
}
$stmt->execute();
$orders = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $limit));

$pageTitle = 'Bestellingen';
require __DIR__ . '/_layout_start.php';
?>
<h1>Bestellingen</h1>

<form method="get" class="form-card" style="max-width:100%;margin:1rem 0">
  <div class="form-grid two">
    <label>Zoeken
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Naam, e-mail of bestelnummer">
    </label>
    <label>Betaalstatus
      <select name="status">
        <option value="">Alle</option>
        <?php foreach ($paymentLabels as $val => $label): ?>
          <option value="<?= e($val) ?>"<?= $status === $val ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p style="margin-top:0.75rem">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="/admin/book/orders.php">Reset</a>
  </p>
</form>

<p class="hint"><?= $total ?> resultaat<?= $total === 1 ? '' : 'en' ?></p>

<table class="data">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>E-mail</th>
      <th>Bron</th>
      <th>Betaling</th>
      <th>Verzending</th>
      <th>Totaal</th>
      <th>Datum</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($orders === []): ?>
    <tr><td colspan="8">Geen bestellingen gevonden.</td></tr>
  <?php else: ?>
    <?php foreach ($orders as $o): ?>
      <tr>
        <td><a href="/admin/book/order.php?id=<?= (int) $o['id'] ?>"><?= e((string) $o['public_order_number']) ?></a></td>
        <td><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></td>
        <td><?= e((string) $o['email']) ?></td>
        <td>
          <?php if ($o['source'] === 'approved_promo'): ?>
            <span class="badge">Promo</span>
          <?php else: ?>
            Mollie
          <?php endif; ?>
        </td>
        <td><?= e($paymentLabels[$o['payment_status']] ?? (string) $o['payment_status']) ?></td>
        <td><?= e((string) $o['fulfilment_status']) ?></td>
        <td><?= e(money_cents((int) $o['total_cents'], (string) $o['currency'])) ?></td>
        <td><?= e((string) $o['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<p style="margin-top:1rem">
  <?php if ($page > 1): ?>
    <a href="?<?= e(http_build_query(['q' => $q, 'status' => $status, 'page' => $page - 1])) ?>">← Vorige</a>
  <?php endif; ?>
  Pagina <?= $page ?> / <?= $totalPages ?>
  <?php if ($page < $totalPages): ?>
    <a href="?<?= e(http_build_query(['q' => $q, 'status' => $status, 'page' => $page + 1])) ?>">Volgende →</a>
  <?php endif; ?>
</p>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

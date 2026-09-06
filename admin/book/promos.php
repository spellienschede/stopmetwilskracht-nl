<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\PromoService;

Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR public_application_number LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '' && isset(PromoService::LABELS[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sqlWhere = implode(' AND ', $where);
$pdo = Database::pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM promo_applications WHERE {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;
$stmt = $pdo->prepare(
    "SELECT * FROM promo_applications WHERE {$sqlWhere} ORDER BY id DESC LIMIT ? OFFSET ?"
);
foreach ($listParams as $i => $val) {
    $stmt->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
}
$stmt->execute();
$apps = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $limit));

$pageTitle = 'Promo-aanvragen';
require __DIR__ . '/_layout_start.php';
?>
<h1>Promo-aanvragen</h1>

<form method="get" class="form-card" style="max-width:100%;margin:1rem 0">
  <div class="form-grid two">
    <label>Zoeken
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Naam, e-mail of aanvraagnummer">
    </label>
    <label>Status
      <select name="status">
        <option value="">Alle</option>
        <?php foreach (PromoService::LABELS as $val => $label): ?>
          <option value="<?= e($val) ?>"<?= $status === $val ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p style="margin-top:0.75rem">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="/admin/book/promos.php">Reset</a>
  </p>
</form>

<p class="hint"><?= $total ?> resultaat<?= $total === 1 ? '' : 'en' ?></p>

<table class="data">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>E-mail</th>
      <th>Status</th>
      <th>Deadline</th>
      <th>Ingediend</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($apps === []): ?>
    <tr><td colspan="6">Geen aanvragen gevonden.</td></tr>
  <?php else: ?>
    <?php foreach ($apps as $a): ?>
      <tr>
        <td><a href="/admin/book/promo.php?id=<?= (int) $a['id'] ?>"><?= e((string) $a['public_application_number']) ?></a></td>
        <td><?= e(trim($a['first_name'] . ' ' . $a['last_name'])) ?></td>
        <td><?= e((string) $a['email']) ?></td>
        <td><span class="badge"><?= e(PromoService::LABELS[$a['status']] ?? (string) $a['status']) ?></span></td>
        <td><?= e((string) ($a['execution_deadline'] ?? '—')) ?></td>
        <td><?= e((string) $a['created_at']) ?></td>
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

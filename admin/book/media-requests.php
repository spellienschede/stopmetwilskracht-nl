<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\MediaKit;
use Grippartner\MediaRequestService;

Auth::requireLogin();

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$type = trim((string) ($_GET['type'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;
$download = isset($_GET['download']);

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(name LIKE ? OR organization LIKE ? OR email LIKE ? OR public_request_number LIKE ? OR message LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($status !== '' && isset(MediaRequestService::STATUS_LABELS[$status])) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($type !== '' && isset(MediaKit::REQUEST_TYPES[$type])) {
    $where[] = 'request_type = ?';
    $params[] = $type;
}

$sqlWhere = implode(' AND ', $where);
$pdo = Database::pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM media_requests WHERE {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;
$stmt = $pdo->prepare(
    "SELECT * FROM media_requests WHERE {$sqlWhere} ORDER BY id DESC LIMIT ? OFFSET ?"
);
foreach ($listParams as $i => $val) {
    $stmt->bindValue($i + 1, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll();
$totalPages = max(1, (int) ceil($total / $limit));

$csvCell = static function (?string $value): string {
    $v = (string) $value;
    if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) {
        return "'" . $v;
    }
    return $v;
};

if ($download) {
    $allStmt = $pdo->prepare("SELECT * FROM media_requests WHERE {$sqlWhere} ORDER BY id DESC");
    $allStmt->execute($params);
    $all = $allStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="grippartner-media-aanvragen-' . date('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'Export mislukt.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Nummer', 'Naam', 'Organisatie', 'E-mail', 'Type', 'Status',
        'Kanaal', 'Datum', 'Bereik', 'Toelichting', 'Aangemaakt', 'Afgehandeld',
    ], ';');
    foreach ($all as $r) {
        fputcsv($out, [
            $csvCell((string) $r['public_request_number']),
            $csvCell((string) $r['name']),
            $csvCell((string) $r['organization']),
            $csvCell((string) $r['email']),
            $csvCell(MediaKit::REQUEST_TYPES[$r['request_type']] ?? (string) $r['request_type']),
            $csvCell(MediaRequestService::STATUS_LABELS[$r['status']] ?? (string) $r['status']),
            $csvCell((string) ($r['channel_url'] ?? '')),
            $csvCell((string) ($r['preferred_date'] ?? '')),
            $csvCell((string) ($r['audience_reach'] ?? '')),
            $csvCell((string) $r['message']),
            $csvCell((string) $r['created_at']),
            $csvCell((string) ($r['handled_at'] ?? '')),
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Media-aanvragen';
require __DIR__ . '/_layout_start.php';
?>
<h1>Media-aanvragen</h1>
<p class="hint">Publieke mediakit: <a href="/media" target="_blank" rel="noopener">/media</a></p>

<form method="get" class="form-card" style="max-width:100%;margin:1rem 0">
  <div class="form-grid two">
    <label>Zoeken
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Naam, medium, e-mail of nummer">
    </label>
    <label>Status
      <select name="status">
        <option value="">Alle</option>
        <?php foreach (MediaRequestService::STATUS_LABELS as $val => $label): ?>
          <option value="<?= e($val) ?>"<?= $status === $val ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <div class="form-grid two" style="margin-top:0.75rem">
    <label>Type
      <select name="type">
        <option value="">Alle</option>
        <?php foreach (MediaKit::REQUEST_TYPES as $val => $label): ?>
          <option value="<?= e($val) ?>"<?= $type === $val ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <p style="margin-top:0.75rem">
    <button type="submit" class="btn btn-primary">Filter</button>
    <a class="btn btn-ghost" href="/admin/book/media-requests.php">Reset</a>
    <a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['q' => $q, 'status' => $status, 'type' => $type, 'download' => '1']))) ?>">CSV-export</a>
  </p>
</form>

<p class="hint"><?= $total ?> resultaat<?= $total === 1 ? '' : 'en' ?></p>

<table class="data">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>Organisatie</th>
      <th>Type</th>
      <th>Status</th>
      <th>Ingediend</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($rows === []): ?>
    <tr><td colspan="6">Geen aanvragen gevonden.</td></tr>
  <?php else: ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/admin/book/media-request.php?id=<?= (int) $r['id'] ?>"><?= e((string) $r['public_request_number']) ?></a></td>
        <td><?= e((string) $r['name']) ?></td>
        <td><?= e((string) $r['organization']) ?></td>
        <td><?= e(MediaKit::REQUEST_TYPES[$r['request_type']] ?? (string) $r['request_type']) ?></td>
        <td><span class="badge"><?= e(MediaRequestService::STATUS_LABELS[$r['status']] ?? (string) $r['status']) ?></span></td>
        <td><?= e((string) $r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
  <p class="hint" style="margin-top:1rem">
    Pagina <?= $page ?> van <?= $totalPages ?>
    <?php if ($page > 1): ?> · <a href="?<?= e(http_build_query(['q' => $q, 'status' => $status, 'type' => $type, 'page' => $page - 1])) ?>">Vorige</a><?php endif; ?>
    <?php if ($page < $totalPages): ?> · <a href="?<?= e(http_build_query(['q' => $q, 'status' => $status, 'type' => $type, 'page' => $page + 1])) ?>">Volgende</a><?php endif; ?>
  </p>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>

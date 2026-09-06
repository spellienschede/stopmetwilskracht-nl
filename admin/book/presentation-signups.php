<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\Database;
use Grippartner\PresentationService;

Auth::requireLogin();
PresentationService::ensureSchema();

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 100;
$offset = ($page - 1) * $limit;
$download = isset($_GET['download']);
$eventDate = Config::string('BOOK_PRESENTATION_DATE', '2026-10-05');

$where = ['event_date = ?'];
$params = [$eventDate];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(name LIKE ? OR email LIKE ? OR public_signup_number LIKE ? OR notes LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}

$sqlWhere = implode(' AND ', $where);
$pdo = Database::pdo();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM presentation_signups WHERE {$sqlWhere}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$listParams = $params;
$listParams[] = $limit;
$listParams[] = $offset;
$stmt = $pdo->prepare(
    "SELECT * FROM presentation_signups WHERE {$sqlWhere} ORDER BY id DESC LIMIT ? OFFSET ?"
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
    $allStmt = $pdo->prepare("SELECT * FROM presentation_signups WHERE {$sqlWhere} ORDER BY id ASC");
    $allStmt->execute($params);
    $all = $allStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="boekpresentatie-aanmeldingen-' . date('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'Export mislukt.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['nummer', 'naam', 'email', 'opmerking', 'event_datum', 'event_tijd', 'aangemeld'], ';');
    foreach ($all as $row) {
        fputcsv($out, [
            $csvCell((string) $row['public_signup_number']),
            $csvCell((string) $row['name']),
            $csvCell((string) $row['email']),
            $csvCell((string) ($row['notes'] ?? '')),
            $csvCell((string) $row['event_date']),
            $csvCell((string) $row['event_time']),
            $csvCell((string) $row['created_at']),
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'Boekpresentatie-aanmeldingen';
require __DIR__ . '/_layout_start.php';
?>
<h1>Boekpresentatie-aanmeldingen</h1>
<p class="hint"><?= e(Config::formatPresentationLabel()) ?> · <?= $total ?> aanmelding<?= $total === 1 ? '' : 'en' ?></p>

<form method="get" style="margin:1rem 0;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
  <label>Zoeken<input type="search" name="q" value="<?= e($q) ?>" placeholder="naam, e-mail, nummer…"></label>
  <button class="btn btn-ghost" type="submit">Filter</button>
  <a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['q' => $q, 'download' => '1']))) ?>">CSV downloaden</a>
</form>

<table class="admin-table">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>E-mail</th>
      <th>Opmerking</th>
      <th>Aangemeld</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="5">Nog geen aanmeldingen.</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['public_signup_number']) ?></td>
          <td><?= e((string) $row['name']) ?></td>
          <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
          <td style="white-space:pre-wrap;max-width:20rem"><?= e((string) ($row['notes'] ?? '—')) ?></td>
          <td><?= e((string) $row['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
  <p style="margin-top:1rem">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php if ($p === $page): ?>
        <strong><?= $p ?></strong>
      <?php else: ?>
        <a href="?<?= e(http_build_query(['q' => $q, 'page' => $p])) ?>"><?= $p ?></a>
      <?php endif; ?>
      <?= $p < $totalPages ? ' · ' : '' ?>
    <?php endfor; ?>
  </p>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

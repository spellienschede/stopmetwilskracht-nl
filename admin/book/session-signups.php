<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\SessionService;

Auth::requireLogin();
SessionService::ensureSchema();

$id = (int) ($_GET['id'] ?? 0);
$session = $id > 0 ? SessionService::findById($id) : null;
if (!$session) {
    $_SESSION['flash_error'] = 'Sessie niet gevonden.';
    redirect('/admin/book/sessions.php');
}

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 100;
$offset = ($page - 1) * $limit;
$download = isset($_GET['download']);

$total = SessionService::countSignupsFiltered((int) $session['id'], $q);
$totalPages = max(1, (int) ceil($total / $limit));

$csvCell = static function (?string $value): string {
    $v = (string) $value;
    if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) {
        return "'" . $v;
    }
    return $v;
};

if ($download) {
    $all = SessionService::listSignups((int) $session['id'], $q, 10000, 0);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sessie-' . preg_replace('/[^a-z0-9\-]+/i', '-', (string) $session['slug']) . '-' . date('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'Export mislukt.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['nummer', 'naam', 'email', 'aangemeld'], ';');
    foreach ($all as $row) {
        fputcsv($out, [
            $csvCell((string) $row['public_signup_number']),
            $csvCell((string) $row['name']),
            $csvCell((string) $row['email']),
            $csvCell((string) $row['created_at']),
        ], ';');
    }
    fclose($out);
    exit;
}

$rows = SessionService::listSignups((int) $session['id'], $q, $limit, $offset);

$pageTitle = 'Aanmeldingen – ' . (string) $session['title'];
require __DIR__ . '/_layout_start.php';
?>
<p><a href="/admin/book/sessions.php">← Alle sessies</a> · <a href="/admin/book/session.php?id=<?= (int) $session['id'] ?>">Bewerk sessie</a></p>
<h1>Aanmeldingen</h1>
<p class="hint"><?= e((string) $session['title']) ?> · <?= e(SessionService::formatWhen($session)) ?> · <?= $total ?> aanmelding<?= $total === 1 ? '' : 'en' ?></p>

<form method="get" style="margin:1rem 0;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:end">
  <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
  <label>Zoeken<input type="search" name="q" value="<?= e($q) ?>" placeholder="naam, e-mail, nummer…"></label>
  <button class="btn btn-ghost" type="submit">Filter</button>
  <a class="btn btn-ghost" href="?<?= e(http_build_query(array_filter(['id' => $id, 'q' => $q, 'download' => '1']))) ?>">CSV downloaden</a>
</form>

<table class="admin-table">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>E-mail</th>
      <th>Aangemeld</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="4">Nog geen aanmeldingen.</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><?= e((string) $row['public_signup_number']) ?></td>
          <td><?= e((string) $row['name']) ?></td>
          <td><a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></td>
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
        <a href="?<?= e(http_build_query(['id' => $id, 'q' => $q, 'page' => $p])) ?>"><?= $p ?></a>
      <?php endif; ?>
      <?= $p < $totalPages ? ' · ' : '' ?>
    <?php endfor; ?>
  </p>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;

Auth::requireLogin();

$filter = (string) ($_GET['filter'] ?? 'ready');
if (!in_array($filter, ['ready', 'all'], true)) {
    $filter = 'ready';
}

$download = isset($_GET['download']);

$pdo = Database::pdo();
if ($filter === 'ready') {
    $sql = "SELECT * FROM book_orders
            WHERE fulfilment_status = 'ready_to_ship'
              AND payment_status IN ('paid','not_applicable')
            ORDER BY id ASC";
} else {
    $sql = "SELECT * FROM book_orders
            WHERE payment_status IN ('paid','not_applicable')
            ORDER BY id ASC";
}
$rows = $pdo->query($sql)->fetchAll();

/**
 * Voorkom CSV-formule-injectie in Excel.
 */
$csvCell = static function (?string $value): string {
    $v = (string) $value;
    if ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) {
        return "'" . $v;
    }
    return $v;
};

if ($download) {
    $filename = 'grippartner-verzending-' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        http_response_code(500);
        echo 'Export mislukt.';
        exit;
    }

    // UTF-8 BOM voor Excel NL
    fwrite($out, "\xEF\xBB\xBF");

    // Nederlandse Excel: scheidingsteken ;
    fputcsv($out, [
        'Bestelnummer',
        'Voornaam',
        'Achternaam',
        'E-mail',
        'Straat',
        'Huisnummer',
        'Toevoeging',
        'Postcode',
        'Plaats',
        'Land',
        'Aantal',
        'Bron',
        'Betaalstatus',
        'Verzendstatus',
    ], ';');

    foreach ($rows as $r) {
        fputcsv($out, [
            $csvCell((string) $r['public_order_number']),
            $csvCell((string) $r['first_name']),
            $csvCell((string) $r['last_name']),
            $csvCell((string) $r['email']),
            $csvCell((string) $r['street']),
            $csvCell((string) $r['house_number']),
            $csvCell((string) ($r['house_addition'] ?? '')),
            $csvCell((string) $r['postal_code']),
            $csvCell((string) $r['city']),
            $csvCell((string) $r['country']),
            (string) (int) $r['quantity'],
            $csvCell((string) $r['source']),
            $csvCell((string) $r['payment_status']),
            $csvCell((string) $r['fulfilment_status']),
        ], ';');
    }
    fclose($out);
    exit;
}

$pageTitle = 'CSV-export verzending';
require __DIR__ . '/_layout_start.php';
?>
<h1>CSV-export voor verzending</h1>
<p>UTF-8 met BOM, puntkomma-gescheiden — geschikt voor Excel NL. Formule-injectie wordt afgevangen.</p>

<form method="get" class="form-card" style="margin:1rem 0">
  <label>Filter
    <select name="filter">
      <option value="ready"<?= $filter === 'ready' ? ' selected' : '' ?>>Klaar om te verzenden (ready_to_ship)</option>
      <option value="all"<?= $filter === 'all' ? ' selected' : '' ?>>Alle betaalde + promo-orders</option>
    </select>
  </label>
  <p style="margin-top:0.75rem">
    <button type="submit" class="btn btn-ghost">Voorbeeld tonen</button>
    <button type="submit" class="btn btn-primary" name="download" value="1">Download CSV</button>
  </p>
</form>

<p class="hint"><?= count($rows) ?> rij<?= count($rows) === 1 ? '' : 'en' ?> in deze export</p>

<table class="data">
  <thead>
    <tr>
      <th>Nummer</th>
      <th>Naam</th>
      <th>Plaats</th>
      <th>Aantal</th>
      <th>Bron</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($rows === []): ?>
    <tr><td colspan="6">Geen rijen voor deze filter.</td></tr>
  <?php else: ?>
    <?php foreach (array_slice($rows, 0, 50) as $r): ?>
      <tr>
        <td><a href="/admin/book/order.php?id=<?= (int) $r['id'] ?>"><?= e((string) $r['public_order_number']) ?></a></td>
        <td><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></td>
        <td><?= e((string) $r['postal_code'] . ' ' . $r['city']) ?></td>
        <td><?= (int) $r['quantity'] ?></td>
        <td><?= $r['source'] === 'approved_promo' ? '<span class="badge">Promo</span>' : 'Mollie' ?></td>
        <td><?= e((string) $r['fulfilment_status']) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
</table>
<?php if (count($rows) > 50): ?>
<p class="hint">Voorbeeld toont de eerste 50 rijen; de download bevat alles.</p>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

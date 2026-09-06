<?php
declare(strict_types=1);

/**
 * One-time (idempotent) migration for Meta CAPI columns.
 * Requires logged-in book admin.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\Security;

Auth::requireLogin();

$message = '';
$error = '';

if (is_post()) {
    Security::requireCsrf();
    try {
        $sqlFile = dirname(__DIR__, 2) . '/migrations/003_meta_capi.sql';
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException('Migratiebestand niet leesbaar.');
        }
        Database::pdo()->exec($sql);
        $message = 'Migratie 003_meta_capi.sql uitgevoerd (idempotent).';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Meta CAPI migratie';
require __DIR__ . '/_layout_start.php';
?>
<h1>Meta CAPI migratie</h1>
<p>Voegt <code>meta_fbp</code>, <code>meta_fbc</code>, <code>meta_purchase_sent_at</code> en <code>meta_ic_sent_at</code> toe aan <code>book_orders</code>.</p>
<?php if ($message): ?><div class="status-box status-ok"><p><?= e($message) ?></p></div><?php endif; ?>
<?php if ($error): ?><div class="errors" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" style="margin-top:1rem">
  <?= Security::csrfField() ?>
  <button class="btn btn-primary" type="submit">Migratie uitvoeren</button>
</form>
<p style="margin-top:1rem"><a href="/admin/book/">Terug naar beheer</a></p>
<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
declare(strict_types=1);

/**
 * Eenmalige installatie na FTP-upload:
 * 3) database-migratie
 * 4) eerste adminaccount
 * 5) Mollie-webhook-URL controleren (wordt per betaling meegestuurd)
 *
 * Open: https://www.grippartner.nl/install.php
 * Verwijder of hernoem dit bestand na afloop (of laat de lock staan).
 */

require __DIR__ . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\Database;
use Grippartner\Security;

$lockFile = app_path('storage/install.lock');
$errors = [];
$messages = [];
$done = is_readable($lockFile);

function gp_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function gp_run_migration(): void
{
    $sqlFile = app_path('migrations/001_book_commerce.sql');
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Migratiebestand ontbreekt.');
    }
    $pdo = Database::pdo();
    // Multi-statement SQL
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $pdo->exec($sql);
}

$hasConfig = is_readable(app_path('config.local.php'));
$webhookUrl = Config::string('MOLLIE_WEBHOOK_URL');
if ($webhookUrl === '') {
    $webhookUrl = Config::baseUrl() . '/webhook-mollie.php';
}
$mollieKey = Config::string('MOLLIE_API_KEY');
$mollieMode = $mollieKey === '' ? 'ontbreekt' : (str_starts_with($mollieKey, 'live_') ? 'live' : (str_starts_with($mollieKey, 'test_') ? 'test' : 'onbekend'));

$dbOk = false;
$migrated = false;
$adminExists = false;
try {
    if ($hasConfig) {
        $pdo = Database::pdo();
        $dbOk = true;
        $migrated = gp_table_exists($pdo, 'book_orders') && gp_table_exists($pdo, 'promo_applications') && gp_table_exists($pdo, 'admins');
        if ($migrated) {
            $adminExists = Auth::adminCount() > 0;
        }
    }
} catch (Throwable $e) {
    $errors[] = 'Databaseverbinding mislukt. Controleer DB_* in config.local.php.';
}

if ($done && $adminExists) {
    // Installatie al afgerond
} elseif (is_post() && !$done) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? 'install');

    try {
        if (!$hasConfig) {
            throw new RuntimeException('Upload eerst config.local.php via FTP.');
        }
        if (!$dbOk) {
            throw new RuntimeException('Geen databaseverbinding.');
        }

        if (!$migrated) {
            gp_run_migration();
            $migrated = true;
            $messages[] = 'Database-migratie uitgevoerd.';
        } else {
            $messages[] = 'Database was al gemigreerd.';
        }

        if ($action === 'install' && Auth::adminCount() === 0) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $password2 = (string) ($_POST['password2'] ?? '');
            if (mb_strlen($username) < 3) {
                throw new RuntimeException('Gebruikersnaam minimaal 3 tekens.');
            }
            if (mb_strlen($password) < 10) {
                throw new RuntimeException('Wachtwoord minimaal 10 tekens.');
            }
            if ($password !== $password2) {
                throw new RuntimeException('Wachtwoorden komen niet overeen.');
            }
            Auth::createAdmin($username, $password);
            $adminExists = true;
            $messages[] = 'Beheerder aangemaakt.';
        } elseif (Auth::adminCount() > 0) {
            $adminExists = true;
            $messages[] = 'Er bestond al een beheerder.';
        }

        // Stap 5: webhook URL vastleggen in lock + check
        $payload = [
            'installed_at' => date('c'),
            'webhook_url' => $webhookUrl,
            'mollie_mode' => $mollieMode,
            'app_base_url' => Config::baseUrl(),
        ];
        if (!is_dir(app_path('storage'))) {
            mkdir(app_path('storage'), 0755, true);
        }
        file_put_contents($lockFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $done = true;
        $messages[] = 'Installatie afgerond. Webhook-URL staat klaar voor Mollie.';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

header('X-Robots-Tag: noindex, nofollow');
?><!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Grippartner installatie</title>
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="admin-body">
  <div class="admin-wrap">
    <h1>Installatie Grippartner</h1>
    <p class="hint">Na FTP-upload: migratie (3), admin (4) en Mollie-webhook-check (5) in één keer.</p>

    <div class="stats" style="margin-top:1rem">
      <div class="stat"><span class="hint">config.local.php</span><strong><?= $hasConfig ? 'OK' : 'Ontbreekt' ?></strong></div>
      <div class="stat"><span class="hint">Database</span><strong><?= $dbOk ? 'OK' : 'Nee' ?></strong></div>
      <div class="stat"><span class="hint">Migratie</span><strong><?= $migrated ? 'OK' : 'Nog niet' ?></strong></div>
      <div class="stat"><span class="hint">Admin</span><strong><?= $adminExists ? 'OK' : 'Nog niet' ?></strong></div>
      <div class="stat"><span class="hint">Mollie key</span><strong><?= e($mollieMode) ?></strong></div>
    </div>

    <?php if ($errors): ?>
      <div class="errors"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($messages): ?>
      <div class="flash"><ul style="margin:0;padding-left:1.1rem"><?php foreach ($messages as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="form-card" style="margin-top:1rem;max-width:40rem">
      <h2 style="font-size:1.1rem">Mollie webhook (stap 5)</h2>
      <p>Bij elke betaling stuurt de site automatisch deze webhook-URL mee naar Mollie:</p>
      <p><code style="word-break:break-all"><?= e($webhookUrl) ?></code></p>
      <p class="hint">Je hoeft in het Mollie-dashboard geen aparte globale webhook te zetten voor iDEAL-betalingen — zolang <code>APP_BASE_URL</code> (of <code>MOLLIE_WEBHOOK_URL</code>) klopt en de URL via HTTPS bereikbaar is. Optioneel: in Mollie → Developers → Webhooks dezelfde URL noteren ter controle.</p>
    </div>

    <?php if ($done && $adminExists): ?>
      <div class="status-box status-ok" style="margin-top:1.25rem">
        <p><strong>Klaar.</strong> Ga naar het beheer of de homepage.</p>
        <p>
          <a class="btn btn-primary" href="/admin/book/login.php">Naar beheerlogin</a>
          <a class="btn btn-ghost" href="/">Naar website</a>
        </p>
        <p class="hint">Tip: verwijder <code>install.php</code> via FTP na afloop, of laat <code>storage/install.lock</code> staan zodat dit formulier niet opnieuw draait.</p>
      </div>
    <?php elseif (!$hasConfig): ?>
      <div class="status-box status-bad" style="margin-top:1.25rem">
        <p>Upload eerst <code>config.local.php</code> via FTP (kopieer van <code>config.local.example.php</code> en vul DB, APP_BASE_URL, Mollie en SMTP in).</p>
      </div>
    <?php else: ?>
      <form method="post" class="form-card" style="margin-top:1.25rem;max-width:40rem" autocomplete="off">
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="install">
        <h2 style="font-size:1.1rem"><?= $adminExists ? 'Migratie afronden' : 'Admin aanmaken + installeren' ?></h2>
        <?php if (!$adminExists): ?>
          <div class="form-grid">
            <label>Gebruikersnaam<input name="username" required minlength="3" value="<?= e((string) ($_POST['username'] ?? '')) ?>"></label>
            <label>Wachtwoord <span class="hint">min. 10 tekens</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <label>Wachtwoord bevestigen<input type="password" name="password2" required minlength="10" autocomplete="new-password"></label>
          </div>
        <?php else: ?>
          <p class="hint">Er is al een admin. Deze knop voert alleen nog ontbrekende migratie/lock uit.</p>
        <?php endif; ?>
        <p style="margin-top:1rem"><button class="btn btn-primary" type="submit">Voer installatie uit (stap 3–5)</button></p>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>

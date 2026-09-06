<?php
declare(strict_types=1);

/**
 * Save Meta CAPI access token to storage/ (outside git).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\Security;

Auth::requireLogin();

$message = '';
$error = '';
$current = Config::string('META_CAPI_ACCESS_TOKEN');
$masked = $current === '' ? '(leeg)' : (substr($current, 0, 8) . '…' . substr($current, -6));

if (is_post()) {
    Security::requireCsrf();
    $token = trim((string) ($_POST['token'] ?? ''));
    $testCode = trim((string) ($_POST['test_code'] ?? ''));
    if ($token === '' || !str_starts_with($token, 'EA')) {
        $error = 'Plak een geldige Meta access token (begint met EA).';
    } else {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $error = 'Kon storage/ niet aanmaken.';
        } else {
            $payload = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'META_CAPI_ACCESS_TOKEN' => $token,
                'META_CAPI_TEST_EVENT_CODE' => $testCode,
            ], true) . ";\n";
            $file = $dir . '/meta_capi_token.php';
            if (file_put_contents($file, $payload) === false) {
                $error = 'Schrijven naar storage/meta_capi_token.php mislukt.';
            } else {
                @chmod($file, 0640);
                $message = 'CAPI-token opgeslagen.';
                $masked = substr($token, 0, 8) . '…' . substr($token, -6);
            }
        }
    }
}

$pageTitle = 'Meta CAPI token';
require __DIR__ . '/_layout_start.php';
?>
<h1>Meta CAPI token</h1>
<p>Huidige token: <code><?= e($masked) ?></code></p>
<?php if ($message): ?><div class="status-box status-ok"><p><?= e($message) ?></p></div><?php endif; ?>
<?php if ($error): ?><div class="errors" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" style="margin-top:1rem" autocomplete="off">
  <?= Security::csrfField() ?>
  <label>Access token
    <textarea name="token" rows="4" required style="width:100%;font-family:monospace"></textarea>
  </label>
  <label style="display:block;margin-top:0.75rem">Test event code <span class="hint">(optioneel)</span>
    <input type="text" name="test_code" value="">
  </label>
  <p style="margin-top:1rem"><button class="btn btn-primary" type="submit">Opslaan</button></p>
</form>
<p style="margin-top:1rem"><a href="/admin/book/migrate-meta-capi.php">Door naar migratie</a> · <a href="/admin/book/">Dashboard</a></p>
<?php require __DIR__ . '/_layout_end.php'; ?>

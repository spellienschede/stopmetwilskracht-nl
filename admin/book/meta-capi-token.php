<?php
declare(strict_types=1);

/**
 * Save Meta CAPI access token to storage/ (outside git).
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\MetaCapi;
use Grippartner\Security;

Auth::requireLogin();

$message = '';
$error = '';
$testResult = '';
$testOk = false;

$mask = static function (string $token): string {
    if ($token === '') {
        return '(leeg)';
    }
    if (strlen($token) < 16) {
        return '••••';
    }
    return substr($token, 0, 8) . '…' . substr($token, -6);
};

$savedToken = Config::string('META_CAPI_ACCESS_TOKEN');
if ($savedToken === '') {
    $savedToken = Config::string('META_CAPI_TOKEN');
}
$savedTest = Config::string('META_CAPI_TEST_EVENT_CODE');
if ($savedTest === '') {
    $savedTest = Config::string('META_TEST_EVENT_CODE');
}
$masked = $mask($savedToken);
$testCodeValue = $savedTest;

$storageDir = dirname(__DIR__, 2) . '/storage';
$storageFile = $storageDir . '/meta_capi_token.php';

$writeToken = static function (string $token, string $testCode) use ($storageDir, $storageFile): ?string {
    if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true) && !is_dir($storageDir)) {
        return 'De map storage/ kon niet worden aangemaakt. Maak die map op de server en geef PHP schrijfrechten.';
    }
    if (!is_writable($storageDir) || (is_file($storageFile) && !is_writable($storageFile))) {
        return 'De map storage/ is niet schrijfbaar. Zet de rechten zodat PHP storage/meta_capi_token.php kan opslaan.';
    }
    $payload = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export([
        'META_CAPI_ACCESS_TOKEN' => $token,
        'META_CAPI_TOKEN' => $token,
        'META_CAPI_TEST_EVENT_CODE' => $testCode,
        'META_TEST_EVENT_CODE' => $testCode,
    ], true) . ";\n";
    if (file_put_contents($storageFile, $payload, LOCK_EX) === false) {
        return 'Schrijven naar storage/meta_capi_token.php mislukt. Controleer of storage/ schrijfbaar is.';
    }
    @chmod($storageFile, 0640);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($storageFile, true);
    }
    return null;
};

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $postedToken = trim((string) ($_POST['token'] ?? ''));
    $testCode = trim((string) ($_POST['test_code'] ?? ''));
    $testCodeValue = $testCode;

    if ($action === 'test') {
        $tokenForTest = $postedToken !== '' ? $postedToken : $savedToken;
        if ($tokenForTest === '' || !str_starts_with($tokenForTest, 'EA')) {
            $error = 'Er is nog geen geldig token. Plak het token (begint met EA) of sla het eerst op.';
        } elseif ($testCode === '') {
            $error = 'Vul een test event code in. Zonder code zou dit een live event worden.';
        } else {
            if ($postedToken !== '' && $postedToken !== $savedToken) {
                $writeError = $writeToken($postedToken, $testCode);
                if ($writeError !== null) {
                    $error = $writeError;
                } else {
                    $savedToken = $postedToken;
                    $masked = $mask($savedToken);
                }
            }
            if ($error === '') {
                $probe = MetaCapi::testWebinarRegistration($testCode);
                $testOk = $probe['ok'];
                $testResult = $probe['detail'];
            }
        }
    } elseif ($postedToken === '' || !str_starts_with($postedToken, 'EA')) {
        $error = 'Plak een geldige Meta access token (begint met EA).';
    } else {
        $writeError = $writeToken($postedToken, $testCode);
        if ($writeError !== null) {
            $error = $writeError;
        } else {
            $message = 'CAPI-token opgeslagen. Het bestand staat buiten git.';
            $savedToken = $postedToken;
            $masked = $mask($savedToken);
        }
    }
}

$pageTitle = 'Meta CAPI token';
require __DIR__ . '/_layout_start.php';
?>
<h1>Meta CAPI token</h1>
<p>Huidige token: <code><?= e($masked) ?></code></p>
<?php if ($message): ?><div class="status-box status-ok"><p><?= e($message) ?></p></div><?php endif; ?>
<?php if ($testResult !== ''): ?><div class="status-box <?= $testOk ? 'status-ok' : '' ?>"><p><?= e($testResult) ?></p></div><?php endif; ?>
<?php if ($error): ?><div class="errors" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" style="margin-top:1rem" autocomplete="off">
  <?= Security::csrfField() ?>
  <label>Access token
    <textarea name="token" rows="4" style="width:100%;font-family:monospace" placeholder="Plak een nieuw token, of laat leeg om het opgeslagen token te testen"></textarea>
  </label>
  <label style="display:block;margin-top:0.75rem">Test event code <span class="hint">(verplicht bij een testevent)</span>
    <input type="text" name="test_code" value="<?= e($testCodeValue) ?>">
  </label>
  <p style="margin-top:1rem">
    <button class="btn btn-primary" type="submit" name="action" value="save">Opslaan</button>
    <button class="btn" type="submit" name="action" value="test">Stuur testevent</button>
  </p>
</form>
<p class="hint">Het testevent is een CompleteRegistration naar dataset 882449387955199. Het token wordt nergens volledig getoond en staat niet in git.</p>
<p style="margin-top:1rem"><a href="/admin/book/migrate-meta-capi.php">Door naar migratie</a> · <a href="/admin/book/">Dashboard</a></p>
<?php require __DIR__ . '/_layout_end.php'; ?>

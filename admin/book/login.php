<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Security;

if (Auth::adminCount() === 0) {
    redirect('/admin/book/setup.php');
}

if (Auth::check()) {
    redirect('/admin/book/');
}

$error = '';

if (is_post()) {
    Security::requireCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (Auth::attempt($username, $password)) {
        redirect('/admin/book/');
    }
    $error = 'Onjuiste gegevens of te veel pogingen. Probeer later opnieuw.';
}

$pageTitle = 'Inloggen';
$showNav = false;
require __DIR__ . '/_layout_start.php';
?>
<h1>Beheer – inloggen</h1>
<p class="hint">Alleen voor geautoriseerde beheerders.</p>

<?php if ($error !== ''): ?>
<div class="errors"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" class="form-card" autocomplete="off" style="margin-top:1rem">
  <?= Security::csrfField() ?>
  <div class="form-grid">
    <label>Gebruikersnaam
      <input type="text" name="username" required autofocus autocomplete="username" value="<?= e((string) ($_POST['username'] ?? '')) ?>">
    </label>
    <label>Wachtwoord
      <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button type="submit" class="btn btn-primary">Inloggen</button>
  </div>
</form>
<?php require __DIR__ . '/_layout_end.php'; ?>

<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Security;

if (Auth::adminCount() > 0) {
    redirect('/admin/book/login.php');
}

$errors = [];

if (is_post()) {
    Security::requireCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password2'] ?? '');

    if ($username === '' || mb_strlen($username) < 3) {
        $errors[] = 'Kies een gebruikersnaam van minimaal 3 tekens.';
    }
    if (mb_strlen($password) < 10) {
        $errors[] = 'Het wachtwoord moet minimaal 10 tekens lang zijn.';
    }
    if ($password !== $password2) {
        $errors[] = 'De wachtwoorden komen niet overeen.';
    }

    if ($errors === []) {
        Auth::createAdmin($username, $password);
        Auth::attempt($username, $password);
        $_SESSION['flash'] = 'Eerste beheerder aangemaakt.';
        redirect('/admin/book/');
    }
}

$pageTitle = 'Eerste beheerder';
$showNav = false;
require __DIR__ . '/_layout_start.php';
?>
<h1>Eerste beheerder aanmaken</h1>
<p>Er is nog geen beheerdersaccount. Maak er één aan om verder te gaan.</p>

<?php if ($errors !== []): ?>
<div class="errors">
  <ul>
    <?php foreach ($errors as $err): ?>
      <li><?= e($err) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<form method="post" class="form-card" autocomplete="off" style="margin-top:1rem">
  <?= Security::csrfField() ?>
  <div class="form-grid">
    <label>Gebruikersnaam
      <input type="text" name="username" required minlength="3" autofocus value="<?= e((string) ($_POST['username'] ?? '')) ?>">
    </label>
    <label>Wachtwoord
      <span class="hint">Minimaal 10 tekens</span>
      <input type="password" name="password" required minlength="10" autocomplete="new-password">
    </label>
    <label>Wachtwoord bevestigen
      <input type="password" name="password2" required minlength="10" autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary">Aanmaken</button>
  </div>
</form>
<?php require __DIR__ . '/_layout_end.php'; ?>

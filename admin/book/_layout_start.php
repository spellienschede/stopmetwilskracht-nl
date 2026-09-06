<?php
declare(strict_types=1);

/** @var string $pageTitle */

use Grippartner\Auth;

$pageTitle = $pageTitle ?? 'Beheer';
$showNav = $showNav ?? Auth::check();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($pageTitle) ?> – Grippartner beheer</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body class="admin-body">
<?php if ($showNav): ?>
<nav class="admin-nav" aria-label="Beheer">
  <strong style="margin-right:0.5rem">Grippartner</strong>
  <a href="/admin/book/">Dashboard</a>
  <a href="/admin/book/orders.php">Bestellingen</a>
  <a href="/admin/book/promos.php">Promo</a>
  <a href="/admin/book/sessions.php">Sessies</a>
  <a href="/admin/book/presentation-signups.php">Boekpresentatie</a>
  <a href="/admin/book/media-requests.php">Media</a>
  <a href="/admin/book/export.php">CSV-export</a>
  <a href="/admin/book/migrate-meta-capi.php">Meta CAPI migratie</a>
  <a href="/admin/book/meta-capi-token.php">Meta CAPI token</a>
  <a href="/admin-nieuwsbrief.php">Nieuwsbrief</a>
  <span style="margin-left:auto;opacity:0.7"><?= e(Auth::username()) ?></span>
  <a href="/admin/book/logout.php">Uitloggen</a>
</nav>
<?php endif; ?>
<div class="admin-wrap">
<?php
if (!empty($_SESSION['flash'])) {
    $flash = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="flash" role="status">' . e($flash) . '</div>';
}
if (!empty($_SESSION['flash_error'])) {
    $flashErr = (string) $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
    echo '<div class="errors" role="alert">' . e($flashErr) . '</div>';
}
?>

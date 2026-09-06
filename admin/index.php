<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Grippartner\Auth;

if (Auth::adminCount() === 0) {
    redirect('/admin/book/setup.php');
}

if (Auth::check()) {
    redirect('/admin/book/');
}

$pageTitle = 'Beheer';
$showNav = false;
require __DIR__ . '/book/_layout_start.php';
?>
<h1>Grippartner beheer</h1>
<p>Kies een onderdeel:</p>
<p>
  <a class="btn btn-primary" href="/admin/book/login.php">Boek &amp; promo</a>
  <a class="btn btn-ghost" href="/admin-nieuwsbrief.php">Nieuwsbrief</a>
</p>
<?php require __DIR__ . '/book/_layout_end.php'; ?>

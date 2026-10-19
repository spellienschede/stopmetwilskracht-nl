<?php
declare(strict_types=1);

use Grippartner\Config;

require __DIR__ . '/src/bootstrap.php';

$open = Config::isPresentationOpen();
$when = Config::formatPresentationLabel();
$zoomUrl = 'https://zoom.us/meeting/register/NFDk7VBMSK6A0Xzk0gB6Gg';

$title = 'Online boekpresentatie – ' . Config::string('BOOK_TITLE');
$description = 'Meld je aan voor de online boekpresentatie van ' . Config::string('BOOK_TITLE')
    . ' op ' . $when . '. Gratis, via Zoom. De link krijg je meteen per mail.';
$canonical = Config::baseUrl() . '/boekpresentatie.php';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:40rem">
    <p class="eyebrow">Online boekpresentatie</p>
    <h1><?= e(Config::string('BOOK_TITLE')) ?></h1>
    <p class="lede">Op <?= e($when) ?> presenteer ik het boek online. Kort, persoonlijk, en met ruimte voor vragen. Gratis.</p>
    <?php if ($open): ?>
      <p>Meld je aan via Zoom. Je krijgt de link meteen per mail.</p>
      <p style="margin-top:1.25rem"><a class="btn btn-primary" href="<?= e($zoomUrl) ?>">Aanmelden via Zoom</a></p>
    <?php else: ?>
      <div class="status-box status-bad">
        <p>Aanmelden voor deze presentatie is gesloten.</p>
        <p><a href="/">Terug naar de homepage</a></p>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

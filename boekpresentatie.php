<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MailingList;

require __DIR__ . '/src/bootstrap.php';

const BP_REPLAY_URL = 'https://www.kornepot.nl/opname/8529b2989d10f490b0e5c8d5';

$replayError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($honeypot !== '') {
        $replayError = 'Dit formulier kon niet worden verstuurd.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $replayError = 'Vul een geldig e-mailadres in.';
    } else {
        try {
            MailingList::subscribe($email, 'lezer', '-', 'boekpresentatie-replay', false);
            header('Location: ' . BP_REPLAY_URL, true, 303);
            exit;
        } catch (Throwable $e) {
            $replayError = 'Opslaan lukte niet. Probeer het nog eens.';
        }
    }
}

$when = Config::formatPresentationLabel();
$title = 'Boekpresentatie terugkijken – ' . Config::string('BOOK_TITLE');
$description = 'Kijk de online boekpresentatie van ' . Config::string('BOOK_TITLE')
    . ' terug. Vul je e-mailadres in voor toegang tot de opname.';
$canonical = Config::baseUrl() . '/boekpresentatie.php';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:40rem">
    <p class="eyebrow">Online boekpresentatie</p>
    <h1><?= e(Config::string('BOOK_TITLE')) ?></h1>
    <p class="lede">Op <?= e($when) ?> presenteerde ik in 25 minuten mijn boek &ldquo;<?= e(Config::string('BOOK_TITLE')) ?>&rdquo;. Terugkijken kost dus 25 minuten of met versneld afspelen nog minder :) De opname kan je hier terugkijken.</p>
    <p>Vul je mailadres in. Dan krijg je de video.</p>
    <?php if ($replayError !== ''): ?>
      <div class="errors"><p><?= e($replayError) ?></p></div>
    <?php endif; ?>
    <form method="post" action="/boekpresentatie.php">
      <p class="hp" style="position:absolute;left:-9999px" aria-hidden="true">
        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </p>
      <div class="form-grid">
        <label>E-mailadres
          <input type="email" name="email" required autocomplete="email" value="<?= e((string) ($_POST['email'] ?? '')) ?>">
        </label>
        <p><button class="btn btn-primary" type="submit">Opname bekijken</button></p>
      </div>
    </form>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

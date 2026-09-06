<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\PresentationService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\Validator;

require __DIR__ . '/src/bootstrap.php';

$errors = [];
$success = false;
$already = false;
$publicNr = '';
$data = Validator::normalizePresentationSignupInput([]);
$open = Config::isPresentationOpen();
$when = Config::formatPresentationLabel();

if (is_post() && $open) {
    Security::requireCsrf();
    if (Security::honeypotFilled()) {
        $success = true;
    } elseif (RateLimiter::tooMany('presentation:' . client_ip(), 8, 3600)) {
        $errors['form'] = 'Te veel aanmeldingen. Probeer later opnieuw.';
    } else {
        $data = Validator::normalizePresentationSignupInput($_POST);
        $errors = Validator::presentationSignup($_POST);
        if ($errors === []) {
            try {
                $row = PresentationService::create($data);
                $success = true;
                $already = !empty($row['_already']);
                $publicNr = (string) ($row['public_signup_number'] ?? '');
            } catch (Throwable $e) {
                \Grippartner\Logger::error('Presentation signup failed', ['m' => $e->getMessage()]);
                $errors['form'] = 'Aanmelding kon niet worden opgeslagen. Probeer het opnieuw.';
            }
        }
    }
}

$title = 'Online boekpresentatie – ' . Config::string('BOOK_TITLE');
$description = 'Meld je aan voor de online boekpresentatie van ' . Config::string('BOOK_TITLE')
    . ' op ' . $when . '. Gratis, live via Zoom/Teams of vergelijkbaar.';
$canonical = Config::baseUrl() . '/boekpresentatie.php';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:40rem">
    <p class="eyebrow">Online boekpresentatie</p>
    <h1><?= e(Config::string('BOOK_TITLE')) ?></h1>
    <p class="lede">Op <?= e($when) ?> presenteer ik het boek online. Kort, persoonlijk, en met ruimte voor vragen. Gratis — meld je hieronder aan, dan stuur ik je de link.</p>

    <?php if ($success): ?>
      <div class="status-box status-ok">
        <?php if ($already): ?>
          <p>Je stond al op de lijst<?= $publicNr !== '' ? ' (nummer ' . e($publicNr) . ')' : '' ?>. De link volgt dichter bij de datum.</p>
        <?php else: ?>
          <p>Bedankt. Je aanmelding is ontvangen<?= $publicNr !== '' ? ' (nummer ' . e($publicNr) . ')' : '' ?>.</p>
          <p>Je krijgt een bevestiging per mail. De online-link stuur ik dichter bij <?= e(Config::formatPresentationDateShort()) ?>.</p>
        <?php endif; ?>
        <p><a href="/">Terug naar de homepage</a></p>
      </div>
    <?php elseif (!$open): ?>
      <div class="status-box status-bad">
        <p>Aanmelden voor deze presentatie is gesloten.</p>
        <p><a href="/">Terug naar de homepage</a></p>
      </div>
    <?php else: ?>
      <div class="form-card" style="margin-top:1.25rem">
        <p><strong>Wanneer:</strong> <?= e($when) ?><br>
          <strong>Waar:</strong> online (link volgt per mail)</p>
        <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post">
          <?= Security::csrfField() ?>
          <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <div class="form-grid">
            <label>Naam<input name="name" required maxlength="160" value="<?= e((string) ($data['name'] ?? '')) ?>" autocomplete="name"></label>
            <label>E-mailadres<input type="email" name="email" required maxlength="255" value="<?= e((string) ($data['email'] ?? '')) ?>" autocomplete="email"></label>
            <label>Opmerking <span class="hint">(optioneel)</span><textarea name="notes" maxlength="2000" rows="3" placeholder="Bijv. een vraag die je wilt stellen…"><?= e((string) ($data['notes'] ?? '')) ?></textarea></label>
          </div>
          <p class="hint" style="margin-top:0.75rem">Met je aanmelding ga je akkoord met het <a href="/privacy.php">privacybeleid</a>. Je ontvangt ook de wekelijkse tip van Korne; uitschrijven kan altijd.</p>
          <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">Ja, ik meld me aan</button></p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

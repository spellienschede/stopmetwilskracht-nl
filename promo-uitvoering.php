<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\PromoService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\View;

require __DIR__ . '/src/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$app = $token !== '' ? PromoService::findByToken($token) : null;
$error = '';
$done = false;

if (!$app || !in_array($app['status'], [PromoService::APPROVED_AWAITING, PromoService::EXECUTION_CHANGES], true)) {
    $error = 'Deze link is ongeldig of je kunt nu geen uitvoering indienen.';
}

$countries = View::countries();
unset($countries['OTHER']);

if (is_post() && $error === '') {
    Security::requireCsrf();
    if (RateLimiter::tooMany('promo_exec:' . client_ip(), 8, 3600)) {
        $error = 'Te veel pogingen. Probeer later opnieuw.';
    } else {
        try {
            PromoService::submitExecution($token, [
                'execution_description' => $_POST['execution_description'] ?? '',
                'execution_performed_on' => $_POST['execution_performed_on'] ?? '',
                'execution_links' => $_POST['execution_links'] ?? '',
                'execution_reach_notes' => $_POST['execution_reach_notes'] ?? '',
                'execution_extra_notes' => $_POST['execution_extra_notes'] ?? '',
                'street' => $_POST['street'] ?? '',
                'house_number' => $_POST['house_number'] ?? '',
                'house_addition' => $_POST['house_addition'] ?? '',
                'postal_code' => $_POST['postal_code'] ?? '',
                'city' => $_POST['city'] ?? '',
                'country' => $_POST['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL'),
            ]);
            $done = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$title = 'Uitvoering doorgeven';
$noindex = true;
$canonical = Config::baseUrl() . '/promo-uitvoering.php';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>Uitvoering doorgeven</h1>
    <?php if ($done): ?>
      <div class="status-box status-ok"><p>Bedankt. Je uitvoering is ontvangen. Korne kijkt ernaar en laat van zich horen.</p></div>
    <?php elseif ($error !== '' && !is_post()): ?>
      <div class="status-box status-bad"><p><?= e($error) ?></p></div>
    <?php else: ?>
      <?php if ($error): ?><div class="errors"><?= e($error) ?></div><?php endif; ?>
      <p>Vertel kort wat je hebt gedaan. Geen uploads nodig: een link is genoeg. Vul ook je afleveradres in voor het gratis exemplaar.</p>
      <?php if (!empty($app['execution_agreement'])): ?>
        <div class="form-card" style="margin-bottom:1rem"><strong>Afspraken</strong><p><?= nl2br(e((string) $app['execution_agreement'])) ?></p></div>
      <?php endif; ?>
      <form method="post" class="form-card">
        <?= Security::csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-grid">
          <label>Wat is er uitgevoerd?<textarea name="execution_description" required maxlength="5000"><?= e((string) ($_POST['execution_description'] ?? '')) ?></textarea></label>
          <label>Wanneer uitgevoerd?<input type="date" name="execution_performed_on" value="<?= e((string) ($_POST['execution_performed_on'] ?? '')) ?>"></label>
          <label>Links naar de promotie<textarea name="execution_links" maxlength="5000" placeholder="https://..."><?= e((string) ($_POST['execution_links'] ?? '')) ?></textarea></label>
          <label>Opmerkingen <span class="hint">(optioneel)</span><textarea name="execution_extra_notes" maxlength="3000"><?= e((string) ($_POST['execution_extra_notes'] ?? '')) ?></textarea></label>
        </div>
        <h2 style="font-size:1.1rem;margin:1.5rem 0 0.75rem">Afleveradres</h2>
        <div class="form-grid two">
          <label>Straat<input name="street" required value="<?= e((string) ($_POST['street'] ?? $app['street'] ?? '')) ?>"></label>
          <label>Huisnummer<input name="house_number" required value="<?= e((string) ($_POST['house_number'] ?? $app['house_number'] ?? '')) ?>"></label>
        </div>
        <div class="form-grid two" style="margin-top:0.95rem">
          <label>Toevoeging<input name="house_addition" value="<?= e((string) ($_POST['house_addition'] ?? $app['house_addition'] ?? '')) ?>"></label>
          <label>Postcode<input name="postal_code" required value="<?= e((string) ($_POST['postal_code'] ?? $app['postal_code'] ?? '')) ?>"></label>
        </div>
        <div class="form-grid two" style="margin-top:0.95rem">
          <label>Woonplaats<input name="city" required value="<?= e((string) ($_POST['city'] ?? $app['city'] ?? '')) ?>"></label>
          <label>Land
            <select name="country" required>
              <?php
                $selectedCountry = (string) ($_POST['country'] ?? $app['country'] ?? Config::string('DEFAULT_COUNTRY', 'NL'));
                foreach ($countries as $code => $label):
              ?>
                <option value="<?= e($code) ?>"<?= $selectedCountry === $code ? ' selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <p style="margin-top:1rem"><button class="btn btn-primary" type="submit">Verstuur uitvoering</button></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

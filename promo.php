<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MetaCapi;
use Grippartner\PromoService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\Validator;
use Grippartner\View;

require __DIR__ . '/src/bootstrap.php';

MetaCapi::captureBrowserIds();

$errors = [];
$success = false;
$publicNr = '';
$metaPixelEvents = [];
$data = Validator::normalizePromoInput([
    'country' => Config::string('DEFAULT_COUNTRY', 'NL'),
]);

if (is_post()) {
    Security::requireCsrf();
    if (Security::honeypotFilled()) {
        $success = true;
    } elseif (RateLimiter::tooMany('promo:' . client_ip(), 5, 3600)) {
        $errors['form'] = 'Te veel aanvragen. Probeer later opnieuw.';
    } else {
        $data = Validator::normalizePromoInput($_POST);
        $errors = Validator::promo($_POST);
        if ($errors === []) {
            try {
                $app = PromoService::createApplication($data);
                $success = true;
                $publicNr = (string) ($app['public_application_number'] ?? '');
                $leadEventId = 'lead:' . $publicNr;
                $alreadyTracked = !empty($_SESSION['meta_lead_tracked'][$publicNr]);
                if ($publicNr !== '' && !$alreadyTracked) {
                    $metaPixelEvents = [[
                        'Lead',
                        [
                            'content_name' => Config::string('BOOK_TITLE'),
                            'content_category' => 'promo',
                            'status' => 'submitted',
                        ],
                        $leadEventId,
                    ]];
                    $_SESSION['meta_lead_tracked'][$publicNr] = true;
                    try {
                        MetaCapi::sendLead($app);
                    } catch (Throwable $capiEx) {
                        \Grippartner\Logger::error('Meta CAPI lead failed', ['m' => $capiEx->getMessage()]);
                    }
                }
            } catch (Throwable $e) {
                \Grippartner\Logger::error('Promo create failed', ['m' => $e->getMessage()]);
                $errors['form'] = 'Aanvraag kon niet worden opgeslagen. Probeer het opnieuw.';
            }
        }
    }
}

$countries = View::countries();
unset($countries['OTHER']);
$title = 'Gratis exemplaar via promo';
$description = 'Heb je ' . Config::string('BOOK_TITLE') . ' al gedeeld? Meld achteraf wat je deed en maak kans op een gratis exemplaar.';
$canonical = Config::baseUrl() . '/promo.php';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>Meld wat je hebt gedaan</h1>
    <?php if ($success): ?>
      <div class="status-box status-ok">
        <p>Bedankt. Je promo is ontvangen<?= $publicNr !== '' ? ' (nummer ' . e($publicNr) . ')' : '' ?>.</p>
        <p>Korne kijkt ernaar. Als het past, sturen we je een gratis exemplaar.</p>
        <p><a href="/">Terug naar de homepage</a></p>
      </div>
    <?php else: ?>
      <p class="lede">We sturen graag een gratis exemplaar als dank voor een échte promotieactie. Het hoeft niet groot of perfect te zijn, maar wel iets waarmee je het boek serieus onder de aandacht brengt bij anderen.</p>
      <p class="lede">Alleen de link of advertentie doorsturen is prima — maar voeg daar altijd iets van jezelf aan toe. Schrijf in je eigen woorden waarom jij dit boek de moeite waard vindt, wat het je heeft gebracht, of voor wie het volgens jou interessant is. Dat persoonlijke stukje maakt het een echte aanbeveling.</p>
      <div class="promo-ideas-box">
        <p><strong>Bijvoorbeeld:</strong></p>
        <ul class="promo-ideas">
          <li>een Instagram-post met je eigen tip of ervaring, plus de link</li>
          <li>een verhaal op Instagram waarin je uitlegt waarom je het boek aanraadt</li>
          <li>acht vrienden geappt met je eigen reden waarom het de moeite waard is</li>
          <li>een post op Facebook of X met je persoonlijke aanbeveling</li>
          <li>postertjes opgehangen met je eigen tekst erbij</li>
          <li>of iets anders creatiefs — zolang je eigen woorden erbij staan</li>
        </ul>
      </div>
      <p class="lede" style="margin-top:1.25rem">Vul hieronder in wat je hebt gedaan. Als we zien dat het een mooie bijdrage is, sturen we je graag een gratis exemplaar.</p>
      <div class="form-card" style="margin-top:1.25rem;max-width:32rem">
        <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" id="promo-form">
          <?= Security::csrfField() ?>
          <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <div class="form-grid">
            <label>Naam<input name="name" required maxlength="160" value="<?= e((string) ($data['name'] ?? '')) ?>" autocomplete="name"></label>
            <label>E-mailadres<input type="email" name="email" required maxlength="255" value="<?= e((string) $data['email']) ?>" autocomplete="email"></label>
            <label>Wat heb je gedaan?<textarea name="idea_description" required maxlength="5000" rows="5" placeholder="Bijv. Instagram-post gezet met mijn eigen tip over grippartners, plus de link. Of: 8 vrienden geappt en verteld waarom ik het boek aanraad…"><?= e((string) ($data['idea_description'] ?? '')) ?></textarea></label>
            <label>Link <span class="hint">(optioneel, als je die hebt)</span><input name="promo_link" maxlength="5000" value="<?= e((string) ($data['promo_link'] ?? '')) ?>" placeholder="https://…"></label>
          </div>
          <h2 style="font-size:1.05rem;margin:1.5rem 0 0.75rem">Afleveradres</h2>
          <p class="hint" style="margin-top:0">Voor als we je een gratis exemplaar toesturen.</p>
          <div class="form-grid two">
            <label>Straat<input name="street" required value="<?= e((string) $data['street']) ?>"></label>
            <label>Huisnummer<input name="house_number" required value="<?= e((string) $data['house_number']) ?>"></label>
          </div>
          <div class="form-grid two" style="margin-top:0.95rem">
            <label>Toevoeging<input name="house_addition" value="<?= e((string) ($data['house_addition'] ?? '')) ?>"></label>
            <label>Postcode<input name="postal_code" required value="<?= e((string) $data['postal_code']) ?>"></label>
          </div>
          <div class="form-grid two" style="margin-top:0.95rem">
            <label>Woonplaats<input name="city" required value="<?= e((string) $data['city']) ?>"></label>
            <label>Land
              <select name="country" required>
                <?php foreach ($countries as $code => $label): ?>
                  <option value="<?= e($code) ?>"<?= ($data['country'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>
          <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">Verstuur</button></p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

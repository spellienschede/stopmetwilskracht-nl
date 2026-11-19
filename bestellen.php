<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MetaCapi;
use Grippartner\OrderService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\Validator;
use Grippartner\View;

require __DIR__ . '/src/bootstrap.php';

capture_campaign_attribution();
MetaCapi::captureBrowserIds();

$blackFridayRequested = (($_POST['actie'] ?? $_GET['actie'] ?? '') === 'blackfriday');
$blackFridayLive = $blackFridayRequested && Config::isBlackFridayActive();
$promoNotice = '';
if ($blackFridayRequested && !$blackFridayLive) {
    $promoNotice = 'De Black Friday-actie is afgelopen. Je bestelt nu 1 boek voor de normale prijs.';
}

if (Config::isActionSite() && !$blackFridayRequested) {
    $target = Config::orderUrl();
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if (is_string($qs) && $qs !== '') {
        $target .= (str_contains($target, '?') ? '&' : '?') . $qs;
    }
    redirect($target);
}

$errors = [];
$data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'street' => '',
    'house_number' => '',
    'house_addition' => '',
    'postal_code' => '',
    'city' => '',
    'country' => Config::string('DEFAULT_COUNTRY', 'NL'),
    'quantity' => $blackFridayLive ? 2 : 1,
    'marketing_consent' => false,
    'terms' => false,
];

if (is_post()) {
    Security::requireCsrf();
    if (Security::honeypotFilled()) {
        redirect('/bestellen.php?ok=1');
    }
    if (RateLimiter::tooMany('order:' . client_ip(), 10, 3600)) {
        $errors['form'] = 'Te veel pogingen. Probeer later opnieuw.';
    } else {
        $post = $_POST;
        if ($blackFridayLive) {
            $post['quantity'] = 2;
        } elseif ($blackFridayRequested) {
            $post['quantity'] = 1;
        }
        $data = Validator::normalizeOrderInput($post);
        $data['country'] = 'NL';
        $data = array_merge($data, campaign_attribution_from_request($post));
        if ($blackFridayLive) {
            $data['actie'] = 'blackfriday';
            $data['quantity'] = 2;
        } else {
            $data['actie'] = '';
            if ($blackFridayRequested) {
                $data['quantity'] = 1;
            }
        }
        $errors = Validator::order(array_merge($post, ['country' => 'NL']));
        if ($errors === []) {
            try {
                $order = OrderService::createPendingOrder($data);
                $_SESSION['checkout_order_id'] = (int) $order['id'];
                try {
                    OrderService::notifyAdminOrderPending($order);
                } catch (Throwable $mailEx) {
                    \Grippartner\Logger::error('Pending order admin mail failed', ['m' => $mailEx->getMessage()]);
                }
                redirect('/bestelling-bevestigen.php');
            } catch (Throwable $e) {
                \Grippartner\Logger::error('Order create failed', ['m' => $e->getMessage()]);
                $errors['form'] = 'Bestelling kon niet worden aangemaakt. Probeer het opnieuw.';
            }
        }
    }
}

$countries = View::countries();
unset($countries['OTHER']);
$utm = campaign_attribution_from_request($_GET);
$payPrice = $blackFridayLive ? Config::regularPriceFormatted() : Config::priceFormatted();
$title = ($blackFridayLive ? 'Bestel 1, ontvang 2' : Config::orderCta()) . ' – ' . Config::string('BOOK_TITLE');
$description = $blackFridayLive
    ? 'Black Friday: 2 boeken voor ' . Config::regularPriceFormatted() . ', inclusief btw en verzending.'
    : 'Bestel het boek ' . Config::string('BOOK_TITLE') . '. ' . Config::priceFormatted() . ' incl. btw en verzending.';
$canonical = Config::baseUrl() . '/bestellen.php';
$noindex = true;
$metaPixelEvents = [];
$formAction = $blackFridayRequested ? '/bestellen.php?actie=blackfriday' : '/bestellen.php';
$orderUrlOverride = $blackFridayLive ? $formAction : '';
$navCtaLabel = $blackFridayLive ? 'Bestel 1, ontvang 2' : '';

ob_start();
?>
<?php $orderCover = Config::string('BOOK_COVER_PATH'); ?>
<section class="section order-page" style="border-top:0;padding-top:1.5rem">
  <div class="wrap">
    <div class="order-intro">
      <div class="order-intro-copy">
        <?php if ($blackFridayLive): ?>
          <h1>Bestel 1, ontvang 2</h1>
          <p class="lede">Eén voor jou, één voor je grippartner. <strong><?= e(Config::regularPriceFormatted()) ?></strong> voor 2 boeken · inclusief btw en verzending · <?= e(Config::blackFridayDeadlineText()) ?></p>
        <?php else: ?>
          <h1><?= Config::isPresaleActive() ? 'Nu pre-orderen' : e(Config::orderCta()) ?></h1>
          <?php if (Config::isPresaleActive()): ?>
            <p class="lede">Je bent al bezig met groei — maar je plannen verwateren. Dit boek laat zien wat je mist. <strong><?= e(Config::priceFormatted()) ?></strong> i.p.v. <?= e(Config::regularPriceFormatted()) ?> · inclusief btw en verzending · <?= e(Config::availabilityText()) ?></p>
          <?php else: ?>
            <p class="lede"><?= e(Config::priceFormatted()) ?> per exemplaar · inclusief btw en verzending · <?= e(Config::availabilityText()) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <?php if ($orderCover !== ''): ?>
        <aside class="order-cover">
          <img src="<?= e($orderCover) ?>?v=wilskracht2" alt="Omslag van <?= e(Config::string('BOOK_TITLE')) ?>" width="160" height="240">
        </aside>
      <?php endif; ?>
    </div>
    <div class="form-card" style="margin-top:1.5rem">
      <?php if ($promoNotice !== ''): ?>
        <div class="status-box status-wait" role="status"><p><?= e($promoNotice) ?></p></div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="errors" role="alert"><strong>Controleer het formulier</strong><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" action="<?= e($formAction) ?>" novalidate>
        <?= Security::csrfField() ?>
        <?php if ($blackFridayLive): ?>
          <input type="hidden" name="actie" value="blackfriday">
        <?php endif; ?>
        <?php foreach ($utm as $utmKey => $utmVal): ?>
          <?php if ($utmVal !== null): ?>
            <input type="hidden" name="<?= e($utmKey) ?>" value="<?= e($utmVal) ?>">
          <?php endif; ?>
        <?php endforeach; ?>
        <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-grid two">
          <label>Voornaam<input name="first_name" required maxlength="100" autocomplete="given-name" value="<?= e($data['first_name']) ?>"></label>
          <label>Achternaam<input name="last_name" required maxlength="100" autocomplete="family-name" value="<?= e($data['last_name']) ?>"></label>
        </div>
        <div class="form-grid" style="margin-top:0.95rem">
          <label>E-mailadres<input type="email" name="email" required maxlength="255" autocomplete="email" value="<?= e($data['email']) ?>"></label>
        </div>
        <div class="form-grid two" style="margin-top:0.95rem">
          <label>Straat<input name="street" required maxlength="150" autocomplete="address-line1" value="<?= e($data['street']) ?>"></label>
          <label>Huisnummer<input name="house_number" required maxlength="20" inputmode="numeric" autocomplete="address-line2" value="<?= e($data['house_number']) ?>"></label>
        </div>
        <div class="form-grid two" style="margin-top:0.95rem">
          <label>Toevoeging <span class="hint">(optioneel)</span><input name="house_addition" maxlength="20" value="<?= e((string) ($data['house_addition'] ?? '')) ?>"></label>
          <label>Postcode<input name="postal_code" required maxlength="20" autocomplete="postal-code" value="<?= e($data['postal_code']) ?>"></label>
        </div>
        <div class="form-grid two" style="margin-top:0.95rem">
          <label>Woonplaats<input name="city" required maxlength="100" autocomplete="address-level2" value="<?= e($data['city']) ?>"></label>
          <label>Land
            <select name="country" required>
              <option value="NL" selected>Nederland</option>
            </select>
            <span class="hint">Betalen via iDEAL — alleen NL.</span>
          </label>
        </div>
        <div class="form-grid" style="margin-top:0.95rem">
          <?php if ($blackFridayLive): ?>
            <p><strong>Aantal:</strong> 2 boeken, voor de prijs van 1.</p>
            <input type="hidden" name="quantity" value="2">
          <?php elseif ($blackFridayRequested): ?>
            <p><strong>Aantal:</strong> 1 boek.</p>
            <input type="hidden" name="quantity" value="1">
          <?php else: ?>
            <label>Aantal exemplaren
              <input type="number" name="quantity" min="1" max="50" required value="<?= (int) $data['quantity'] ?>" id="order-qty">
            </label>
          <?php endif; ?>
        </div>
        <p class="muted" style="margin-top:0.75rem">Totaal: <strong id="order-total"><?= e($payPrice) ?></strong> (incl. btw en verzending)</p>
        <p style="margin-top:1.25rem"><button class="btn btn-primary btn-lg" type="submit"><?= $blackFridayLive ? 'Doorgaan naar betaling — ' . e(Config::regularPriceFormatted()) . ' voor 2 boeken' : 'Doorgaan naar betaling — ' . e(Config::priceFormatted()) ?></button></p>
      </form>
    </div>
  </div>
</section>
<?php if (!$blackFridayRequested): ?>
<script>
(() => {
  const qty = document.getElementById('order-qty');
  const total = document.getElementById('order-total');
  const unit = <?= (int) Config::currentPriceCents() ?>;
  if (!qty || !total) return;
  const fmt = (cents) => '€ ' + (cents/100).toFixed(2).replace('.', ',') + ' Euro';
  const refresh = () => {
    const n = Math.max(1, Math.min(50, parseInt(qty.value || '1', 10) || 1));
    total.textContent = fmt(unit * n);
  };
  qty.addEventListener('input', refresh);
  refresh();
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
$hideMobileCta = true;
require __DIR__ . '/templates/layout.php';

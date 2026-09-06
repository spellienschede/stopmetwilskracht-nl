<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\OrderService;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\Validator;
use Grippartner\View;

require __DIR__ . '/src/bootstrap.php';

if (Config::isActionSite()) {
    redirect(Config::orderUrl());
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
    'quantity' => 1,
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
        $data = Validator::normalizeOrderInput($_POST);
        $data['country'] = 'NL';
        $errors = Validator::order(array_merge($_POST, ['country' => 'NL']));
        // Ignore client-side price manipulation: server calculates only
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
unset($countries['OTHER']); // keep ISO-2 only
$title = Config::orderCta() . ' – ' . Config::string('BOOK_TITLE');
$description = 'Bestel het boek ' . Config::string('BOOK_TITLE') . '. ' . Config::priceFormatted() . ' incl. btw en verzending.';
$canonical = Config::baseUrl() . '/bestellen.php';
$noindex = false;
// InitiateCheckout fires on confirm page after order exists (not on bare form view).
$metaPixelEvents = [];
\Grippartner\MetaCapi::captureBrowserIds();

ob_start();
?>
<?php $orderCover = Config::string('BOOK_COVER_PATH'); ?>
<section class="section order-page" style="border-top:0;padding-top:1.5rem">
  <div class="wrap">
    <div class="order-intro">
      <div class="order-intro-copy">
        <h1><?= Config::isPresaleActive() ? 'Nu pre-orderen' : e(Config::orderCta()) ?></h1>
        <?php if (Config::isPresaleActive()): ?>
          <p class="lede">Je bent al bezig met groei — maar je plannen verwateren. Dit boek laat zien wat je mist. <strong><?= e(Config::priceFormatted()) ?></strong> i.p.v. <?= e(Config::regularPriceFormatted()) ?> · inclusief btw en verzending · <?= e(Config::availabilityText()) ?></p>
          <?php
            $countdown = Config::countdownLabel();
            $presaleEndShort = Config::formatPresaleEndDateShort();
          ?>
          <div class="urgency-box">
            <strong><?= e($countdown !== '' ? $countdown : 'Pre-order nu open') ?></strong>
            <p>Tot <?= e($presaleEndShort) ?>: <strong>€ 10 korting</strong> én <strong>2 bonussen</strong>.</p>
            <ol class="bonus-list">
              <?php foreach (Config::presaleBonuses() as $bonus): ?>
                <li>
                  <strong><?= e($bonus['title']) ?></strong>
                  <span><?= e($bonus['text']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php else: ?>
          <p class="lede"><?= e(Config::priceFormatted()) ?> per exemplaar · inclusief btw en verzending · <?= e(Config::availabilityText()) ?></p>
        <?php endif; ?>
      </div>
      <?php if ($orderCover !== ''): ?>
        <aside class="order-cover">
          <img src="<?= e($orderCover) ?>?v=wilskracht2" alt="Omslag van <?= e(Config::string('BOOK_TITLE')) ?>" width="160" height="240">
        </aside>
      <?php endif; ?>
    </div>
    <div class="form-card" style="margin-top:1.5rem">
      <?php if ($errors): ?>
        <div class="errors" role="alert"><strong>Controleer het formulier</strong><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <form method="post" action="/bestellen.php" novalidate>
        <?= Security::csrfField() ?>
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
          <label>Aantal exemplaren
            <input type="number" name="quantity" min="1" max="50" required value="<?= (int) $data['quantity'] ?>" id="order-qty">
          </label>
        </div>
        <p class="muted" style="margin-top:0.75rem">Totaal: <strong id="order-total"><?= e(Config::priceFormatted()) ?></strong> (incl. btw en verzending)</p>
        <p style="margin-top:1.25rem"><button class="btn btn-primary btn-lg" type="submit">Doorgaan naar betaling — <?= e(Config::priceFormatted()) ?></button></p>
      </form>
    </div>
  </div>
</section>
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
<?php
$content = ob_get_clean();
$hideMobileCta = true;
require __DIR__ . '/templates/layout.php';

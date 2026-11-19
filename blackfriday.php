<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\MetaCapi;

require __DIR__ . '/src/bootstrap.php';

capture_campaign_attribution();
MetaCapi::captureBrowserIds();

$active = Config::isBlackFridayActive();
$price = Config::regularPriceFormatted();
$cover = Config::string('BOOK_COVER_PATH');
$book = Config::string('BOOK_TITLE');
$deadline = Config::blackFridayDeadlineText();

$utm = campaign_attribution_from_request($_GET);
$params = array_merge(['actie' => 'blackfriday'], array_filter($utm, static fn ($value) => $value !== null));
$fbclid = $_GET['fbclid'] ?? '';
if (is_string($fbclid) && $fbclid !== '') {
    $params['fbclid'] = mb_substr($fbclid, 0, 200);
}
$orderPath = '/bestellen.php?' . http_build_query($params);

$title = $active
    ? 'Bestel 1, ontvang 2 – ' . $book
    : 'De Black Friday-actie is afgelopen – ' . $book;
$description = $active
    ? 'Bestel 1, ontvang 2. Eén voor jou, één voor je grippartner. ' . $price . ' voor 2 boeken, ' . $deadline . '.'
    : 'De Black Friday-actie van ' . $book . ' is afgelopen.';
$canonical = Config::baseUrl() . '/blackfriday';
$canonicalSelf = true;
$noindex = false;
$hideMobileCta = !$active;
$orderUrlOverride = $active ? $orderPath : '';
$navCtaLabel = $active ? 'Bestel 1, ontvang 2' : '';

$metaPixelEvents = [];
$gaEvents = [];
if ($active) {
    $value = Config::regularPriceCents() / 100;
    $metaPixelEvents[] = [
        'ViewContent',
        [
            'content_name' => 'Black Friday 1+1',
            'content_ids' => ['stop-met-wilskracht'],
            'content_type' => 'product',
            'value' => $value,
            'currency' => 'EUR',
        ],
    ];
    $gaEvents[] = [
        'view_item',
        [
            'currency' => 'EUR',
            'value' => $value,
            'items' => [[
                'item_id' => 'stop-met-wilskracht',
                'item_name' => 'Black Friday 1+1',
                'quantity' => 2,
                'price' => (Config::regularPriceCents() / 2) / 100,
            ]],
        ],
    ];
}

ob_start();
?>
<section class="hero-band">
  <div class="wrap hero">
    <div class="hero-copy">
      <?php if ($active): ?>
        <p class="eyebrow">Black Friday · <?= e($deadline) ?></p>
        <h1>Bestel 1, ontvang 2</h1>
        <p class="hook">Eén voor jou, één voor je grippartner.</p>
        <p class="lede"><strong><?= e($price) ?></strong> voor 2 boeken. Inclusief btw en verzending, in één zending.</p>
        <p class="bf-countdown" id="bf-countdown" data-end="<?= e(Config::blackFridayEndsAtAtom()) ?>"><?= e(ucfirst($deadline)) ?>.</p>
        <div class="cta-row">
          <a class="btn btn-primary btn-lg" href="<?= e($orderPath) ?>">Bestel 2 boeken voor <?= e($price) ?></a>
        </div>
      <?php else: ?>
        <p class="eyebrow">Black Friday</p>
        <h1>De Black Friday-actie is afgelopen</h1>
        <p class="lede">De actie liep <?= e(preg_replace('/^nog /', '', $deadline) ?: 't/m maandag 30 november') ?>, 23:59. De 1+1 is niet meer te bestellen.</p>
        <div class="cta-row">
          <a class="btn btn-primary btn-lg" href="<?= e(Config::orderUrl()) ?>">Naar de bestelpagina</a>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($active && $cover !== ''): ?>
      <div class="bf-covers bf-covers-named">
        <figure>
          <img src="<?= e($cover) ?>?v=wilskracht2" alt="Omslag van <?= e($book) ?>, jouw exemplaar" width="200" height="300">
          <figcaption>Voor jou</figcaption>
        </figure>
        <figure>
          <img src="<?= e($cover) ?>?v=wilskracht2" alt="Omslag van <?= e($book) ?>, het exemplaar voor je grippartner" width="200" height="300">
          <figcaption>Voor je grippartner</figcaption>
        </figure>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php if ($active): ?>
<script>
(() => {
  const el = document.getElementById('bf-countdown');
  if (!el) return;
  const end = Date.parse(el.dataset.end || '');
  if (!Number.isFinite(end)) return;
  const tick = () => {
    const left = end - Date.now();
    if (left <= 0) {
      el.textContent = 'De actie is afgelopen.';
      return;
    }
    const total = Math.floor(left / 1000);
    const days = Math.floor(total / 86400);
    const hours = Math.floor((total % 86400) / 3600);
    const mins = Math.floor((total % 3600) / 60);
    const dayLabel = days === 1 ? 'dag' : 'dagen';
    el.textContent = 'Nog ' + days + ' ' + dayLabel + ', ' + hours + ' uur en ' + mins + ' minuten';
  };
  tick();
  window.setInterval(tick, 30000);
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $description */
/** @var string|null $canonical */
/** @var bool $noindex */

use Grippartner\Config;
use Grippartner\Security;

$pageTitle = $title ?? (Config::string('BOOK_TITLE') . ' – boek van ' . Config::string('BOOK_AUTHOR'));
$pageDesc = $description ?? (Config::string('BOOK_HOOK') . ' ' . Config::priceFormatted() . ' inclusief btw en verzending.');
$orderUrl = Config::orderUrl();
$canon = $canonical ?? Config::canonicalForPath('/');
if (Config::isActionSite()) {
    // Always map to Grip; ignore self-canonical from page scripts
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $canon = Config::canonicalForPath(is_string($path) ? $path : '/');
}
$noindex = !empty($noindex) || Config::isActionSite();
$ga = Config::string('GA_MEASUREMENT_ID');
$metaPixelId = Config::string('META_PIXEL_ID');
/** @var list<array{0: string, 1?: array<string, mixed>}> $metaPixelEvents */
$metaPixelEvents = $metaPixelEvents ?? [];
$presaleActive = Config::isPresaleActive();
$released = Config::isReleased();
$presaleEndIso = Config::string('BOOK_PRESALE_END_DATE', '2026-09-14');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <?php if ($ga !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($ga) ?>');</script>
  <?php endif; ?>
  <?php if ($metaPixelId !== ''): ?>
  <!-- Meta Pixel Code -->
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '<?= e($metaPixelId) ?>');
  fbq('track', 'PageView');
  <?php foreach ($metaPixelEvents as $pixelEvent):
      $eventName = (string) ($pixelEvent[0] ?? '');
      if ($eventName === '') {
          continue;
      }
      $eventParams = $pixelEvent[1] ?? null;
      $eventId = $pixelEvent[2] ?? null;
      if (is_array($eventParams) && $eventParams !== []):
          if (is_string($eventId) && $eventId !== ''): ?>
  fbq('track', <?= json_encode($eventName, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($eventParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {eventID: <?= json_encode($eventId, JSON_UNESCAPED_UNICODE) ?>});
  <?php else: ?>
  fbq('track', <?= json_encode($eventName, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($eventParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
  <?php endif; else: ?>
  fbq('track', <?= json_encode($eventName, JSON_UNESCAPED_UNICODE) ?>);
  <?php endif; endforeach; ?>
  </script>
  <noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=<?= e($metaPixelId) ?>&ev=PageView&noscript=1"
  /></noscript>
  <!-- End Meta Pixel Code -->
  <?php endif; ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>">
  <link rel="canonical" href="<?= e($canon) ?>">
  <?php if ($noindex): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
  <meta property="og:type" content="book">
  <meta property="og:locale" content="nl_NL">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDesc) ?>">
  <meta property="og:url" content="<?= e($canon) ?>">
  <meta property="og:site_name" content="<?= e(Config::string('BOOK_TITLE')) ?>">
  <?php
    $ogImage = !empty($ogImage) ? (string) $ogImage : Config::string('BOOK_COVER_PATH');
    if ($ogImage !== ''):
      $ogImageUrl = str_starts_with($ogImage, 'http') ? $ogImage : rtrim(Config::baseUrl(), '/') . $ogImage;
  ?>
  <meta property="og:image" content="<?= e($ogImageUrl) ?>">
  <meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDesc) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,400;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/site.css?v=20260906b">
  <?php if (!empty($extraHead)): ?><?= $extraHead ?><?php endif; ?>
  <script type="application/ld+json">
  <?php
  if (!empty($jsonLd) && is_array($jsonLd)) {
      echo json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  } else {
  $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'Book',
      'name' => Config::string('BOOK_TITLE'),
      'author' => ['@type' => 'Person', 'name' => Config::string('BOOK_AUTHOR')],
      'description' => Config::string('BOOK_SUBTITLE'),
      'inLanguage' => 'nl',
      'datePublished' => Config::string('BOOK_RELEASE_DATE'),
      'offers' => [
          '@type' => 'Offer',
          'priceCurrency' => Config::string('BOOK_CURRENCY'),
          'price' => number_format(Config::currentPriceCents() / 100, 2, '.', ''),
          'availability' => $released ? 'https://schema.org/InStock' : 'https://schema.org/PreOrder',
          'url' => $orderUrl,
      ],
  ];
  $coverPath = Config::string('BOOK_COVER_PATH');
  if ($coverPath !== '') {
      $schema['image'] = str_starts_with($coverPath, 'http')
          ? $coverPath
          : rtrim(Config::baseUrl(), '/') . $coverPath;
  }
  echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
  }
  ?>
  </script>
</head>
<body<?= $presaleActive ? ' data-release-date="' . e($presaleEndIso) . '"' : '' ?><?= !empty($hideMobileCta) ? ' class="no-mobile-cta"' : '' ?>>
<?php
\Grippartner\MetaCapi::captureBrowserIds();
?>
<a class="skip-link" href="#main">Direct naar inhoud</a>
<?php if ($presaleActive): ?>
<div class="urgency-bar" role="status">
  <span class="urgency-bar-text"><?= e(Config::urgencyBarText()) ?></span>
</div>
<?php endif; ?>
<header class="topbar">
  <div class="wrap topbar-inner">
    <a class="brand" href="/"><?= e(Config::string('BOOK_AUTHOR')) ?></a>
    <nav class="nav" aria-label="Hoofd">
      <a class="nav-link-desk" href="/#kopen"><?= e(Config::orderVerb()) ?></a>
      <?php if (Config::isPresentationOpen()): ?>
      <a class="nav-link-desk" href="/boekpresentatie.php">Boekpresentatie</a>
      <?php endif; ?>
      <a class="nav-promo" href="/#promo">Gratis via promo</a>
      <a class="btn btn-primary btn-nav-order" href="<?= e($orderUrl) ?>"><?= $presaleActive ? '€ 10 korting' : e(Config::orderCta()) ?></a>
    </nav>
  </div>
</header>
<main id="main">
  <?= $content ?>
</main>
<footer class="site-footer">
  <div class="wrap">
    <nav aria-label="Footer">
      <a href="<?= e($orderUrl) ?>"><?= e(Config::orderVerb()) ?></a>
      <a href="/boekpresentatie.php">Boekpresentatie</a>
      <a href="/media">Mediakit</a>
      <a href="/#promo">Promo</a>
      <a href="/privacy.php">Privacy</a>
      <a href="/voorwaarden.php">Voorwaarden</a>
      <a href="/tip.php">Wekelijkse tip</a>
      <a href="mailto:info@kornepot.nl">info@kornepot.nl</a>
    </nav>
    <p>© <?= date('Y') ?> <?= e(Config::string('BOOK_AUTHOR')) ?> · <?= e(Config::string('BOOK_TITLE')) ?></p>
  </div>
</footer>
<script src="/assets/js/site.js?v=20260812a" defer></script>
<?php if (empty($hideMobileCta)): ?>
<div class="mobile-cta-bar" aria-label="Snelle acties">
  <a class="btn btn-primary" href="<?= e($orderUrl) ?>">
    <span class="cta-full"><?= $presaleActive ? 'Pre-order € 10 korting' : e(Config::orderCta()) ?></span>
    <span class="cta-short"><?= $presaleActive ? '€ 10 korting' : e(Config::orderVerb()) ?></span>
  </a>
  <a class="btn btn-promo" href="/#promo">
    <span class="cta-full">Gratis boek via promo</span>
    <span class="cta-short">Gratis boek</span>
  </a>
</div>
<?php endif; ?>
</body>
</html>

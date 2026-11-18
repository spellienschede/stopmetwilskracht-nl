<?php
declare(strict_types=1);

use Grippartner\Config;
use Grippartner\Logger;
use Grippartner\Security;
use Grippartner\WebinarService;

require __DIR__ . '/src/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rtrim($path, '/') : '';
$isThanks = str_ends_with($path, '/bedankt');
$isAgenda = str_ends_with($path, '/agenda');

header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

if ($isAgenda) {
    $row = WebinarService::findByToken((string) ($_GET['t'] ?? ''));
    if ($row === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Dit agendapunt is niet gevonden.';
        exit;
    }
    header('Content-Type: text/calendar; charset=UTF-8');
    header('Content-Disposition: attachment; filename="webinar-afspraken.ics"');
    echo WebinarService::ics($row);
    exit;
}

$error = '';
$voornaam = trim((string) ($_POST['voornaam'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isThanks) {
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    if ($honeypot !== '') {
        header('Location: /webinar/bedankt', true, 303);
        exit;
    }
    if (!Security::verifyCsrf((string) ($_POST['_csrf'] ?? ''))) {
        $error = 'Vernieuw de pagina en probeer het nog eens.';
    } else {
        try {
            $result = WebinarService::register($voornaam, $email, client_ip());
        } catch (Throwable $e) {
            Logger::error('Webinar-inschrijving onverwacht', ['error' => $e->getMessage()]);
            $result = ['ok' => false, 'error' => 'Inschrijven lukt op dit moment niet. Probeer het over een paar minuten opnieuw.'];
        }
        if ($result['ok']) {
            header('Location: /webinar/bedankt?t=' . rawurlencode($result['token']), true, 303);
            exit;
        }
        $error = $result['error'];
    }
}

$thanks = $isThanks ? WebinarService::findByToken((string) ($_GET['t'] ?? '')) : null;
$joinUrl = WebinarService::joinUrl();
$pageUrl = rtrim(Config::baseUrl(), '/') . '/webinar';
$title = WebinarService::TITLE . ' | Stop met wilskracht';
$description = 'Voor een afspraak met iemand anders maak je tijd. In 30 minuten laat Korne Pot zien hoe je diezelfde kracht gebruikt voor iets wat jij belangrijk vindt. Dinsdag 1 december 2026, 19:30.';
$ga = Config::string('GA_MEASUREMENT_ID');
$pixelId = Config::string('META_PIXEL_ID');
$pixelEvent = ($isThanks && $thanks !== null) ? (string) $thanks['token'] : '';

$eventLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => WebinarService::TITLE,
    'description' => $description,
    'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'startDate' => '2026-12-01T19:30:00+01:00',
    'endDate' => '2026-12-01T20:00:00+01:00',
    'inLanguage' => 'nl',
    'isAccessibleForFree' => true,
    'url' => $pageUrl,
    'organizer' => [
        '@type' => 'Person',
        'name' => 'Korne Pot',
        'url' => rtrim(Config::baseUrl(), '/') . '/',
    ],
    'location' => [
        '@type' => 'VirtualLocation',
        'url' => $joinUrl !== '' ? $joinUrl : $pageUrl,
    ],
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'EUR',
        'availability' => 'https://schema.org/InStock',
        'url' => $pageUrl . '#inschrijven',
    ],
];
?>
<!doctype html>
<html lang="nl">
<head>
  <?php if ($ga !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',<?= json_encode($ga, JSON_UNESCAPED_UNICODE) ?>);</script>
  <?php endif; ?>
  <?php if ($pixelId !== ''): ?>
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', <?= json_encode($pixelId, JSON_UNESCAPED_UNICODE) ?>);
  fbq('track', 'PageView');
  <?php if ($pixelEvent !== ''): ?>
  fbq('track', 'CompleteRegistration', {}, {eventID: <?= json_encode($pixelEvent, JSON_UNESCAPED_UNICODE) ?>});
  <?php endif; ?>
  </script>
  <?php endif; ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>">
  <meta name="robots" content="noindex, nofollow">
  <link rel="canonical" href="<?= e($pageUrl) ?>">
  <meta property="og:title" content="<?= e(WebinarService::TITLE) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($pageUrl) ?>">
  <meta property="og:image" content="<?= e(rtrim(Config::baseUrl(), '/') . '/assets/img/korne-pot-author.jpg') ?>">
  <meta name="theme-color" content="#161615">
  <link rel="icon" href="/assets/img/cover.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Overpass:wght@500;700;800&amp;display=swap" rel="stylesheet">
  <script type="application/ld+json"><?= json_encode($eventLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <style>
    :root {
      --geel: #fcea10;
      --nacht: #161615;
      --inkt: #2e2e2c;
      --zacht: #5a5a56;
      --lijn: #e4e4e0;
      --grond: #f6f6f4;
      --papier: #fff;
      --groen: #12985f;
      --rood: #9b2c2c;
    }
    * { box-sizing: border-box; }
    html { color-scheme: light; background: var(--grond); }
    body {
      margin: 0;
      min-height: 100%;
      background-color: var(--grond);
      background-image: radial-gradient(640px 220px at 100% 0, rgba(252, 234, 16, .55), transparent 70%);
      background-repeat: no-repeat;
      color: var(--inkt);
      font-family: Overpass, "Segoe UI", sans-serif;
      font-size: 17px;
      line-height: 1.5;
    }
    a { color: inherit; }
    .balk {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      max-width: 1080px;
      margin: 0 auto;
      padding: 1.1rem 1.25rem 0;
    }
    .logo {
      text-decoration: none;
      color: var(--nacht);
      font-weight: 800;
      letter-spacing: -.04em;
      font-size: 1.15rem;
      line-height: 1.1;
    }
    .wanneer {
      margin: 0;
      padding: .45rem .75rem;
      border-radius: 999px;
      background: #fff;
      color: var(--nacht);
      font-size: clamp(.68rem, 2.8vw, .82rem);
      font-weight: 800;
      line-height: 1.2;
      white-space: nowrap;
      box-shadow: inset 0 0 0 1.5px var(--lijn);
    }
    .wrap {
      max-width: 1080px;
      margin: 0 auto;
      padding: 1.5rem 1.25rem 3rem;
      display: grid;
      gap: 1.5rem;
      align-items: start;
    }
    @media (min-width: 880px) {
      .wrap { grid-template-columns: 1.15fr .85fr; gap: 2.5rem; padding-top: 2.2rem; }
    }
    .wenkbrauw {
      margin: 0 0 .6rem;
      color: var(--nacht);
      font-size: .75rem;
      font-weight: 800;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    h1 {
      margin: 0;
      color: var(--nacht);
      font-size: clamp(2.05rem, 4.6vw, 3.15rem);
      line-height: 1.02;
      letter-spacing: -.04em;
      font-weight: 800;
    }
    .citaat {
      margin: .85rem 0 0;
      max-width: 36rem;
      color: var(--inkt);
      font-family: Georgia, "Times New Roman", serif;
      font-size: 1.2rem;
      font-style: italic;
      line-height: 1.35;
    }
    .lead {
      margin: 1rem 0 0;
      max-width: 40rem;
      font-size: 1.08rem;
      color: var(--nacht);
    }
    .persoon {
      display: flex;
      align-items: center;
      gap: .9rem;
      margin: 1.15rem 0 0;
    }
    .persoon img {
      width: 88px;
      height: 88px;
      border-radius: 50%;
      object-fit: cover;
      object-position: center 22%;
      background: #eceae6;
      flex: 0 0 auto;
    }
    .persoon strong {
      display: block;
      color: var(--nacht);
      font-size: 1rem;
      letter-spacing: -.02em;
    }
    .persoon span {
      display: block;
      color: var(--zacht);
      font-size: .92rem;
      font-weight: 500;
    }
    .punten { list-style: none; margin: 1.4rem 0 0; padding: 0; display: grid; gap: .75rem; }
    .punten li {
      display: flex;
      gap: .7rem;
      align-items: flex-start;
      color: var(--nacht);
      font-weight: 700;
    }
    .bonus {
      margin: 1.25rem 0 0;
      max-width: 40rem;
      padding: .9rem 1rem;
      border-radius: 16px;
      background: #fff;
      color: var(--nacht);
      font-weight: 700;
      box-shadow: inset 0 0 0 1.5px var(--lijn);
    }
    .bonus span {
      display: block;
      margin-bottom: .15rem;
      font-size: .75rem;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .vink {
      flex: 0 0 auto;
      width: 1.35rem;
      height: 1.35rem;
      margin-top: .1rem;
      border-radius: 999px;
      background: var(--groen);
      color: #fff;
      display: grid;
      place-items: center;
      font-size: .8rem;
    }
    .kaart {
      position: relative;
      background: var(--papier);
      border-radius: 28px;
      padding: 1.35rem 1.25rem 1.2rem;
      box-shadow: 0 1px 2px rgba(22, 22, 21, .04), 0 18px 40px -24px rgba(22, 22, 21, .45);
    }
    @media (min-width: 880px) {
      .kaart { position: sticky; top: 1rem; padding: 1.5rem 1.4rem 1.3rem; }
    }
    .kaart h2 { margin: 0 0 .35rem; color: var(--nacht); font-size: 1.35rem; letter-spacing: -.03em; }
    .kaart .waarom { margin: 0 0 .85rem; color: var(--nacht); font-size: 1rem; }
    .kaart .sub { margin: 0 0 1rem; color: var(--zacht); font-size: .95rem; }
    .boekje {
      position: relative;
      display: flex;
      align-items: center;
      gap: .85rem;
      margin: .1rem 0 1.1rem;
    }
    .boekje img {
      width: 104px;
      height: auto;
      display: block;
      flex: 0 0 auto;
      border-radius: 3px;
    }
    .sticker {
      position: absolute;
      z-index: 2;
      top: -34px;
      right: -14px;
      width: 116px;
      height: 116px;
      margin: 0;
      padding: .7rem;
      border-radius: 50%;
      background: #f07816;
      color: #fff;
      display: grid;
      place-items: center;
      align-content: center;
      text-align: center;
      font-weight: 800;
      line-height: 1.05;
      box-shadow: 0 10px 18px -8px rgba(176, 62, 0, .6);
      transform: rotate(-16deg);
    }
    .sticker span {
      display: block;
      font-size: 1.2rem;
      letter-spacing: .06em;
      text-transform: uppercase;
    }
    .sticker small {
      display: block;
      max-width: 6.6rem;
      margin-top: .2rem;
      font-size: .62rem;
      font-weight: 800;
      letter-spacing: 0;
      line-height: 1.15;
    }
    .kaart:has(.sticker) .sub { padding-right: 4.2rem; }
    label { display: block; font-size: .82rem; font-weight: 800; color: var(--nacht); margin-bottom: .3rem; }
    .veld { margin: 0 0 .75rem; }
    input[type="text"], input[type="email"] {
      width: 100%;
      border: 1.5px solid var(--lijn);
      background: #fff;
      border-radius: 14px;
      padding: .8rem .85rem;
      font: inherit;
      color: var(--nacht);
    }
    input:focus { outline: 3px solid rgba(22, 22, 21, .18); border-color: var(--nacht); }
    .knop {
      display: inline-flex;
      width: 100%;
      align-items: center;
      justify-content: center;
      margin-top: .35rem;
      border: 0;
      border-radius: 999px;
      padding: .95rem 1.3rem;
      font: inherit;
      font-weight: 800;
      color: var(--nacht);
      cursor: pointer;
      text-decoration: none;
      background: linear-gradient(180deg, #fff36a 0%, var(--geel) 55%, #f0dc00 100%);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.65), 0 2px 0 #d6c200, 0 10px 24px -10px rgba(180, 160, 0, .8);
    }
    .knop:hover { filter: brightness(1.03); }
    .knop:active { transform: translateY(1px); }
    .knop.secundair {
      background: #fff;
      color: var(--nacht);
      box-shadow: inset 0 0 0 1.5px var(--lijn);
    }
    .fijn { margin: .75rem 0 0; color: var(--zacht); font-size: .82rem; }
    .fout {
      margin: 0 0 .8rem;
      padding: .7rem .8rem;
      border-radius: 14px;
      background: #fff1ee;
      color: var(--rood);
      font-weight: 700;
      font-size: .92rem;
    }
    .linkvak {
      margin: .8rem 0 0;
      padding: .85rem .9rem;
      border-radius: 16px;
      background: #fffce8;
      word-break: break-all;
      font-weight: 700;
      color: var(--nacht);
    }
    .acties { display: grid; gap: .6rem; margin-top: 1rem; }
    .voet {
      max-width: 1080px;
      margin: 0 auto;
      padding: 0 1.25rem 2rem;
      color: var(--zacht);
      font-size: .85rem;
    }
    .voet a { color: var(--nacht); font-weight: 700; }
    .honing { position: absolute; left: -9999px; }
  </style>
</head>
<body>
  <header class="balk">
    <a class="logo" href="/">Stop met wilskracht</a>
    <p class="wanneer"><?= e(WebinarService::badge()) ?></p>
  </header>
  <main class="wrap">
    <section>
      <p class="wenkbrauw">Gratis webinar · 30 minuten</p>
      <h1><?= e(WebinarService::TITLE) ?></h1>
      <p class="citaat">„Dat uur dat je voor je eigen plan had geblokt? Dat schuif je makkelijker door.”</p>
      <div class="persoon">
        <img src="/assets/img/korne-pot-author.jpg" width="176" height="176" alt="Korne Pot">
        <p><strong>Korne Pot</strong><span><?= e(WebinarService::captionDate()) ?><br><?= e(WebinarService::captionTime()) ?></span></p>
      </div>
      <p class="lead">Voor een afspraak met iemand anders maak je tijd. Maar dat uur dat je voor je eigen plan had geblokt? Dat schuif je makkelijker door. In dit webinar laat ik je zien hoe je diezelfde kracht van een afspraak gebruikt voor iets wat jij belangrijk vindt.</p>
      <ul class="punten">
        <li><span class="vink" aria-hidden="true">✓</span><span>Waarom een afspraak met iemand anders wél blijft staan</span></li>
        <li><span class="vink" aria-hidden="true">✓</span><span>Waarom het uur voor jezelf zo makkelijk doorschuift</span></li>
        <li><span class="vink" aria-hidden="true">✓</span><span>Hoe je diezelfde kracht gebruikt voor iets wat jij belangrijk vindt</span></li>
      </ul>
      <p class="bonus"><span>Gratis bonus voor aanwezigen</span>De handleiding jaarreview.</p>
    </section>

    <section class="kaart" id="inschrijven">
      <?php if ($isThanks && $thanks !== null): ?>
        <h2>Je bent ingeschreven, <?= e((string) $thanks['first_name']) ?>.</h2>
        <p class="waarom"><?= e(WebinarService::when()) ?>. Online, 30 minuten. Ben je erbij, dan krijg je de handleiding jaarreview, gratis.</p>
        <?php if ((int) $thanks['mail_ok'] === 1): ?>
          <p class="sub">De bevestiging staat in je mail op <?= e((string) $thanks['email']) ?>.</p>
        <?php else: ?>
          <p class="fout">De mail kon niet worden verstuurd. Zet het webinar hieronder in je agenda.</p>
        <?php endif; ?>
        <?php if ($joinUrl !== '' && (int) $thanks['mail_ok'] !== 1): ?>
          <p class="linkvak" id="join-link"><?= e($joinUrl) ?></p>
        <?php endif; ?>
        <div class="acties">
          <?php if ($joinUrl !== '' && (int) $thanks['mail_ok'] !== 1): ?>
            <button class="knop" type="button" id="kopieer">Kopieer mijn link</button>
          <?php endif; ?>
          <a class="knop<?= ($joinUrl !== '' && (int) $thanks['mail_ok'] !== 1) ? ' secundair' : '' ?>" href="/webinar/agenda?t=<?= e((string) $thanks['token']) ?>">Zet in je agenda</a>
          <a class="knop secundair" href="<?= e(WebinarService::googleCalendarUrl($thanks)) ?>">Google-agenda</a>
        </div>
        <?php if ($joinUrl !== '' && (int) $thanks['mail_ok'] !== 1): ?>
        <script>
          document.getElementById('kopieer').addEventListener('click', function () {
            var link = document.getElementById('join-link').textContent.trim();
            var knop = this;
            navigator.clipboard.writeText(link).then(function () {
              knop.textContent = 'Link gekopieerd';
            });
          });
        </script>
        <p class="fijn">Deel je link niet. Hij hoort bij jouw inschrijving.</p>
        <?php endif; ?>
      <?php elseif ($isThanks): ?>
        <h2>Kijk in je mail</h2>
        <p class="waarom">Zet het webinar in je agenda. Dinsdag 1 december, 19:30, 30 minuten.</p>
        <p class="sub">Geen mail ontvangen? <a href="/webinar">Meld je opnieuw aan</a> met hetzelfde adres, dan sturen we de bevestiging nog een keer.</p>
      <?php else: ?>
        <p class="sticker"><span>Bonus</span><small>Handleiding jaarreview</small></p>
        <h2>Meld je gratis aan</h2>
        <p class="sub"><?= e(WebinarService::when()) ?>. Je krijgt meteen een bevestiging.</p>
        <div class="boekje">
          <img src="/assets/img/jaarreview-boekje.jpg" width="681" height="1081" alt="Boekje Jaarreview">
        </div>
        <?php if ($error !== ''): ?>
          <p class="fout" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="/webinar">
          <p class="honing" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
          </p>
          <?= Security::csrfField() ?>
          <p class="veld">
            <label for="voornaam">Voornaam</label>
            <input id="voornaam" name="voornaam" type="text" required maxlength="60" autocomplete="given-name" value="<?= e($voornaam) ?>">
          </p>
          <p class="veld">
            <label for="email">E-mailadres</label>
            <input id="email" name="email" type="email" required maxlength="120" autocomplete="email" value="<?= e($email) ?>">
          </p>
          <button class="knop" type="submit">Meld me gratis aan</button>
        </form>
        <p class="fijn">Gratis. Je mail gebruik ik voor de bevestiging en de link om mee te doen.</p>
      <?php endif; ?>
    </section>
  </main>
  <footer class="voet">
    <p>Stop met wilskracht · webinar van Korne Pot. Vragen: <a href="mailto:info@kornepot.nl">info@kornepot.nl</a> · <a href="/privacy.php">Privacy</a></p>
  </footer>
</body>
</html>

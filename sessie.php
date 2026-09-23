<?php
declare(strict_types=1);

use Grippartner\MetaCapi;
use Grippartner\RateLimiter;
use Grippartner\Security;
use Grippartner\SessionService;
use Grippartner\Validator;

require __DIR__ . '/src/bootstrap.php';

MetaCapi::captureBrowserIds();

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
    if (preg_match('#/sessie/([a-z0-9\-]+)/?#i', $path, $m)) {
        $slug = $m[1];
    }
}

$session = $slug !== '' ? SessionService::findBySlug($slug) : null;
if (!$session || !(int) $session['is_published']) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$errors = [];
$success = false;
$already = false;
$publicNr = '';
$metaPixelEvents = [];
$data = Validator::normalizeSessionSignupInput([]);
$open = SessionService::isOpen($session);
$when = SessionService::formatWhen($session);

if (is_post() && $open) {
    Security::requireCsrf();
    if (Security::honeypotFilled()) {
        $success = true;
    } elseif (RateLimiter::tooMany('session:' . (int) $session['id'] . ':' . client_ip(), 8, 3600)) {
        $errors['form'] = 'Te veel aanmeldingen. Probeer later opnieuw.';
    } else {
        $data = Validator::normalizeSessionSignupInput($_POST);
        $errors = Validator::sessionSignup($_POST);
        if ($errors === []) {
            try {
                $row = SessionService::signup((int) $session['id'], $data);
                $success = true;
                $already = !empty($row['_already']);
                $publicNr = (string) ($row['public_signup_number'] ?? '');
                if ($publicNr !== '' && !$already) {
                    $leadEventId = 'session-lead:' . $publicNr;
                    $tracked = !empty($_SESSION['meta_session_lead_tracked'][$publicNr]);
                    if (!$tracked) {
                        $metaPixelEvents = [[
                            'Lead',
                            [
                                'content_name' => (string) ($session['title'] ?? 'Online sessie'),
                                'content_category' => 'live-session',
                                'content_ids' => [(string) ($session['slug'] ?? '')],
                                'status' => 'submitted',
                            ],
                            $leadEventId,
                        ]];
                        $_SESSION['meta_session_lead_tracked'][$publicNr] = true;
                        try {
                            MetaCapi::sendSessionLead($row, $session);
                        } catch (Throwable $capiEx) {
                            \Grippartner\Logger::error('Meta CAPI session lead failed', ['m' => $capiEx->getMessage()]);
                        }
                    }
                }
            } catch (Throwable $e) {
                \Grippartner\Logger::error('Session signup failed', ['m' => $e->getMessage()]);
                $errors['form'] = 'Aanmelding kon niet worden opgeslagen. Probeer het opnieuw.';
            }
        }
    }
}

$host1Photo = (string) ($session['host1_photo'] ?? '');
$host2Photo = (string) ($session['host2_photo'] ?? '');
$host1Exists = $host1Photo !== '' && is_readable(app_path(ltrim($host1Photo, '/')));
$host2Exists = $host2Photo !== '' && is_readable(app_path(ltrim($host2Photo, '/')));

$title = (string) $session['title'] . ' – online sessie';
$description = trim((string) ($session['intro'] ?? '')) !== ''
    ? (string) $session['intro']
    : ('Meld je aan voor ' . (string) $session['title'] . ' op ' . $when . '. Gratis online sessie.');
$canonical = SessionService::publicUrl($session);
$ogImage = $host2Exists ? $host2Photo : ($host1Exists ? $host1Photo : '');
$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Event',
    'name' => (string) $session['title'],
    'description' => $description,
    'startDate' => str_replace(' ', 'T', (string) $session['starts_at']) . '+02:00',
    'endDate' => !empty($session['ends_at'])
        ? (str_replace(' ', 'T', (string) $session['ends_at']) . '+02:00')
        : null,
    'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
    'eventStatus' => 'https://schema.org/EventScheduled',
    'location' => [
        '@type' => 'VirtualLocation',
        'url' => $canonical,
    ],
    'organizer' => [
        '@type' => 'Person',
        'name' => 'Korné Pot',
        'url' => \Grippartner\Config::baseUrl() . '/',
    ],
    'isAccessibleForFree' => true,
    'url' => $canonical,
];
$jsonLd = array_filter($jsonLd, static fn ($v) => $v !== null);

ob_start();
?>
<section class="section session-page" style="border-top:0;padding-top:2rem">
  <div class="wrap session-layout">
    <div class="session-copy">
      <p class="eyebrow">Gratis online sessie</p>
      <h1><?= e((string) $session['title']) ?></h1>
      <?php if (!empty($session['hosts'])): ?>
        <p class="session-hosts">Met <?= e((string) $session['hosts']) ?></p>
      <?php endif; ?>
      <p class="lede"><?= e((string) ($session['intro'] ?? '')) ?></p>
      <?php
        $cliffhangers = SessionService::cliffhangers($session);
        if ($cliffhangers !== []):
      ?>
        <div class="session-cliffhangers">
          <p class="session-cliffhangers-label"><?= (string) ($session['slug'] ?? '') === '3-geheimen' ? 'Drie geheimen in de sessie:' : 'In deze sessie:' ?></p>
          <ol>
            <?php foreach ($cliffhangers as $hook): ?>
              <li><?= e($hook) ?></li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>
      <ul class="session-bullets">
        <li><strong>Wanneer:</strong> <?= e($when) ?></li>
        <li><strong>Waar:</strong> online via Zoom (link per mail)</li>
        <li><strong>Kosten:</strong> gratis</li>
      </ul>

      <?php if ($host1Exists || $host2Exists): ?>
        <div class="session-hosts-grid" aria-label="Hosts">
          <?php if ($host1Exists): ?>
            <figure class="session-host">
              <img src="<?= e($host1Photo) ?>" alt="<?= e((string) ($session['host1_name'] ?? 'Host')) ?>" width="480" height="480" loading="eager">
              <figcaption>
                <strong><?= e((string) ($session['host1_name'] ?? '')) ?></strong>
                <?php if (!empty($session['host1_role'])): ?><span><?= e((string) $session['host1_role']) ?></span><?php endif; ?>
              </figcaption>
            </figure>
          <?php endif; ?>
          <?php if ($host2Exists): ?>
            <figure class="session-host">
              <img src="<?= e($host2Photo) ?>" alt="<?= e((string) ($session['host2_name'] ?? 'Host')) ?>" width="480" height="480" loading="eager">
              <figcaption>
                <strong><?= e((string) ($session['host2_name'] ?? '')) ?></strong>
                <?php if (!empty($session['host2_role'])): ?><span><?= e((string) $session['host2_role']) ?></span><?php endif; ?>
              </figcaption>
            </figure>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="session-form-col">
      <?php if ($success): ?>
        <div class="status-box status-ok">
          <?php if ($already): ?>
            <p>Je stond al op de lijst<?= $publicNr !== '' ? ' (nummer ' . e($publicNr) . ')' : '' ?>.</p>
          <?php else: ?>
            <p>Top — je bent erbij<?= $publicNr !== '' ? ' (nummer ' . e($publicNr) . ')' : '' ?>.</p>
            <p>Check je mail voor de bevestiging. De Zoom-link volgt dichter bij de sessie.</p>
          <?php endif; ?>
          <p><a href="/">Terug naar de homepage</a></p>
        </div>
      <?php elseif (!$open): ?>
        <div class="status-box status-bad">
          <p>Aanmelden voor deze sessie is gesloten.</p>
          <p><a href="/">Terug naar de homepage</a></p>
        </div>
      <?php else: ?>
        <div class="form-card session-form-card">
          <h2>Meld je aan</h2>
          <p class="hint">Naam + e-mail is genoeg. We sturen je de link.</p>
          <?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <form method="post" action="">
            <?= Security::csrfField() ?>
            <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
            <div class="form-grid">
              <label>Naam<input name="name" required maxlength="160" value="<?= e((string) ($data['name'] ?? '')) ?>" autocomplete="name"></label>
              <label>E-mailadres<input type="email" name="email" required maxlength="255" value="<?= e((string) ($data['email'] ?? '')) ?>" autocomplete="email"></label>
            </div>
            <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">Ja, ik wil erbij zijn</button></p>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

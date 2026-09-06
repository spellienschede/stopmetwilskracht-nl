<?php
declare(strict_types=1);

use Grippartner\PromoService;
use Grippartner\RateLimiter;
use Grippartner\Security;

require __DIR__ . '/src/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$app = $token !== '' ? PromoService::findByToken($token) : null;
$error = '';
$done = false;

if (!$app || $app['status'] !== PromoService::CHANGES_REQUESTED) {
    $error = 'Deze link is ongeldig of je kunt het idee nu niet aanpassen.';
}

if (is_post() && $error === '') {
    Security::requireCsrf();
    if (RateLimiter::tooMany('promo_edit:' . client_ip(), 8, 3600)) {
        $error = 'Te veel pogingen.';
    } else {
        try {
            PromoService::resubmitIdea(
                $token,
                (string) ($_POST['idea_description'] ?? '')
            );
            $done = true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$title = 'Promo aanpassen';
$noindex = true;

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <h1>Promo aanpassen</h1>
    <?php if ($done): ?>
      <div class="status-box status-ok"><p>Je aangepaste voorstel is opnieuw ingediend. Korne kijkt ernaar.</p></div>
    <?php elseif ($error !== '' && !is_post()): ?>
      <div class="status-box status-bad"><p><?= e($error) ?></p></div>
    <?php else: ?>
      <?php if (!empty($app['idea_changes_note'])): ?><div class="form-card" style="margin-bottom:1rem"><strong>Vraag van Korne</strong><p><?= nl2br(e((string) $app['idea_changes_note'])) ?></p></div><?php endif; ?>
      <?php if ($error): ?><div class="errors"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form-card" style="max-width:28rem">
        <?= Security::csrfField() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-grid">
          <label>Jouw promo<textarea name="idea_description" required maxlength="5000"><?= e((string) ($app['idea_description'] ?? '')) ?></textarea></label>
        </div>
        <p style="margin-top:1rem"><button class="btn btn-primary" type="submit">Opnieuw indienen</button></p>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

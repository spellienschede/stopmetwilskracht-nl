<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Database;
use Grippartner\OrderService;
use Grippartner\PromoService;
use Grippartner\Security;

Auth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$app = PromoService::findById($id);
if (!$app) {
    http_response_code(404);
    echo 'Promo-aanvraag niet gevonden.';
    exit;
}

$status = (string) $app['status'];
$confirmApproveExecution = isset($_GET['confirm']) && (string) $_GET['confirm'] === '1'
    && (string) ($_GET['action'] ?? '') === 'approve_execution';

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $adminId = Auth::id();

    try {
        switch ($action) {
            case 'save_note':
                $note = trim((string) ($_POST['internal_note'] ?? ''));
                Database::pdo()->prepare('UPDATE promo_applications SET internal_note = ? WHERE id = ?')
                    ->execute([$note === '' ? null : mb_substr($note, 0, 5000), $id]);
                $_SESSION['flash'] = 'Interne notitie opgeslagen.';
                break;

            case 'request_idea_changes':
                $note = trim((string) ($_POST['note'] ?? ''));
                if (mb_strlen($note) < 5) {
                    throw new \RuntimeException('Geef een duidelijke toelichting (min. 5 tekens).');
                }
                PromoService::requestIdeaChanges($id, $note, $adminId);
                $_SESSION['flash'] = 'Aanpassing gevraagd; e-mail met link is verstuurd.';
                break;

            case 'reject_idea':
                $reason = trim((string) ($_POST['reason'] ?? ''));
                if (mb_strlen($reason) < 5) {
                    throw new \RuntimeException('Geef een afwijzingsreden.');
                }
                $sendEmail = !empty($_POST['send_email']);
                PromoService::rejectIdea($id, $reason, $sendEmail, $adminId);
                $_SESSION['flash'] = 'Idee afgewezen.';
                break;

            case 'approve_idea':
                $agreement = trim((string) ($_POST['agreement'] ?? ''));
                if (mb_strlen($agreement) < 10) {
                    throw new \RuntimeException('Vul de uitvoeringsafspraak in.');
                }
                $deadline = trim((string) ($_POST['deadline'] ?? ''));
                if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                    throw new \RuntimeException('Ongeldige deadline-datum.');
                }
                // Geen gratis order hier — alleen voorlopige goedkeuring + token
                PromoService::approveIdea($id, $agreement, $deadline !== '' ? $deadline : null, $adminId);
                $_SESSION['flash'] = 'Idee goedgekeurd (wacht op uitvoering). Er is géén gratis order aangemaakt.';
                break;

            case 'mark_expired':
                PromoService::markExpired($id, $adminId);
                $_SESSION['flash'] = 'Aanvraag gemarkeerd als verlopen.';
                break;

            case 'request_execution_changes':
                $note = trim((string) ($_POST['note'] ?? ''));
                if (mb_strlen($note) < 5) {
                    throw new \RuntimeException('Geef een duidelijke toelichting.');
                }
                PromoService::requestExecutionChanges($id, $note, $adminId);
                $_SESSION['flash'] = 'Aanvulling uitvoering gevraagd; e-mail verstuurd.';
                break;

            case 'reject_execution':
                $reason = trim((string) ($_POST['reason'] ?? ''));
                if (mb_strlen($reason) < 5) {
                    throw new \RuntimeException('Geef een afwijzingsreden.');
                }
                $sendEmail = !empty($_POST['send_email']);
                PromoService::rejectExecution($id, $reason, $sendEmail, $adminId);
                $_SESSION['flash'] = 'Uitvoering afgewezen.';
                break;

            case 'approve_execution':
                if (empty($_POST['confirmed'])) {
                    throw new \RuntimeException('Bevestig eerst dat je één gratis order wilt aanmaken.');
                }
                $result = PromoService::approveExecution($id, $adminId);
                $orderNum = $result['order']['public_order_number'] ?? '?';
                $_SESSION['flash'] = 'Uitvoering goedgekeurd. Gratis order ' . $orderNum . ' aangemaakt en e-mail verstuurd.';
                break;

            case 'mark_shipped':
                if (empty($app['reward_order_id'])) {
                    throw new \RuntimeException('Geen beloningsorder gekoppeld.');
                }
                OrderService::markShipped((int) $app['reward_order_id'], !empty($_POST['send_email']));
                $_SESSION['flash'] = 'Gratis exemplaar gemarkeerd als verzonden.';
                break;

            default:
                throw new \RuntimeException('Onbekende actie.');
        }
    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    redirect('/admin/book/promo.php?id=' . $id);
}

$app = PromoService::findById($id) ?? $app;
$status = (string) $app['status'];
$history = PromoService::history($id);
$rewardOrder = !empty($app['reward_order_id'])
    ? OrderService::findById((int) $app['reward_order_id'])
    : null;

$pageTitle = 'Promo ' . $app['public_application_number'];
require __DIR__ . '/_layout_start.php';
?>
<p><a href="/admin/book/promos.php">← Promo-aanvragen</a></p>
<h1><?= e((string) $app['public_application_number']) ?></h1>
<p>
  <span class="badge"><?= e(PromoService::LABELS[$status] ?? $status) ?></span>
  <?php if ($rewardOrder): ?>
    — Beloningsorder:
    <a href="/admin/book/order.php?id=<?= (int) $rewardOrder['id'] ?>"><?= e((string) $rewardOrder['public_order_number']) ?></a>
  <?php endif; ?>
</p>

<?php if ($confirmApproveExecution && in_array($status, [PromoService::EXECUTION_SUBMITTED, PromoService::EXECUTION_APPROVED, PromoService::SUBMITTED], true)): ?>
<section class="status-box status-wait" style="max-width:100%;margin:1.25rem 0">
  <h2 style="margin-top:0">Promo bevestigen?</h2>
  <p>Hiermee maak je <strong>één gratis order</strong> aan en stuur je de aanvrager een bevestigingsmail.</p>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="approve_execution">
    <input type="hidden" name="confirmed" value="1">
    <p>
      <button type="submit" class="btn btn-primary">Ja, bevestig + gratis boek</button>
      <a class="btn btn-ghost" href="/admin/book/promo.php?id=<?= $id ?>">Annuleren</a>
    </p>
  </form>
</section>
<?php endif; ?>

<table class="data" style="margin:1rem 0">
  <tbody>
    <tr><th>Naam</th><td><?= e(trim($app['first_name'] . ' ' . $app['last_name'])) ?></td></tr>
    <tr><th>E-mail</th><td><a href="mailto:<?= e((string) $app['email']) ?>"><?= e((string) $app['email']) ?></a></td></tr>
    <tr><th>Adres</th><td>
      <?php
        $addr = trim(($app['street'] ?? '') . ' ' . ($app['house_number'] ?? '') . (!empty($app['house_addition']) ? ' ' . $app['house_addition'] : ''));
        $cityLine = trim(($app['postal_code'] ?? '') . ' ' . ($app['city'] ?? ''));
      ?>
      <?php if ($addr === '' && $cityLine === ''): ?>
        <span class="hint">Nog niet ingevuld</span>
      <?php else: ?>
        <?= e($addr) ?><br>
        <?= e($cityLine) ?><br>
        <?= e((string) $app['country']) ?>
      <?php endif; ?>
    </td></tr>
    <tr><th>Wat gedaan</th><td style="white-space:pre-wrap"><?= e((string) ($app['execution_description'] ?: $app['idea_description'])) ?></td></tr>
    <tr><th>Link</th><td style="white-space:pre-wrap"><?= e((string) ($app['execution_links'] ?? '—')) ?></td></tr>
    <tr><th>Aangemaakt</th><td><?= e((string) $app['created_at']) ?></td></tr>
  </tbody>
</table>

<section class="form-card" style="max-width:100%;margin-bottom:1.25rem">
  <h2 style="margin-top:0;font-size:1.05rem">Interne notitie</h2>
  <form method="post">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="save_note">
    <textarea name="internal_note" rows="3"><?= e((string) ($app['internal_note'] ?? '')) ?></textarea>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-primary">Notitie opslaan</button></p>
  </form>
</section>

<?php if (in_array($status, [PromoService::EXECUTION_SUBMITTED, PromoService::SUBMITTED], true)): ?>
<section class="form-card" style="max-width:100%;margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:1.05rem">Promo beoordelen</h2>
  <p class="hint">Bevestigen = gratis exemplaar toekennen.</p>
  <p>
    <a class="btn btn-primary" href="/admin/book/promo.php?id=<?= $id ?>&amp;action=approve_execution&amp;confirm=1">Bevestigen…</a>
  </p>

  <form method="post" style="margin:1.25rem 0" onsubmit="return confirm('Aanvulling vragen?');">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="request_execution_changes">
    <label>Aanvulling vragen
      <textarea name="note" required minlength="5" rows="3"></textarea>
    </label>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-ghost">Aanvulling vragen</button></p>
  </form>

  <form method="post" onsubmit="return confirm('Promo afwijzen?');">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="reject_execution">
    <label>Afwijzen – reden
      <textarea name="reason" required minlength="5" rows="3"></textarea>
    </label>
    <label class="check" style="margin-top:0.5rem">
      <input type="checkbox" name="send_email" value="1" checked>
      <span>Afwijzingsmail sturen</span>
    </label>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-ghost">Afwijzen</button></p>
  </form>
</section>
<?php endif; ?>

<?php if ($status === PromoService::APPROVED_AWAITING): ?>
<section class="form-card" style="max-width:100%;margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:1.05rem">Oude status: wacht op uitvoering</h2>
  <p class="hint">Dit is een aanvraag uit de oude workflow.</p>
  <form method="post" onsubmit="return confirm('Markeren als verlopen?');">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="mark_expired">
    <button type="submit" class="btn btn-ghost">Markeer als verlopen</button>
  </form>
</section>
<?php endif; ?>

<?php if ($status === PromoService::EXECUTION_APPROVED && empty($app['reward_order_id'])): ?>
<section class="form-card" style="max-width:100%;margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:1.05rem">Gratis order afronden</h2>
  <p class="hint">Promo is bevestigd, maar er is nog geen beloningsorder.</p>
  <a class="btn btn-primary" href="/admin/book/promo.php?id=<?= $id ?>&amp;action=approve_execution&amp;confirm=1">Gratis order aanmaken…</a>
</section>
<?php endif; ?>

<?php if ($status === PromoService::REWARD_CREATED && $rewardOrder && $rewardOrder['fulfilment_status'] !== OrderService::FULFILMENT_SHIPPED): ?>
<section class="form-card" style="max-width:100%;margin-bottom:1rem">
  <h2 style="margin-top:0;font-size:1.05rem">Gratis boek verzenden</h2>
  <p>Order <a href="/admin/book/order.php?id=<?= (int) $rewardOrder['id'] ?>"><?= e((string) $rewardOrder['public_order_number']) ?></a> staat klaar.</p>
  <form method="post" onsubmit="return confirm('Gratis exemplaar als verzonden markeren?');">
    <?= Security::csrfField() ?>
    <input type="hidden" name="action" value="mark_shipped">
    <label class="check">
      <input type="checkbox" name="send_email" value="1" checked>
      <span>Verzendmail sturen</span>
    </label>
    <p style="margin-top:0.75rem"><button type="submit" class="btn btn-primary">Markeer verzonden</button></p>
  </form>
</section>
<?php endif; ?>

<section style="margin-top:2rem">
  <h2 style="font-size:1.1rem">Geschiedenis</h2>
  <?php if ($history === []): ?>
    <p class="hint">Nog geen geschiedenis.</p>
  <?php else: ?>
    <ol style="padding-left:1.2rem">
      <?php foreach ($history as $h): ?>
        <li style="margin-bottom:0.75rem">
          <strong><?= e((string) $h['created_at']) ?></strong>
          — <?= e(PromoService::LABELS[$h['old_status'] ?? ''] ?? (string) ($h['old_status'] ?? 'nieuw')) ?>
          → <strong><?= e(PromoService::LABELS[$h['new_status']] ?? (string) $h['new_status']) ?></strong>
          <span class="hint">(<?= e((string) $h['actor']) ?>)</span>
          <?php if (!empty($h['public_note'])): ?>
            <div style="white-space:pre-wrap"><?= e((string) $h['public_note']) ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>

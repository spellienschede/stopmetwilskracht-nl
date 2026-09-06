<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\MediaKit;
use Grippartner\MediaRequestService;
use Grippartner\Security;

Auth::requireLogin();

$id = (int) ($_GET['id'] ?? 0);
$row = MediaRequestService::findById($id);
if (!$row) {
    http_response_code(404);
    echo 'Media-aanvraag niet gevonden.';
    exit;
}

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_note') {
            MediaRequestService::saveNote($id, (string) ($_POST['internal_note'] ?? ''));
            $_SESSION['flash'] = 'Interne notitie opgeslagen.';
        } elseif ($action === 'set_status') {
            $status = (string) ($_POST['status'] ?? '');
            MediaRequestService::updateStatus($id, $status, Auth::id());
            $_SESSION['flash'] = 'Status bijgewerkt.';
        } else {
            throw new RuntimeException('Onbekende actie.');
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    redirect('/admin/book/media-request.php?id=' . $id);
}

$row = MediaRequestService::findById($id) ?? $row;
$pageTitle = 'Media ' . $row['public_request_number'];
require __DIR__ . '/_layout_start.php';
?>
<p><a href="/admin/book/media-requests.php">← Media-aanvragen</a></p>
<h1><?= e((string) $row['public_request_number']) ?></h1>
<p><span class="badge"><?= e(MediaRequestService::STATUS_LABELS[$row['status']] ?? (string) $row['status']) ?></span>
  · <?= e(MediaKit::REQUEST_TYPES[$row['request_type']] ?? (string) $row['request_type']) ?></p>

<section class="form-card" style="max-width:100%;margin:1.25rem 0">
  <p><strong><?= e((string) $row['name']) ?></strong><br>
    <?= e((string) $row['organization']) ?><br>
    <a href="mailto:<?= e((string) $row['email']) ?>"><?= e((string) $row['email']) ?></a></p>
  <?php if (!empty($row['channel_url'])): ?>
    <p><strong>Kanaal:</strong> <?= e((string) $row['channel_url']) ?></p>
  <?php endif; ?>
  <?php if (!empty($row['preferred_date'])): ?>
    <p><strong>Gewenste datum:</strong> <?= e((string) $row['preferred_date']) ?></p>
  <?php endif; ?>
  <?php if (!empty($row['audience_reach'])): ?>
    <p><strong>Bereik:</strong><br><?= nl2br(e((string) $row['audience_reach'])) ?></p>
  <?php endif; ?>
  <p><strong>Toelichting</strong><br><?= nl2br(e((string) $row['message'])) ?></p>
  <p class="hint">Ingediend: <?= e((string) $row['created_at']) ?>
    <?= !empty($row['handled_at']) ? ' · Afgehandeld: ' . e((string) $row['handled_at']) : '' ?></p>
</section>

<form method="post" class="form-card" style="max-width:100%;margin-bottom:1rem">
  <?= Security::csrfField() ?>
  <input type="hidden" name="action" value="set_status">
  <label>Status
    <select name="status">
      <?php foreach (MediaRequestService::STATUS_LABELS as $val => $label): ?>
        <option value="<?= e($val) ?>"<?= $row['status'] === $val ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <p style="margin-top:0.75rem"><button class="btn btn-primary" type="submit">Status opslaan</button></p>
</form>

<form method="post" class="form-card" style="max-width:100%">
  <?= Security::csrfField() ?>
  <input type="hidden" name="action" value="save_note">
  <label>Interne notitie
    <textarea name="internal_note" rows="5" maxlength="5000"><?= e((string) ($row['internal_note'] ?? '')) ?></textarea>
  </label>
  <p style="margin-top:0.75rem"><button class="btn btn-ghost" type="submit">Notitie opslaan</button></p>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>

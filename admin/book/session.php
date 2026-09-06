<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Security;
use Grippartner\SessionService;
use Grippartner\Validator;

Auth::requireLogin();
SessionService::ensureSchema();

$id = (int) ($_GET['id'] ?? 0);
$session = $id > 0 ? SessionService::findById($id) : null;
$isEdit = $session !== null;

$defaults = [
    'slug' => '',
    'title' => '',
    'hosts' => '',
    'intro' => '',
    'date' => '',
    'start_time' => '19:30',
    'end_time' => '20:00',
    'meeting_url' => '',
    'signup_open' => '1',
    'is_published' => '1',
    'host1_name' => '',
    'host1_role' => '',
    'host1_photo' => '',
    'host2_name' => '',
    'host2_role' => '',
    'host2_photo' => '',
];

if ($session) {
    $starts = SessionService::startsAt($session);
    $ends = SessionService::endsAt($session);
    $defaults = [
        'slug' => (string) $session['slug'],
        'title' => (string) $session['title'],
        'hosts' => (string) ($session['hosts'] ?? ''),
        'intro' => (string) ($session['intro'] ?? ''),
        'date' => $starts ? $starts->format('Y-m-d') : '',
        'start_time' => $starts ? $starts->format('H:i') : '19:30',
        'end_time' => $ends ? $ends->format('H:i') : '',
        'meeting_url' => (string) ($session['meeting_url'] ?? ''),
        'signup_open' => (int) $session['signup_open'] ? '1' : '',
        'is_published' => (int) $session['is_published'] ? '1' : '',
        'host1_name' => (string) ($session['host1_name'] ?? ''),
        'host1_role' => (string) ($session['host1_role'] ?? ''),
        'host1_photo' => (string) ($session['host1_photo'] ?? ''),
        'host2_name' => (string) ($session['host2_name'] ?? ''),
        'host2_role' => (string) ($session['host2_role'] ?? ''),
        'host2_photo' => (string) ($session['host2_photo'] ?? ''),
    ];
}

$errors = [];
$form = $defaults;

if (is_post()) {
    Security::requireCsrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'send_meeting' && $session) {
        $url = trim((string) ($_POST['meeting_url'] ?? $session['meeting_url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'] = 'Sla eerst een geldige Zoom-/meeting-URL op.';
        } else {
            // Persist URL if changed
            $data = Validator::normalizeSessionAdminInput(array_merge($defaults, [
                'meeting_url' => $url,
                'signup_open' => !empty($defaults['signup_open']) ? '1' : '',
                'is_published' => !empty($defaults['is_published']) ? '1' : '',
            ]));
            // Use posted form fields if present
            if (!empty($_POST['title'])) {
                $data = Validator::normalizeSessionAdminInput($_POST);
            } else {
                $data['meeting_url'] = $url;
            }
            SessionService::update((int) $session['id'], $data);
            $result = SessionService::sendMeetingLinkToAll((int) $session['id']);
            $_SESSION['flash'] = 'Zoom-mail verwerkt: ' . $result['sent'] . ' verstuurd/geregistreerd'
                . ($result['failed'] > 0 ? ', ' . $result['failed'] . ' mislukt' : '') . '.';
        }
        redirect('/admin/book/session.php?id=' . (int) $session['id']);
    }

    if ($action === 'send_custom' && $session) {
        $subject = trim((string) ($_POST['mail_subject'] ?? ''));
        $body = trim((string) ($_POST['mail_body'] ?? ''));
        $campaign = trim((string) ($_POST['mail_campaign'] ?? ''));
        if ($subject === '' || $body === '') {
            $_SESSION['flash_error'] = 'Onderwerp en bericht zijn verplicht.';
        } else {
            $result = SessionService::sendCustomMail((int) $session['id'], $subject, $body, $campaign);
            $_SESSION['flash'] = 'Mail verstuurd naar ' . $result['sent'] . ' personen'
                . ($result['failed'] > 0 ? ' (' . $result['failed'] . ' mislukt)' : '') . '.';
        }
        redirect('/admin/book/session.php?id=' . (int) $session['id']);
    }

    $form = array_merge($defaults, $_POST);
    $form['signup_open'] = !empty($_POST['signup_open']) ? '1' : '';
    $form['is_published'] = !empty($_POST['is_published']) ? '1' : '';
    $errors = Validator::sessionAdmin($_POST);
    if ($errors === []) {
        $data = Validator::normalizeSessionAdminInput($_POST);
        if ($data['slug'] === '') {
            $data['slug'] = SessionService::normalizeSlug($data['title']);
        }
        try {
            if ($session) {
                $saved = SessionService::update((int) $session['id'], $data);
                $_SESSION['flash'] = 'Sessie opgeslagen.';
                redirect('/admin/book/session.php?id=' . (int) ($saved['id'] ?? $session['id']));
            }
            $saved = SessionService::create($data);
            $_SESSION['flash'] = 'Sessie aangemaakt.';
            redirect('/admin/book/session.php?id=' . (int) ($saved['id'] ?? 0));
        } catch (Throwable $e) {
            \Grippartner\Logger::error('Session save failed', ['m' => $e->getMessage()]);
            $errors['form'] = 'Opslaan mislukt. Probeer opnieuw.';
        }
    }
}

$pageTitle = $isEdit ? 'Sessie bewerken' : 'Nieuwe sessie';
require __DIR__ . '/_layout_start.php';
$signupCount = $session ? SessionService::countSignups((int) $session['id']) : 0;
?>
<p><a href="/admin/book/sessions.php">← Alle sessies</a></p>
<h1><?= e($pageTitle) ?></h1>
<?php if ($session): ?>
  <p class="hint">
    <?= e(SessionService::formatWhen($session)) ?> ·
    <a href="/admin/book/session-signups.php?id=<?= (int) $session['id'] ?>"><?= $signupCount ?> aanmelding<?= $signupCount === 1 ? '' : 'en' ?></a> ·
    <a href="<?= e(SessionService::publicUrl($session)) ?>" target="_blank" rel="noopener">Publieke pagina</a>
  </p>
<?php endif; ?>

<?php if ($errors): ?><div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" class="form-card" style="max-width:42rem;margin-top:1rem">
  <?= Security::csrfField() ?>
  <input type="hidden" name="action" value="save">
  <div class="form-grid">
    <label>Titel<input name="title" required maxlength="255" value="<?= e((string) $form['title']) ?>"></label>
    <label>Slug <span class="hint">(URL)</span><input name="slug" maxlength="80" value="<?= e((string) $form['slug']) ?>" placeholder="3-geheimen"></label>
    <label>Hosts<input name="hosts" maxlength="255" value="<?= e((string) $form['hosts']) ?>" placeholder="Bas Oude Luttikhuis en Korné Pot"></label>
    <label>Intro / wervende tekst<textarea name="intro" rows="4" maxlength="4000"><?= e((string) $form['intro']) ?></textarea></label>
    <label>Datum<input type="date" name="date" required value="<?= e((string) $form['date']) ?>"></label>
    <label>Starttijd<input type="time" name="start_time" required value="<?= e((string) $form['start_time']) ?>"></label>
    <label>Eindtijd<input type="time" name="end_time" value="<?= e((string) $form['end_time']) ?>"></label>
    <label>Zoom-/meeting-URL<input type="url" name="meeting_url" maxlength="500" value="<?= e((string) $form['meeting_url']) ?>" placeholder="https://zoom.us/j/…"></label>
    <label class="checkbox"><input type="checkbox" name="signup_open" value="1" <?= !empty($form['signup_open']) ? 'checked' : '' ?>> Aanmelden open</label>
    <label class="checkbox"><input type="checkbox" name="is_published" value="1" <?= !empty($form['is_published']) ? 'checked' : '' ?>> Gepubliceerd</label>
  </div>

  <h2 style="margin-top:1.5rem;font-size:1.05rem">Hosts / foto’s</h2>
  <div class="form-grid">
    <label>Host 1 naam<input name="host1_name" maxlength="120" value="<?= e((string) $form['host1_name']) ?>"></label>
    <label>Host 1 rol<input name="host1_role" maxlength="160" value="<?= e((string) $form['host1_role']) ?>"></label>
    <label>Host 1 foto-pad<input name="host1_photo" maxlength="255" value="<?= e((string) $form['host1_photo']) ?>" placeholder="/assets/img/sessions/bas-oude-luttikhuis.jpg"></label>
    <label>Host 2 naam<input name="host2_name" maxlength="120" value="<?= e((string) $form['host2_name']) ?>"></label>
    <label>Host 2 rol<input name="host2_role" maxlength="160" value="<?= e((string) $form['host2_role']) ?>"></label>
    <label>Host 2 foto-pad<input name="host2_photo" maxlength="255" value="<?= e((string) $form['host2_photo']) ?>" placeholder="/assets/img/sessions/korne-pot-adidas.jpg"></label>
  </div>

  <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">Opslaan</button></p>
</form>

<?php if ($session): ?>
  <div class="form-card" style="max-width:42rem;margin-top:1.5rem">
    <h2 style="font-size:1.05rem;margin-top:0">Zoom-link mailen</h2>
    <p class="hint">Stuurt de opgeslagen meeting-URL naar alle <?= $signupCount ?> aanmeldingen. Al verstuurde mails (zelfde URL) worden overgeslagen.</p>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="send_meeting">
      <input type="hidden" name="meeting_url" value="<?= e((string) $form['meeting_url']) ?>">
      <p><button class="btn btn-primary" type="submit" <?= $signupCount < 1 || trim((string) $form['meeting_url']) === '' ? 'disabled' : '' ?>>Stuur Zoom-link naar iedereen</button></p>
    </form>
  </div>

  <div class="form-card" style="max-width:42rem;margin-top:1.5rem">
    <h2 style="font-size:1.05rem;margin-top:0">Eigen mail naar alle aanmeldingen</h2>
    <p class="hint">Placeholders: <code>{{name}}</code> <code>{{title}}</code> <code>{{when}}</code> <code>{{meeting_url}}</code> <code>{{link}}</code> <code>{{hosts}}</code></p>
    <form method="post">
      <?= Security::csrfField() ?>
      <input type="hidden" name="action" value="send_custom">
      <div class="form-grid">
        <label>Campagne-sleutel <span class="hint">(uniek per mailing, voorkomt dubbel versturen)</span>
          <input name="mail_campaign" maxlength="40" value="reminder-<?= e(date('Ymd')) ?>" required>
        </label>
        <label>Onderwerp<input name="mail_subject" maxlength="200" required placeholder="Herinnering: {{title}}"></label>
        <label>Bericht<textarea name="mail_body" rows="6" required placeholder="Hoi {{name}},&#10;&#10;Morgen om {{when}} starten we.&#10;&#10;Link: {{meeting_url}}"></textarea></label>
      </div>
      <p style="margin-top:1rem"><button class="btn btn-primary" type="submit" <?= $signupCount < 1 ? 'disabled' : '' ?>>Verstuur mail</button></p>
    </form>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/_layout_end.php'; ?>

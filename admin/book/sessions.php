<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\SessionService;

Auth::requireLogin();
SessionService::ensureSchema();

$rows = SessionService::all();

$pageTitle = 'Online sessies';
require __DIR__ . '/_layout_start.php';
?>
<h1>Online sessies</h1>
<p class="hint">Maak sessies aan, bekijk aanmeldingen en stuur Zoom-links of andere mails.</p>
<p style="margin:1rem 0"><a class="btn btn-primary" href="/admin/book/session.php">Nieuwe sessie</a></p>

<table class="admin-table">
  <thead>
    <tr>
      <th>Titel</th>
      <th>Wanneer</th>
      <th>Aanmeldingen</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="5">Nog geen sessies.</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $row): ?>
        <?php
          $open = SessionService::isOpen($row);
          $published = (int) $row['is_published'] === 1;
        ?>
        <tr>
          <td>
            <strong><?= e((string) $row['title']) ?></strong><br>
            <span class="hint">/sessie/<?= e((string) $row['slug']) ?></span>
          </td>
          <td><?= e(SessionService::formatWhen($row)) ?></td>
          <td><a href="/admin/book/session-signups.php?id=<?= (int) $row['id'] ?>"><?= (int) $row['signup_count'] ?></a></td>
          <td>
            <?php if (!$published): ?>
              concept
            <?php elseif ($open): ?>
              open
            <?php else: ?>
              gesloten
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <a href="/admin/book/session.php?id=<?= (int) $row['id'] ?>">Bewerk</a>
            ·
            <a href="<?= e(SessionService::publicUrl($row)) ?>" target="_blank" rel="noopener">Pagina</a>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
<?php require __DIR__ . '/_layout_end.php'; ?>

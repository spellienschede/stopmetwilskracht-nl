<?php
declare(strict_types=1);

use Grippartner\Config;

require __DIR__ . '/src/bootstrap.php';

$title = 'Wekelijkse tip – ' . Config::string('BOOK_AUTHOR');
$description = 'Ontvang elke week een praktische tip van Korne Pot. Gratis. Direct toepasbaar.';
$canonical = Config::baseUrl() . '/tip.php';
$success = isset($_GET['success']);
$error = (string) ($_GET['error'] ?? '');

ob_start();
?>
<section class="section tip-page" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:40rem">
    <p class="eyebrow">Nieuwsbrief</p>
    <h1>Elke week een tip die je verder helpt</h1>
    <p class="lede">Je weet wat belangrijk is. Maar je doet het te weinig — niet door gebrek aan motivatie, maar omdat het dagelijkse leven zich steeds weer opdringt.</p>
    <p>Elke week deel ik één praktische tip. Geen lange verhalen, geen theorie. Wel iets dat je direct kunt toepassen — zodat je structureel aandacht geeft aan wat er écht toe doet.</p>

    <ul class="promo-ideas tip-benefits" style="margin-top:1.5rem">
      <li><strong>Elke week</strong> — een tip in je inbox</li>
      <li><strong>Direct toepasbaar</strong> — praktische inzichten, geen theorie</li>
      <li><strong>Gratis</strong> — geen kosten, geen verplichtingen</li>
    </ul>

    <div class="form-card" style="margin-top:2rem" id="aanmelden">
      <h2 style="font-size:1.35rem;margin-top:0">Meld je aan</h2>
      <p class="hint" style="margin:0 0 1rem">Geen spam. Geen verkooppraatjes. Alleen tips. Uitschrijven kan altijd.</p>

      <?php if ($success): ?>
        <div class="status-box status-ok" role="status">Bedankt! Je bent aangemeld. Check je inbox voor de bevestiging.</div>
      <?php elseif ($error !== ''): ?>
        <div class="errors" role="alert">
          <?= $error === 'email'
            ? 'Ongeldig e-mailadres. Probeer het opnieuw.'
            : ($error === 'velden'
              ? 'Vul alle velden in.'
              : 'Er is een fout opgetreden. Probeer het opnieuw.') ?>
        </div>
      <?php endif; ?>

      <form action="/aanmelden.php" method="post">
        <div class="form-grid">
          <label>Voornaam<input id="voornaam" type="text" name="voornaam" required autocomplete="given-name" maxlength="100"></label>
          <label>Achternaam<input id="achternaam" type="text" name="achternaam" required autocomplete="family-name" maxlength="100"></label>
          <label>E-mailadres<input id="email" type="email" name="email" required autocomplete="email" maxlength="255"></label>
        </div>
        <p style="margin-top:1.25rem"><button class="btn btn-primary btn-lg" type="submit" style="width:100%">Stuur me elke week een tip</button></p>
        <p class="hint" style="margin-top:0.85rem;text-align:center">We delen je gegevens nooit met anderen.</p>
      </form>
    </div>

    <div style="margin-top:2.5rem">
      <h2 style="font-size:1.25rem">Wat je kunt verwachten</h2>
      <ul>
        <li>een praktische tip</li>
        <li>een concrete vraag om over na te denken</li>
        <li>een kleine actie die je direct kunt nemen</li>
      </ul>
      <p class="hint">Geen lange verhalen. Geen marketing. Alleen inzichten die je verder helpen.</p>
      <p style="margin-top:1.25rem"><a href="/">Naar het boek <?= e(Config::string('BOOK_TITLE')) ?></a></p>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

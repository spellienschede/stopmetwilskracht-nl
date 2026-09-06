<?php
/** @var array<string,string> $errors */
/** @var bool $success */
/** @var string $publicNr */
/** @var array<string,mixed> $data */
/** @var array<string,mixed>|null $kitZip */
/** @var array<string,mixed>|null $coverWeb */
/** @var array<string,mixed>|null $coverHigh */
/** @var list<array<string,mixed>> $authorReady */

use Grippartner\Config;
use Grippartner\MediaKit;
use Grippartner\Security;

$copy = static function (string $text, string $label = 'Kopieer tekst'): string {
    $id = 'copy-' . substr(hash('sha256', $label . $text), 0, 10);
    return '<div class="copy-block" data-copy-root>'
        . '<pre class="copy-text" id="' . e($id) . '">' . e($text) . '</pre>'
        . '<button type="button" class="btn btn-ghost copy-btn" data-copy-target="' . e($id) . '">' . e($label) . '</button>'
        . '<span class="copy-status" data-copy-status hidden aria-live="polite">Gekopieerd</span>'
        . '</div>';
};
?>
<section class="section media-hero" style="border-top:0;padding-top:2rem">
  <div class="wrap">
    <p class="eyebrow">Pers &amp; partners</p>
    <h1 id="intro">Mediakit Grippartner</h1>
    <p class="lede">Hier vinden journalisten, makers, redacties en promotiepartners informatie, teksten en beeldmateriaal rond het boek <em>Grippartner</em> en auteur <?= e(Config::string('BOOK_AUTHOR')) ?>.</p>

    <nav class="media-toc" aria-label="Op deze pagina">
      <?php foreach (MediaKit::pageNav() as $item): ?>
        <a href="#<?= e($item['id']) ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="media-cta-row">
      <?php if ($kitZip && !empty($kitZip['downloadable'])): ?>
        <a class="btn btn-primary" href="<?= e((string) $kitZip['path']) ?>" download="<?= e((string) $kitZip['filename']) ?>">Download complete mediakit</a>
      <?php else: ?>
        <span class="media-status">Complete mediakit volgt</span>
      <?php endif; ?>

            <?php
              $coverReady = ($coverHigh && !empty($coverHigh['downloadable'])) || ($coverWeb && !empty($coverWeb['downloadable']));
              $coverDl = ($coverHigh && !empty($coverHigh['downloadable'])) ? $coverHigh
                  : (($coverWeb && !empty($coverWeb['downloadable'])) ? $coverWeb : null);
            ?>
            <?php if ($coverDl): ?>
              <a class="btn btn-ghost" href="<?= e((string) $coverDl['path']) ?>" download="<?= e((string) $coverDl['filename']) ?>">Download boekomslag</a>
              <?php if ($coverReady): ?>
                <span class="hint" style="margin:0">Tijdelijke omslag — definitieve versie volgt</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="media-status">Definitieve boekomslag volgt</span>
            <?php endif; ?>

      <?php if ($authorReady !== []): ?>
        <a class="btn btn-ghost" href="#beeld">Download auteursfoto’s</a>
      <?php else: ?>
        <span class="media-status">Auteursfoto’s volgen</span>
      <?php endif; ?>

      <a class="btn btn-ghost" href="#contact">Interview of samenwerking aanvragen</a>
    </div>
  </div>
</section>

<section class="section" id="feiten">
  <div class="wrap">
    <h2>Boek in het kort</h2>
    <p class="section-intro">De belangrijkste feiten, klaar om over te nemen.</p>
    <dl class="fact-grid">
      <?php foreach (MediaKit::factRows() as $row): ?>
        <div class="fact-item">
          <dt><?= e($row['label']) ?></dt>
          <dd><?= e($row['value']) ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
    <p class="hint">Kernstelling: <strong><?= e('Niemand is op zoek naar een grippartner, maar iedereen heeft er één nodig.') ?></strong></p>
  </div>
</section>

<section class="section" id="teksten">
  <div class="wrap">
    <h2>Teksten om over te nemen</h2>

    <h3>Elevator pitch</h3>
    <p class="section-intro">Ongeveer 40 woorden — geschikt voor presentatoren en korte agenda’s.</p>
    <?= $copy(MediaKit::elevatorPitch()) ?>

    <h3>Korte boekbeschrijving</h3>
    <p class="section-intro">Ongeveer 100 woorden — voor nieuwsbrieven, webshops, events en podcasts.</p>
    <?= $copy(MediaKit::shortDescription()) ?>

    <h3>Lange boekbeschrijving</h3>
    <p class="section-intro">Ongeveer 250–350 woorden — voor artikelen en uitgebreidere aankondigingen.</p>
    <?= $copy(MediaKit::longDescription()) ?>
  </div>
</section>

<section class="section" id="kern">
  <div class="wrap">
    <h2>De kern van Grippartner</h2>
    <div class="media-core">
      <article>
        <h3>Het probleem</h3>
        <p>Veel mensen weten al wat zij zouden willen veranderen. Toch verdwijnen goede voornemens in de drukte. De afstand zit zelden in weten of willen — die zit in doen.</p>
      </article>
      <article>
        <h3>De ontdekking</h3>
        <p>Niemand is op zoek naar een grippartner, maar iedereen heeft er één nodig: iemand die wekelijks meekijkt op wat voor jou belangrijk is.</p>
      </article>
      <article>
        <h3>De methode</h3>
        <ul>
          <li>twee gelijkwaardige mensen</li>
          <li>één gedeeld document</li>
          <li>iedere week ± 30 minuten</li>
          <li>terugkijken en vooruitkijken</li>
          <li>minimaal drie maanden serieus proberen</li>
        </ul>
      </article>
      <article>
        <h3>Voor wie</h3>
        <p>Voor ondernemende volwassenen — en iedereen die merkt dat belangrijke zaken verliezen van urgente zaken. Geen zwaar coachtraject; wel iets nuchters en toepasbaars.</p>
      </article>
      <article>
        <h3>Na het boek</h3>
        <p>Eén persoon benaderen, een eenvoudig document delen, wekelijks afspreken en de samenwerking drie maanden testen.</p>
      </article>
    </div>
    <blockquote class="media-quote">
      <p>“<?= e('Een grippartner is iemand die je op wekelijkse basis, met een gedeeld document, helpt meer grip te krijgen op de onderdelen van je leven die voor jou belangrijk zijn.') ?>”</p>
      <footer>Definitie — samenvatting van het concept</footer>
    </blockquote>
  </div>
</section>

<section class="section" id="auteur">
  <div class="wrap">
    <h2>Over <?= e(Config::string('BOOK_AUTHOR')) ?></h2>
    <h3>Korte biografie</h3>
    <?= $copy(MediaKit::bioShort()) ?>
    <h3>Langere biografie</h3>
    <?= $copy(MediaKit::bioLong()) ?>
  </div>
</section>

<section class="section" id="interviews">
  <div class="wrap">
    <h2>Interviewonderwerpen</h2>
    <p class="section-intro">Onderwerpen waarover Korne geïnterviewd kan worden.</p>
    <ul class="media-topics">
      <?php foreach (MediaKit::interviewTopics() as $topic): ?>
        <li>
          <strong><?= e($topic['title']) ?></strong>
          <span><?= e($topic['note']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <h3>Voorbeeldvragen</h3>
    <ol class="media-questions">
      <?php foreach (MediaKit::sampleQuestions() as $q): ?>
        <li><?= e($q) ?></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="section" id="citaten">
  <div class="wrap">
    <h2>Citaten en kernzinnen</h2>
    <p class="section-intro">Geen letterlijke manuscriptcitaten tenzij dat apart is vastgesteld. Onderstaande zinnen zijn campagne- of samenvattingszinnen uit de bekende positionering.</p>
    <div class="media-quotes">
      <?php foreach (MediaKit::quotes() as $q): ?>
        <figure class="media-quote-card">
          <span class="badge"><?= e($q['label']) ?></span>
          <blockquote><p><?= e($q['text']) ?></p></blockquote>
          <?= $copy($q['text'], 'Kopieer zin') ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="beeld">
  <div class="wrap">
    <h2>Beeldmateriaal</h2>
    <p class="section-intro"><?= e(MediaKit::usageRights()) ?></p>
    <div class="asset-grid">
      <?php foreach (array_merge(
          MediaKit::assetsByCategory('book-cover'),
          MediaKit::assetsByCategory('author'),
          MediaKit::assetsByCategory('logos'),
          MediaKit::assetsByCategory('illustrations'),
          MediaKit::assetsByCategory('documents')
      ) as $asset): ?>
        <?php if (($asset['id'] ?? '') === 'kit-zip') {
            continue;
        } ?>
        <article class="asset-card">
          <h3><?= e((string) $asset['title']) ?></h3>
          <p><?= e((string) ($asset['description'] ?? '')) ?></p>
          <?php if (!empty($asset['dimensions'])): ?>
            <p class="hint"><?= e((string) $asset['dimensions']) ?><?= !empty($asset['size_label']) ? ' · ' . e((string) $asset['size_label']) : '' ?></p>
          <?php elseif (!empty($asset['size_label'])): ?>
            <p class="hint"><?= e((string) $asset['size_label']) ?></p>
          <?php endif; ?>
          <?php if (!empty($asset['credit'])): ?>
            <p class="hint">Credit: <?= e((string) $asset['credit']) ?></p>
          <?php elseif (!empty($asset['creator'])): ?>
            <p class="hint">Maker: <?= e((string) $asset['creator']) ?></p>
          <?php endif; ?>
          <?php if (!empty($asset['downloadable'])): ?>
            <p><a class="btn btn-primary" href="<?= e((string) $asset['path']) ?>" download="<?= e((string) $asset['filename']) ?>">Download</a></p>
          <?php else: ?>
            <p class="media-status"><?= e((string) ($asset['status_label'] ?? 'Nog niet beschikbaar')) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="social">
  <div class="wrap">
    <h2>Sociale media-assets</h2>
    <p class="section-intro">Voorbereide formaten. Zolang de definitieve omslag ontbreekt, zijn dit geen definitieve campagnebeelden — alleen placeholders in het manifest. Campagnezinnen hieronder zijn <strong>geen</strong> boekcitaten.</p>
    <ul class="media-topics">
      <?php foreach (MediaKit::socialLines() as $line): ?>
        <li><strong><?= e($line) ?></strong></li>
      <?php endforeach; ?>
    </ul>
    <div class="asset-grid">
      <?php foreach (MediaKit::assetsByCategory('social') as $asset): ?>
        <article class="asset-card">
          <h3><?= e((string) $asset['title']) ?></h3>
          <p><?= e((string) ($asset['description'] ?? '')) ?></p>
          <?php if (!empty($asset['dimensions'])): ?><p class="hint"><?= e((string) $asset['dimensions']) ?></p><?php endif; ?>
          <?php if (!empty($asset['downloadable'])): ?>
            <p><a class="btn btn-primary" href="<?= e((string) $asset['path']) ?>" download="<?= e((string) $asset['filename']) ?>">Download</a></p>
          <?php else: ?>
            <p class="media-status"><?= e((string) ($asset['status_label'] ?? 'Nog niet beschikbaar')) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="factsheet">
  <div class="wrap media-factsheet" id="print-factsheet">
    <h2>Factsheet</h2>
    <p class="section-intro no-print">Printvriendelijk overzicht. Gebruik je browser: Afdrukken → Opslaan als PDF.</p>
    <p class="no-print"><button type="button" class="btn btn-ghost" onclick="window.print()">Factsheet afdrukken / PDF</button></p>

    <div class="factsheet-sheet">
      <h3><?= e(Config::string('BOOK_TITLE')) ?></h3>
      <p class="hook"><?= e(Config::string('BOOK_SUBTITLE')) ?></p>
      <p><strong>Auteur:</strong> <?= e(Config::string('BOOK_AUTHOR')) ?><br>
        <strong>Publicatie:</strong> <?= e(Config::formatReleaseDate()) ?><br>
        <strong>Prijs:</strong> <?= e(Config::priceFormatted()) ?> incl. btw en verzending<br>
        <strong>Website:</strong> www.grippartner.nl<br>
        <strong>Contact:</strong> <?= e(MediaKit::contactEmail()) ?></p>
      <p><strong>Kernstelling:</strong> Niemand is op zoek naar een grippartner, maar iedereen heeft er één nodig.</p>
      <p><?= e(MediaKit::shortDescription()) ?></p>
      <p><strong>Doelgroep:</strong> Ondernemende volwassenen en iedereen die merkt dat belangrijke zaken verliezen van urgente drukte.</p>
      <p><strong>Methode:</strong> twee mensen · één document · wekelijks ± 30 minuten · terugkijken &amp; vooruitkijken · drie maanden testen.</p>
      <p><strong>Persbeelden:</strong> definitieve omslag en auteursfoto’s volgen op www.grippartner.nl/media</p>
    </div>
  </div>
</section>

<section class="section" id="contact">
  <div class="wrap">
    <h2>Interview, recensie-exemplaar of samenwerking aanvragen</h2>
    <p class="section-intro">Mail ook rechtstreeks naar <a href="mailto:<?= e(MediaKit::contactEmail()) ?>"><?= e(MediaKit::contactEmail()) ?></a>.</p>

    <?php if ($success): ?>
      <div class="status-box status-ok">
        <p>Bedankt. Je aanvraag is ontvangen<?= $publicNr !== '' ? ' (referentie ' . e($publicNr) . ')' : '' ?>.</p>
        <p>Korne neemt contact met je op.</p>
      </div>
    <?php else: ?>
      <div class="form-card" style="margin-top:1rem">
        <?php if ($errors): ?>
          <div class="errors" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" id="media-request-form" action="/media#contact">
          <?= Security::csrfField() ?>
          <div class="hp" aria-hidden="true"><label>Website<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
          <div class="form-grid two">
            <label>Naam<input name="name" required maxlength="160" value="<?= e((string) $data['name']) ?>"></label>
            <label>Organisatie of medium<input name="organization" required maxlength="200" value="<?= e((string) $data['organization']) ?>"></label>
          </div>
          <div class="form-grid two" style="margin-top:0.95rem">
            <label>E-mailadres<input type="email" name="email" required maxlength="255" value="<?= e((string) $data['email']) ?>"></label>
            <label>Website of kanaal (optioneel)<input name="channel_url" maxlength="500" value="<?= e((string) $data['channel_url']) ?>" placeholder="URL of @handle"></label>
          </div>
          <div class="form-grid two" style="margin-top:0.95rem">
            <label>Soort aanvraag
              <select name="request_type" required>
                <option value="">Kies…</option>
                <?php foreach (MediaKit::REQUEST_TYPES as $val => $label): ?>
                  <option value="<?= e($val) ?>"<?= ($data['request_type'] ?? '') === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>Gewenste datum (optioneel)<input name="preferred_date" maxlength="40" value="<?= e((string) $data['preferred_date']) ?>" placeholder="bijv. week 38"></label>
          </div>
          <div class="form-grid" style="margin-top:0.95rem">
            <label>Toelichting<textarea name="message" required maxlength="5000" rows="5"><?= e((string) $data['message']) ?></textarea></label>
            <label>Bereik of doelgroep (optioneel)<textarea name="audience_reach" maxlength="1000" rows="3"><?= e((string) $data['audience_reach']) ?></textarea></label>
          </div>
          <p style="margin-top:1.25rem"><button class="btn btn-primary" type="submit">Verstuur aanvraag</button></p>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section" id="promo">
  <div class="wrap">
    <h2>Krijg een gratis exemplaar</h2>
    <p class="section-intro">Deel Grippartner met je eigen woorden — niet alleen de link. Meld daarna wat je hebt gedaan. Als het klopt, volgt een gratis exemplaar.</p>
    <p><a class="btn btn-primary" href="/promo.php">Naar promo-aanvraag</a>
      <a class="btn btn-ghost" href="/#promo">Of bekijk het op de homepage</a></p>
  </div>
</section>

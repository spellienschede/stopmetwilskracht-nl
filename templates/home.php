<?php
use Grippartner\Config;
/** @var string $cover */
/** @var string $productPhoto */
/** @var string $authorPhoto */
/** @var string $cta */
/** @var string $verb */
/** @var string $price */
/** @var string $release */
/** @var string $presaleEnd */
/** @var bool $presaleActive */
/** @var bool $released */
$presaleEndShort = Config::formatPresaleEndDateShort();
$releaseShort = Config::formatReleaseDateShort();
$heroImage = $productPhoto !== '' ? $productPhoto : $cover;
$countdown = Config::countdownLabel();
$bookTitle = Config::string('BOOK_TITLE');
$hook = Config::string('BOOK_HOOK');
?>
<section class="hero-band">
  <div class="wrap hero">
    <div class="hero-copy">
      <div class="hero-intro">
        <?php if ($presaleActive): ?>
          <p class="eyebrow">Pre-order open · tot <?= e($presaleEndShort) ?></p>
        <?php elseif ($released): ?>
          <p class="eyebrow">Nu verkrijgbaar · sinds <?= e($releaseShort) ?></p>
        <?php else: ?>
          <p class="eyebrow">Bestel nu · verzending rond <?= e($releaseShort) ?></p>
        <?php endif; ?>
        <h1><?= e($bookTitle) ?></h1>
      </div>

      <p class="hook"><?= e($hook) ?></p>
      <p class="lede">Maandag denk je: dit wordt de week. Vrijdag weet je het al. Je bent al bezig met groei — maar je plannen verwateren. Dit boek laat zien wat je mist. En wat je daarmee kunt bereiken.</p>

      <?php if ($authorPhoto !== ''): ?>
        <div class="hero-author">
          <img src="<?= e($authorPhoto) ?>?v=podcast3" alt="Korne Pot" width="220" height="220">
          <div>
            <strong>Korne Pot</strong>
            <span>Auteur</span>
          </div>
        </div>
      <?php endif; ?>

      <div class="hero-meta">
        <?php if ($presaleActive): ?>
          <div class="urgency-box">
            <strong><?= e($countdown !== '' ? $countdown : 'Pre-order nu') ?></strong>
            <p>Bestel nu en krijg <strong>€ 10 korting</strong> én <strong>2 bonussen</strong> — gesigneerd exemplaar + online tools. Inclusief btw en verzending.</p>
            <ol class="bonus-list">
              <?php foreach (Config::presaleBonuses() as $bonus): ?>
                <li>
                  <strong><?= e($bonus['title']) ?></strong>
                  <span><?= e($bonus['text']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php else: ?>
          <p class="lede"><strong><?= e($price) ?></strong> — inclusief btw en verzending.</p>
        <?php endif; ?>
      </div>

      <div class="cta-row">
        <?php if ($presaleActive): ?>
          <a class="btn btn-primary btn-lg" href="<?= e(\Grippartner\Config::orderUrl()) ?>">Pre-order met € 10 korting</a>
        <?php else: ?>
          <a class="btn btn-primary btn-lg" href="<?= e(\Grippartner\Config::orderUrl()) ?>"><?= e($cta) ?></a>
        <?php endif; ?>
      </div>
      <p class="cta-hint">Weinig budget? <a href="#promo">Krijg het boek gratis via een echte promo</a>.</p>
    </div>

    <div class="hero-visuals">
      <?php if ($heroImage !== ''): ?>
        <figure class="book-product" id="book-cover-component">
          <img src="<?= e($heroImage) ?>?v=product2" alt="Het boek <?= e($bookTitle) ?> van Korne Pot" width="1080" height="1080">
        </figure>
      <?php elseif ($cover !== ''): ?>
        <div class="book" id="book-cover-component" data-replaceable-cover="1">
          <img class="cover-photo" src="<?= e($cover) ?>?v=wilskracht2" alt="Omslag van het boek <?= e($bookTitle) ?>">
        </div>
      <?php else: ?>
        <div class="book" id="book-cover-component" data-replaceable-cover="1">
          <div class="book-cover">
            <div>
              <span class="book-badge">Tijdelijke omslag</span>
              <p class="book-title"><?= e($bookTitle) ?></p>
              <p class="book-sub"><?= e($hook) ?></p>
            </div>
            <p class="book-author"><?= e(Config::string('BOOK_AUTHOR')) ?></p>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section section-paper" id="waarom">
  <div class="wrap why-panel">
    <p class="eyebrow">Herken je dit?</p>
    <h2>Het probleem is niet wilskracht</h2>
    <p class="lede">Je weet vaak al verrasselijk goed wat verstandig zou zijn. De afstand zit tussen weten en doen — tussen zondagavond en dinsdagmiddag. Dit boek laat zien wat je zelf niet ziet. En wat je daarmee kunt.</p>
    <ul class="why-points">
      <li>
        <strong>Meer uit je leven</strong>
        <span>Op werk. In relaties. In wat je écht doet — zonder steeds opnieuw te beginnen.</span>
      </li>
      <li>
        <strong>Voor wie al bezig is met groei</strong>
        <span>Geen trucjes voor beginners. Voor mensen die plannen maken — en merken dat ze verwateren.</span>
      </li>
      <li>
        <strong>Lees het — dan snap je het</strong>
        <span>Geen methode die we van tevoren uitleggen. Het boek onthult wat je mist en wat je daarmee kunt bereiken.</span>
      </li>
    </ul>
  </div>
</section>

<section class="section section-ink" id="kopen">
  <div class="wrap buy-panel">
    <div class="buy-panel-grid">
      <?php if ($cover !== ''): ?>
        <div class="buy-panel-cover">
          <img src="<?= e($cover) ?>?v=wilskracht2" alt="Omslag van <?= e($bookTitle) ?>" width="280" height="420">
        </div>
      <?php endif; ?>
      <div class="buy-panel-copy">
        <p class="promo-kicker promo-kicker-on-ink">Pre-order</p>
        <h2><?= $presaleActive ? '€ 10 korting + 2 bonussen' : e($verb) . ' het boek' ?></h2>
        <?php if ($presaleActive): ?>
          <p>Bestel nu en je krijgt € 10 korting op <?= e($bookTitle) ?>, plus een gesigneerd exemplaar en toegang tot de online tools. Daarna is het boek <?= e(Config::regularPriceFormatted()) ?> — zonder die bonussen.</p>
          <?php if ($countdown !== ''): ?>
            <p class="muted" data-countdown-label><?= e($countdown) ?> · tot <?= e($presaleEndShort) ?></p>
          <?php endif; ?>
          <div class="urgency-box">
            <strong>Wat je krijgt bij pre-order</strong>
            <ol class="bonus-list">
              <li>
                <strong>€ 10 korting</strong>
                <span>Nu <?= e($price) ?> i.p.v. <?= e(Config::regularPriceFormatted()) ?> — inclusief btw en verzending.</span>
              </li>
              <?php foreach (Config::presaleBonuses() as $bonus): ?>
                <li>
                  <strong><?= e($bonus['title']) ?></strong>
                  <span><?= e($bonus['text']) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php else: ?>
          <p class="muted"><?= e($price) ?> per exemplaar · inclusief btw en verzending</p>
          <p class="muted"><?= e(Config::availabilityText()) ?></p>
        <?php endif; ?>
        <p class="muted">Veilig betalen via iDEAL.</p>
        <p class="buy-panel-actions">
          <?php if ($presaleActive): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(\Grippartner\Config::orderUrl()) ?>">Pre-order met € 10 korting</a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(\Grippartner\Config::orderUrl()) ?>"><?= e($cta) ?></a>
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
</section>

<?php if (Config::isPresentationOpen()): ?>
<section class="section section-paper" id="boekpresentatie">
  <div class="wrap promo-panel">
    <p class="promo-kicker">Online</p>
    <h2>Boekpresentatie — <?= e(Config::formatPresentationDateShort()) ?> om <?= e(Config::formatPresentationTime()) ?></h2>
    <p>Op <?= e(Config::formatPresentationLabel()) ?> presenteer ik <em><?= e($bookTitle) ?></em> online. Kort, persoonlijk, met ruimte voor vragen. Gratis — meld je aan, dan stuur ik je de link.</p>
    <p class="promo-panel-actions"><a class="btn btn-primary btn-lg" href="/boekpresentatie.php">Aanmelden voor de presentatie</a></p>
  </div>
</section>
<?php endif; ?>

<section class="section section-yellow" id="promo">
  <div class="wrap promo-panel promo-panel-featured">
    <p class="promo-kicker">Gratis exemplaar</p>
    <h2>Krijg het boek gratis — in ruil voor een echte promo</h2>
    <p>Heb je weinig budget, maar wel bereik bij vrienden, volgers of collega’s? Deel <em><?= e($bookTitle) ?></em> serieus onder de aandacht. Als je dat doet, sturen we je graag een gratis exemplaar.</p>
    <p>Geen winactie. Geen trucjes. Wel een eerlijke ruil: jij brengt het boek onder de aandacht, wij sturen het boek.</p>
    <p>Alleen de link of advertentie delen is niet genoeg. Voeg altijd je eigen woorden toe: waarom jij dit boek de moeite waard vindt, wat het je heeft gebracht, of voor wie het interessant is.</p>

    <ol class="steps-mini promo-steps">
      <li><strong>Deel het boek</strong> — met je eigen aanbeveling: post, story, WhatsApp, posters, of iets eigens (eventueel plus de link).</li>
      <li><strong>Meld wat je deed</strong> — vul het korte formulier in (link optioneel).</li>
      <li><strong>Ontvang het boek</strong> — als het een echte bijdrage is, sturen we je een gratis exemplaar.</li>
    </ol>

    <p class="promo-ideas-label">Voorbeelden van een echte promo:</p>
    <ul class="promo-ideas">
      <li>een Instagram-post met je eigen tip of ervaring</li>
      <li>een verhaal op Instagram waarin je uitlegt waarom je het aanraadt</li>
      <li>acht vrienden geappt met je persoonlijke reden</li>
      <li>een post op Facebook of X met je eigen woorden</li>
      <li>postertjes opgehangen met je eigen tekst erbij</li>
      <li>of je eigen creatieve actie — zolang je eigen aanbeveling erbij staat</li>
    </ul>
    <p class="promo-panel-actions"><a class="btn btn-promo btn-lg" href="/promo.php">Ja, ik wil het gratis boek</a></p>
    <p class="cta-hint">Al gedeeld? Op de volgende pagina meld je wat je hebt gedaan.</p>
  </div>
</section>

<section class="section section-paper" id="faq">
  <div class="wrap faq">
    <h2>Veelgestelde vragen</h2>
    <details>
      <summary>Voor wie is dit boek?</summary>
      <p>Voor mensen die al bezig zijn met persoonlijke groei — maar merken dat hun beste plannen verwateren. Je weet wat belangrijk is. Toch wint de dag weer. Niet omdat je lui bent. Dit boek is voor jou als je klaar bent om te ontdekken wat je zelf niet ziet.</p>
    </details>
    <details>
      <summary>Tot wanneer kan ik pre-orderen?</summary>
      <p>Pre-order is nu open tot <?= e($presaleEnd) ?>. Je krijgt dan € 10 korting (<?= e(Config::priceFormatted()) ?> i.p.v. <?= e(Config::regularPriceFormatted()) ?>) én 2 bonussen. Daarna is het boek verkrijgbaar voor <?= e(Config::regularPriceFormatted()) ?> — zonder die pre-orderbonussen.</p>
    </details>
    <details><summary>Wanneer verschijnt het boek?</summary><p>Het boek verschijnt op <?= e($release) ?> en wordt dan verzonden. De online boekpresentatie is op <?= e(Config::formatPresentationLabel()) ?> — <a href="/boekpresentatie.php">meld je daar gratis voor aan</a>.</p></details>
    <?php if ($presaleActive): ?>
    <details>
      <summary>Welke bonussen krijg ik bij pre-order?</summary>
      <p>Alleen bij pre-order tot <?= e($presaleEndShort) ?> krijg je € 10 korting én 2 bonussen:</p>
      <ol>
        <?php foreach (Config::presaleBonuses() as $bonus): ?>
          <li><strong><?= e($bonus['title']) ?>:</strong> <?= e($bonus['text']) ?></li>
        <?php endforeach; ?>
      </ol>
      <p>Gratis promo-exemplaren vallen hier niet onder.</p>
    </details>
    <details>
      <summary>Wat gebeurt er met de bonussen na <?= e($presaleEndShort) ?>?</summary>
      <p>Dan geldt de normale prijs van <?= e(Config::regularPriceFormatted()) ?> en vervallen beide bonussen: de gesigneerde editie met persoonlijke boodschap én de toegang tot de online omgeving met tools. Je kunt het boek daarna nog gewoon bestellen, maar zonder pre-orderkorting en zonder die bonussen.</p>
    </details>
    <?php endif; ?>
    <details><summary>Is <?= e($price) ?> inclusief verzending?</summary><p>Ja. <?= e($price) ?> inclusief btw en verzending.</p></details>
    <details>
      <summary>Hoe werkt de online boekpresentatie?</summary>
      <p>Op <?= e(Config::formatPresentationLabel()) ?> presenteer ik het boek live online. Aanmelden is gratis via <a href="/boekpresentatie.php">de aanmeldpagina</a>. Dichter bij de datum stuur ik je de link.</p>
    </details>
    <details>
      <summary>Hoe werkt de gratis promo?</summary>
      <p>Eerst deel je <em><?= e($bookTitle) ?></em> met je eigen woorden (Instagram, Facebook, WhatsApp, posters, of iets eigens). Alleen de link doorsturen is niet genoeg — vertel waarom jij het aanraadt. Daarna meld je wat je hebt gedaan. Korne bevestigt — en dan volgt het gratis boek. Je hoeft niet vooraf te melden wat je wilt doen.</p>
    </details>
    <details><summary>Hoe werkt betalen?</summary><p>Via Mollie, met iDEAL.</p></details>
  </div>
</section>

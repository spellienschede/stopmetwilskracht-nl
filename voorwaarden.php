<?php
declare(strict_types=1);

use Grippartner\Config;

require __DIR__ . '/src/bootstrap.php';

$title = 'Voorwaarden – ' . Config::string('BOOK_TITLE');
$description = 'Algemene voorwaarden voor bestellingen op grippartner.nl';
$canonical = Config::baseUrl() . '/voorwaarden.php';

$business = Config::string('LEGAL_BUSINESS_NAME') ?: 'Het 2e kwadrant';
$address = Config::string('LEGAL_ADDRESS') ?: 'Haaksbergerstraat 709, 7545 PH Enschede';
$kvk = Config::string('LEGAL_KVK') ?: '99420694';
$btw = Config::string('LEGAL_BTW') ?: 'NL868983019B01';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:46rem">
    <h1>Voorwaarden</h1>
    <h2>Verkoper</h2>
    <p><?= e($business) ?> · <?= e($address) ?> · KvK <?= e($kvk) ?> · btw <?= e($btw) ?> · info@kornepot.nl</p>
    <h2>Product</h2>
    <p>Het boek <em><?= e(Config::string('BOOK_TITLE')) ?></em> van Korne Pot. Normale prijs: <?= e(Config::regularPriceFormatted()) ?> per exemplaar. Bij pre-order tot <?= e(Config::formatPresaleEndDate()) ?> geldt <?= e(Config::formatCents(Config::int('BOOK_PRESALE_PRICE_CENTS', 3900))) ?> per exemplaar. Prijzen inclusief btw en verzending (heen), tenzij anders vermeld.</p>
    <h2>Pre-order / levering</h2>
    <p>Pre-order met korting en bonussen is mogelijk tot <?= e(Config::formatPresaleEndDate()) ?>. Het boek verschijnt op <?= e(Config::formatReleaseDate()) ?>; verzending volgt rond of na die datum. Daarna zo snel als praktisch mogelijk.</p>
    <h2>Betaling</h2>
    <p>Betaling via Mollie (o.a. iDEAL). Een bestelling is pas definitief na bevestigde betaling.</p>
    <h2>Promo / gratis exemplaar</h2>
    <p>Je deelt het boek en meldt wat je hebt gedaan. Alleen de link of advertentie doorsturen is geen volwaardige promo: voeg je eigen woorden toe over waarom je het boek aanraadt. Een melding geeft op zichzelf nog geen recht op een gratis boek. Pas na bevestiging door Korne kan een gratis exemplaar worden toegekend.</p>
    <h2>Herroeping / retour</h2>
    <p>Als consument kun je binnen 14 dagen na ontvangst van het boek de overeenkomst herroepen, zonder opgave van reden. Het boek moet onbeschadigd en in originele staat retourneerbaar zijn.</p>
    <p>Je meldt de herroeping via <a href="mailto:info@kornepot.nl">info@kornepot.nl</a>. Daarna stuur je het boek terug. De kosten van de retourzending zijn voor jouw rekening. Na ontvangst en controle van de retourzending wordt het betaalde bedrag (exclusief eventuele retourkosten die jij zelf hebt gemaakt) zo spoedig mogelijk teruggestort, uiterlijk binnen 14 dagen.</p>
    <h2>Aansprakelijkheid</h2>
    <p>Voor zover de wet dat toelaat is aansprakelijkheid beperkt tot het orderbedrag.</p>
    <h2>Contact</h2>
    <p><a href="mailto:info@kornepot.nl">info@kornepot.nl</a></p>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

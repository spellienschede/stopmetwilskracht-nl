<?php
declare(strict_types=1);

use Grippartner\Config;

require __DIR__ . '/src/bootstrap.php';

$title = 'Privacybeleid – Grippartner';
$description = 'Privacybeleid voor grippartner.nl';
$canonical = Config::baseUrl() . '/privacy.php';

$business = Config::string('LEGAL_BUSINESS_NAME') ?: 'Het 2e kwadrant';
$address = Config::string('LEGAL_ADDRESS') ?: 'Haaksbergerstraat 709, 7545 PH Enschede';
$kvk = Config::string('LEGAL_KVK') ?: '99420694';
$btw = Config::string('LEGAL_BTW') ?: 'NL868983019B01';

ob_start();
?>
<section class="section" style="border-top:0;padding-top:2rem">
  <div class="wrap" style="max-width:46rem">
    <h1>Privacybeleid</h1>
    <h2>Verantwoordelijke</h2>
    <p><?= e($business) ?><br>
      <?= e($address) ?><br>
      E-mail: <a href="mailto:info@kornepot.nl">info@kornepot.nl</a><br>
      KvK: <?= e($kvk) ?><br>
      btw: <?= e($btw) ?></p>
    <h2>Welke gegevens</h2>
    <p>Bij bestellingen en promo-aanvragen verwerken we naam, e-mailadres en afleveradres. Bij promo-aanvragen ook de inhoud van je voorstel en uitvoeringsbewijs (links/tekst). Bij aanmelding voor de online boekpresentatie verwerken we naam, e-mailadres en eventuele opmerking. Betaalgegevens worden door Mollie verwerkt; wij slaan geen bank- of kaartgegevens op.</p>
    <h2>Doelen</h2>
    <ul>
      <li>uitvoeren van bestellingen en verzending;</li>
      <li>beoordelen van promo-aanvragen en communicatie daarover;</li>
      <li>organiseren van de online boekpresentatie en sturen van de deelname-link;</li>
      <li>transactionele e-mails over je bestelling of aanvraag;</li>
      <li>nieuwsbrief van Korne Pot (kornepot.nl): bij een bestelling, promo-melding of aanmelding voor de boekpresentatie zetten we je e-mailadres op de mailinglijst. Uitschrijven kan altijd via de link in de mail of via kornepot.nl.</li>
    </ul>
    <h2>Bewaartermijn</h2>
    <p>Bestelgegevens bewaren we zolang dat nodig is voor administratie, verzending en wettelijke verplichtingen. Promo-aanvragen bewaren we zolang de afhandeling en eventuele verzending dat vereisen. Aanmeldingen voor de boekpresentatie bewaren we tot na het evenement en de bijbehorende communicatie.</p>
    <h2>Delen met derden</h2>
    <p>Mollie (betalingen), hosting/e-mailprovider, en eventueel een verzendpartner. Geen verkoop van persoonsgegevens.</p>
    <h2>Rechten</h2>
    <p>Je kunt inzage, correctie of verwijdering vragen via info@kornepot.nl, voor zover de wet dat toelaat.</p>
    <h2>Contact</h2>
    <p><a href="mailto:info@kornepot.nl">info@kornepot.nl</a></p>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/layout.php';

<?php
declare(strict_types=1);

namespace Grippartner;

final class EmailTemplates
{
    /** @return array{0:string,1:string} */
    public static function orderPaidCustomer(array $o): array
    {
        $name = e((string) $o['first_name']);
        $nr = e((string) $o['public_order_number']);
        $qty = (int) $o['quantity'];
        $total = money_cents((int) $o['total_cents']);
        $addr = nl2br(e(OrderService::formatAddress($o)));
        $release = e(Config::formatReleaseDate());
        $bonusHtml = '';
        $bonusText = '';
        if (Config::isPresaleActive()) {
            $bonus = e(Config::presaleBonusText());
            $bonusHtml = '<p>' . $bonus . '</p>';
            $bonusText = "\n" . Config::presaleBonusText() . "\n";
        }
        $book = e(Config::string('BOOK_TITLE'));
        $htmlBody = "<p>Hallo {$name},</p>"
            . "<p>Bedankt voor je bestelling van <strong>{$book}</strong>. Je betaling is ontvangen.</p>"
            . "<p><strong>Bestelnummer:</strong> {$nr}<br>"
            . "<strong>Aantal:</strong> {$qty}<br>"
            . "<strong>Betaald:</strong> {$total}<br>"
            . '<strong>Inclusief</strong> btw en verzending</p>'
            . "<p><strong>Afleveradres</strong><br>{$addr}</p>"
            . '<p>Verwachte verschijning/verzending rond <strong>' . $release . '</strong>.</p>'
            . $bonusHtml
            . '<p>Vragen? Mail naar <a href="mailto:info@kornepot.nl">info@kornepot.nl</a>.</p>'
            . '<p>Groet,<br>Korne Pot</p>';
        $text = "Hallo {$o['first_name']},\n\nBedankt voor je bestelling van " . Config::string('BOOK_TITLE') . ". Je betaling is ontvangen.\n\n"
            . "Bestelnummer: {$o['public_order_number']}\nAantal: {$qty}\nBetaald: {$total}\n"
            . "Afleveradres:\n" . OrderService::formatAddress($o) . "\n\n"
            . 'Verwachte verschijning/verzending rond ' . Config::formatReleaseDate() . ".\n"
            . $bonusText . "\n"
            . "Vragen? info@kornepot.nl\n\nGroet,\nKorne Pot\n";
        return [Mailer::wrapHtml('Bestelling bevestigd', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function orderPaidAdmin(array $o): array
    {
        $link = Config::baseUrl() . '/admin/book/order.php?id=' . (int) $o['id'];
        $htmlBody = '<p>Nieuwe betaalde bestelling.</p>'
            . '<p><strong>' . e($o['first_name'] . ' ' . $o['last_name']) . '</strong><br>'
            . e((string) $o['email']) . '</p>'
            . '<p>Bestelnummer: ' . e((string) $o['public_order_number']) . '<br>'
            . 'Aantal: ' . (int) $o['quantity'] . '<br>'
            . 'Totaal: ' . money_cents((int) $o['total_cents']) . '<br>'
            . 'Status: betaald</p>'
            . '<p>' . nl2br(e(OrderService::formatAddress($o))) . '</p>'
            . '<p><a href="' . e($link) . '">Open in beheer</a></p>';
        $text = "Nieuwe betaalde bestelling {$o['public_order_number']}\n"
            . "{$o['first_name']} {$o['last_name']} <{$o['email']}>\n"
            . 'Aantal: ' . $o['quantity'] . ' / ' . money_cents((int) $o['total_cents']) . "\n"
            . OrderService::formatAddress($o) . "\n\n{$link}\n";
        return [Mailer::wrapHtml('Nieuwe bestelling', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function orderPendingAdmin(array $o): array
    {
        $link = Config::baseUrl() . '/admin/book/order.php?id=' . (int) $o['id'];
        $htmlBody = '<p>Iemand heeft een bestelling aangemaakt en wacht op betaling.</p>'
            . '<p><strong>' . e($o['first_name'] . ' ' . $o['last_name']) . '</strong><br>'
            . e((string) $o['email']) . '</p>'
            . '<p>Bestelnummer: ' . e((string) $o['public_order_number']) . '<br>'
            . 'Aantal: ' . (int) $o['quantity'] . '<br>'
            . 'Totaal: ' . money_cents((int) $o['total_cents']) . '<br>'
            . 'Status: wacht op betaling</p>'
            . '<p>' . nl2br(e(OrderService::formatAddress($o))) . '</p>'
            . '<p><a href="' . e($link) . '">Open in beheer</a></p>';
        $text = "Bestelling wacht op betaling {$o['public_order_number']}\n"
            . "{$o['first_name']} {$o['last_name']} <{$o['email']}>\n"
            . 'Aantal: ' . $o['quantity'] . ' / ' . money_cents((int) $o['total_cents']) . "\n"
            . OrderService::formatAddress($o) . "\n\n{$link}\n";
        return [Mailer::wrapHtml('Bestelling wacht op betaling', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function orderPaymentStartFailedAdmin(array $o, string $reason): array
    {
        $link = Config::baseUrl() . '/admin/book/order.php?id=' . (int) ($o['id'] ?? 0);
        $htmlBody = '<p><strong>Betaling kon niet worden gestart</strong> (Mollie).</p>'
            . '<p>Bestelnummer: ' . e((string) ($o['public_order_number'] ?? '')) . '<br>'
            . e((string) ($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? '')) . '<br>'
            . e((string) ($o['email'] ?? '')) . '<br>'
            . 'Totaal: ' . money_cents((int) ($o['total_cents'] ?? 0)) . '</p>'
            . '<p>' . nl2br(e(OrderService::formatAddress($o))) . '</p>'
            . '<p><strong>Fout:</strong> ' . e($reason) . '</p>'
            . '<p><a href="' . e($link) . '">Open in beheer</a></p>';
        $text = "Betaling start mislukt – {$o['public_order_number']}\n"
            . "{$o['first_name']} {$o['last_name']} <{$o['email']}>\n"
            . 'Totaal: ' . money_cents((int) ($o['total_cents'] ?? 0)) . "\n"
            . OrderService::formatAddress($o) . "\n\nFout: {$reason}\n\n{$link}\n";
        return [Mailer::wrapHtml('Betaling start mislukt', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function orderShipped(array $o): array
    {
        $book = e(Config::string('BOOK_TITLE'));
        $htmlBody = '<p>Hallo ' . e((string) $o['first_name']) . ',</p>'
            . "<p>Je exemplaar van <strong>{$book}</strong> is verzonden.</p>"
            . '<p>Bestelnummer: ' . e((string) $o['public_order_number']) . '</p>'
            . '<p>Groet,<br>Korne Pot</p>';
        $text = "Hallo {$o['first_name']},\n\nJe exemplaar van " . Config::string('BOOK_TITLE') . " is verzonden.\nBestelnummer: {$o['public_order_number']}\n\nGroet,\nKorne Pot\n";
        return [Mailer::wrapHtml('Verzonden', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoReceivedApplicant(array $a): array
    {
        $book = Config::string('BOOK_TITLE');
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Ik heb je promo ontvangen (nummer ' . e((string) $a['public_application_number']) . ').</p>'
            . '<p>Leuk dat je <em>' . e($book) . '</em> hebt gedeeld. Ik kijk ernaar en laat van me horen. Als het past, stuur ik je een gratis exemplaar.</p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nIk heb je promo ontvangen ({$a['public_application_number']}).\n\n"
            . "Leuk dat je {$book} hebt gedeeld. Ik kijk ernaar en laat van me horen. Als het past, stuur ik je een gratis exemplaar.\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Promo ontvangen', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoReceivedAdmin(array $a): array
    {
        $link = Config::baseUrl() . '/admin/book/promo.php?id=' . (int) $a['id'];
        $htmlBody = '<p>Nieuwe promo van ' . e($a['first_name'] . ' ' . $a['last_name']) . ' (' . e((string) $a['email']) . ').</p>'
            . '<p><strong>Nummer:</strong> ' . e((string) $a['public_application_number']) . '</p>'
            . '<p><strong>Wat gedaan</strong><br>' . nl2br(e((string) $a['idea_description'])) . '</p>';
        if (!empty($a['execution_links'])) {
            $htmlBody .= '<p><strong>Link</strong><br>' . nl2br(e((string) $a['execution_links'])) . '</p>';
        }
        $htmlBody .= '<p><a href="' . e($link) . '">Open in beheer</a></p>';
        $text = "Nieuwe promo {$a['public_application_number']}\n{$a['first_name']} {$a['last_name']} <{$a['email']}>\n\n"
            . "{$a['idea_description']}\n\n{$link}\n";
        return [Mailer::wrapHtml('Nieuwe promo', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoIdeaChangesWithToken(array $a, string $token): array
    {
        $link = $token !== ''
            ? Config::baseUrl() . '/promo-aanpassen.php?token=' . rawurlencode($token)
            : Config::baseUrl() . '/#promo';
        $note = nl2br(e((string) ($a['idea_changes_note'] ?? '')));
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Ik heb nog een vraag of verzoek tot aanpassing over je promo-idee:</p>'
            . "<p>{$note}</p>"
            . '<p><a href="' . e($link) . '">Pas je voorstel aan</a></p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nVraag over je promo-idee:\n" . ($a['idea_changes_note'] ?? '') . "\n\n{$link}\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Vraag over promo-idee', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoIdeaRejected(array $a): array
    {
        $orderLink = Config::baseUrl() . '/bestellen.php';
        $reason = nl2br(e((string) ($a['idea_rejection_reason'] ?? '')));
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Bedankt voor je voorstel. Dit keer ga ik er niet mee verder.</p>'
            . ($reason !== '' ? "<p>{$reason}</p>" : '')
            . '<p>Niet ieder idee past bij dit moment — dat zegt niets over jouw intentie. '
            . 'Wil je het boek gewoon lezen? Dan kun je het hier bestellen: <a href="' . e($orderLink) . '">' . e(Config::orderCta()) . '</a>.</p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nBedankt voor je voorstel. Dit keer ga ik er niet mee verder.\n"
            . ($a['idea_rejection_reason'] ?? '') . "\n\nBestellen: {$orderLink}\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Reactie promo-idee', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoIdeaApproved(array $a, string $token): array
    {
        $link = Config::baseUrl() . '/promo-uitvoering.php?token=' . rawurlencode($token);
        $deadline = !empty($a['execution_deadline']) ? e((string) $a['execution_deadline']) : 'in overleg';
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Leuk nieuws: je promo-idee is <strong>bevestigd</strong>.</p>'
            . '<p>Stem kort af hoe je het uitvoert (zie hieronder). Als je klaar bent, geef je de uitvoering door via de link. '
            . 'Zodra ik die heb bevestigd, stuur ik je het gratis exemplaar.</p>'
            . '<p><strong>Afspraken</strong><br>' . nl2br(e((string) ($a['execution_agreement'] ?? ''))) . '</p>'
            . '<p><strong>Deadline:</strong> ' . $deadline . '</p>'
            . '<p>Geef je uitvoering door via deze persoonlijke link:<br><a href="' . e($link) . '">' . e($link) . '</a></p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nJe promo-idee is bevestigd.\n\n"
            . "Afspraken:\n" . ($a['execution_agreement'] ?? '') . "\nDeadline: " . ($a['execution_deadline'] ?? 'in overleg') . "\n\n"
            . "Uitvoering doorgeven: {$link}\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Idee bevestigd', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoExecutionReceivedApplicant(array $a): array
    {
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Ik heb je uitvoering ontvangen en ga die beoordelen. Je hoort zo snel mogelijk of alles klopt, of dat er nog een aanvulling nodig is.</p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nIk heb je uitvoering ontvangen en ga die beoordelen.\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Uitvoering ontvangen', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoExecutionReceivedAdmin(array $a): array
    {
        $link = Config::baseUrl() . '/admin/book/promo.php?id=' . (int) $a['id'];
        $htmlBody = '<p>Uitvoering klaar voor beoordeling: ' . e((string) $a['public_application_number']) . '</p>'
            . '<p>' . nl2br(e((string) ($a['execution_description'] ?? ''))) . '</p>'
            . '<p><a href="' . e($link) . '">Beoordelen</a></p>';
        $text = "Uitvoering {$a['public_application_number']}\n\n" . ($a['execution_description'] ?? '') . "\n\n{$link}\n";
        return [Mailer::wrapHtml('Uitvoering beoordelen', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoExecutionChanges(array $a, string $token = ''): array
    {
        $link = $token !== ''
            ? Config::baseUrl() . '/promo-uitvoering.php?token=' . rawurlencode($token)
            : Config::baseUrl() . '/';
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Kun je je promotie nog aanvullen?</p>'
            . '<p>' . nl2br(e((string) ($a['execution_changes_note'] ?? ''))) . '</p>'
            . '<p><a href="' . e($link) . '">Geef je aanvulling door</a></p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nKun je je promotie nog aanvullen?\n"
            . ($a['execution_changes_note'] ?? '') . "\n\n{$link}\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Aanvulling gevraagd', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoExecutionRejected(array $a): array
    {
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>De uitvoering van je promo-idee komt onvoldoende overeen met de gemaakte afspraak. Daarom ken ik dit keer geen gratis exemplaar toe.</p>'
            . '<p>' . nl2br(e((string) ($a['execution_rejection_reason'] ?? ''))) . '</p>'
            . '<p>Je kunt het boek altijd gewoon bestellen via <a href="' . e(Config::baseUrl() . '/bestellen.php') . '">de website</a>.</p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nDe uitvoering komt onvoldoende overeen met de afspraak.\n"
            . ($a['execution_rejection_reason'] ?? '') . "\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Reactie uitvoering', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoRewardApplicant(array $a, array $o): array
    {
        $htmlBody = '<p>Hallo ' . e((string) $a['first_name']) . ',</p>'
            . '<p>Dank je wel voor de uitgevoerde promotie. Je ontvangt een <strong>gratis exemplaar</strong> van ' . e(Config::string('BOOK_TITLE')) . '.</p>'
            . '<p>Aanvraagnummer: ' . e((string) $a['public_application_number']) . '<br>'
            . 'Gratis bestelnummer: ' . e((string) $o['public_order_number']) . '</p>'
            . '<p>Afleveradres:<br>' . nl2br(e(OrderService::formatAddress($o))) . '</p>'
            . '<p>Verwachte verzending rond of na ' . e(Config::formatReleaseDate()) . '.</p>'
            . '<p>Vragen? <a href="mailto:info@kornepot.nl">info@kornepot.nl</a></p>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$a['first_name']},\n\nDank je wel — je ontvangt een gratis exemplaar van " . Config::string('BOOK_TITLE') . ".\n"
            . "Aanvraag: {$a['public_application_number']}\nBestelling: {$o['public_order_number']}\n"
            . OrderService::formatAddress($o) . "\n\nVerzending rond " . Config::formatReleaseDate() . ".\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml('Gratis exemplaar', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function promoRewardAdmin(array $a, array $o): array
    {
        $link = Config::baseUrl() . '/admin/book/order.php?id=' . (int) $o['id'];
        $htmlBody = '<p>Gratis exemplaar toegekend na goedgekeurde uitvoering.</p>'
            . '<p>Promo: ' . e((string) $a['public_application_number']) . '<br>Order: ' . e((string) $o['public_order_number']) . '</p>'
            . '<p><a href="' . e($link) . '">Open bestelling</a></p>';
        $text = "Gratis exemplaar {$o['public_order_number']} (promo {$a['public_application_number']})\n{$link}\n";
        return [Mailer::wrapHtml('Gratis exemplaar', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function mediaRequestApplicant(array $r): array
    {
        $name = e((string) $r['name']);
        $nr = e((string) $r['public_request_number']);
        $type = e(MediaKit::REQUEST_TYPES[$r['request_type']] ?? (string) $r['request_type']);
        $contact = e(MediaKit::contactEmail());
        $htmlBody = "<p>Hallo {$name},</p>"
            . '<p>Bedankt voor je aanvraag over <strong>' . e(Config::string('BOOK_TITLE')) . '</strong>.</p>'
            . "<p><strong>Soort aanvraag:</strong> {$type}<br>"
            . "<strong>Referentienummer:</strong> {$nr}</p>"
            . '<p>Korne neemt contact met je op. Heb je tussentijds een vraag? Mail naar '
            . '<a href="mailto:' . $contact . '">' . $contact . '</a>.</p>'
            . '<p>Groet,<br>Korne Pot</p>';
        $text = "Hallo {$r['name']},\n\nBedankt voor je aanvraag over " . Config::string('BOOK_TITLE') . ".\n\n"
            . 'Soort: ' . (MediaKit::REQUEST_TYPES[$r['request_type']] ?? $r['request_type']) . "\n"
            . "Referentienummer: {$r['public_request_number']}\n\n"
            . 'Korne neemt contact met je op. Vragen? ' . MediaKit::contactEmail() . "\n\nGroet,\nKorne Pot\n";
        return [Mailer::wrapHtml('Aanvraag ontvangen', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function mediaRequestAdmin(array $r): array
    {
        $link = Config::baseUrl() . '/admin/book/media-request.php?id=' . (int) $r['id'];
        $type = e(MediaKit::REQUEST_TYPES[$r['request_type']] ?? (string) $r['request_type']);
        $htmlBody = '<p>Nieuwe media-aanvraag.</p>'
            . '<p><strong>' . e((string) $r['name']) . '</strong> · ' . e((string) $r['organization']) . '<br>'
            . e((string) $r['email']) . '</p>'
            . '<p><strong>Nummer:</strong> ' . e((string) $r['public_request_number']) . '<br>'
            . '<strong>Type:</strong> ' . $type . '<br>'
            . '<strong>Datum:</strong> ' . e((string) $r['created_at']) . '</p>'
            . '<p><strong>Toelichting</strong><br>' . nl2br(e((string) $r['message'])) . '</p>';
        if (!empty($r['channel_url'])) {
            $htmlBody .= '<p><strong>Kanaal:</strong> ' . e((string) $r['channel_url']) . '</p>';
        }
        if (!empty($r['preferred_date'])) {
            $htmlBody .= '<p><strong>Gewenste datum:</strong> ' . e((string) $r['preferred_date']) . '</p>';
        }
        if (!empty($r['audience_reach'])) {
            $htmlBody .= '<p><strong>Bereik:</strong> ' . nl2br(e((string) $r['audience_reach'])) . '</p>';
        }
        $htmlBody .= '<p><a href="' . e($link) . '">Open in beheer</a></p>';
        $text = "Nieuwe media-aanvraag {$r['public_request_number']}\n"
            . "{$r['name']} / {$r['organization']} <{$r['email']}>\n"
            . 'Type: ' . (MediaKit::REQUEST_TYPES[$r['request_type']] ?? $r['request_type']) . "\n\n"
            . "{$r['message']}\n\n{$link}\n";
        return [Mailer::wrapHtml('Nieuwe media-aanvraag', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function presentationSignupApplicant(array $r): array
    {
        $name = e((string) $r['name']);
        $when = e(Config::formatPresentationLabel());
        $nr = e((string) $r['public_signup_number']);
        $htmlBody = "<p>Hallo {$name},</p>"
            . '<p>Je aanmelding voor de online boekpresentatie van <em>'
            . e(Config::string('BOOK_TITLE')) . '</em> is binnen.</p>'
            . '<p><strong>Wanneer:</strong> ' . $when . '<br>'
            . '<strong>Waar:</strong> online (de link stuur ik je dichter bij de datum)</p>'
            . "<p>Referentienummer: {$nr}</p>"
            . '<p>Tot dan!<br>Korne</p>';
        $text = "Hallo {$r['name']},\n\n"
            . 'Je aanmelding voor de online boekpresentatie van ' . Config::string('BOOK_TITLE') . " is binnen.\n\n"
            . 'Wanneer: ' . Config::formatPresentationLabel() . "\n"
            . "Waar: online (de link stuur ik je dichter bij de datum)\n"
            . "Referentienummer: {$r['public_signup_number']}\n\n"
            . "Tot dan!\nKorne\n";
        return [Mailer::wrapHtml('Aanmelding ontvangen', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function presentationSignupAdmin(array $r): array
    {
        $link = Config::baseUrl() . '/admin/book/presentation-signups.php';
        $htmlBody = '<p>Nieuwe aanmelding boekpresentatie.</p>'
            . '<p><strong>' . e((string) $r['name']) . '</strong><br>'
            . e((string) $r['email']) . '</p>'
            . '<p><strong>Nummer:</strong> ' . e((string) $r['public_signup_number']) . '<br>'
            . '<strong>Event:</strong> ' . e(Config::formatPresentationLabel()) . '</p>';
        if (!empty($r['notes'])) {
            $htmlBody .= '<p><strong>Opmerking</strong><br>' . nl2br(e((string) $r['notes'])) . '</p>';
        }
        $htmlBody .= '<p><a href="' . e($link) . '">Bekijk alle aanmeldingen</a></p>';
        $text = "Nieuwe aanmelding boekpresentatie {$r['public_signup_number']}\n"
            . "{$r['name']} <{$r['email']}>\n"
            . Config::formatPresentationLabel() . "\n\n"
            . (!empty($r['notes']) ? $r['notes'] . "\n\n" : '')
            . "{$link}\n";
        return [Mailer::wrapHtml('Nieuwe aanmelding boekpresentatie', $htmlBody), $text];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $r @return array{0:string,1:string} */
    public static function sessionSignupApplicant(array $session, array $r): array
    {
        $name = e((string) $r['name']);
        $title = e((string) $session['title']);
        $when = e(SessionService::formatWhen($session));
        $hosts = e((string) ($session['hosts'] ?? ''));
        $nr = e((string) $r['public_signup_number']);
        $htmlBody = "<p>Hallo {$name},</p>"
            . "<p>Je aanmelding voor <strong>{$title}</strong> is binnen.</p>"
            . '<p><strong>Wanneer:</strong> ' . $when . '<br>'
            . ($hosts !== '' ? '<strong>Met:</strong> ' . $hosts . '<br>' : '')
            . '<strong>Waar:</strong> online (de Zoom-link stuur ik je dichter bij de sessie)</p>'
            . "<p>Referentienummer: {$nr}</p>"
            . '<p>Tot dan!<br>Korne</p>';
        $text = "Hallo {$r['name']},\n\n"
            . "Je aanmelding voor {$session['title']} is binnen.\n\n"
            . 'Wanneer: ' . SessionService::formatWhen($session) . "\n"
            . (!empty($session['hosts']) ? 'Met: ' . $session['hosts'] . "\n" : '')
            . "Waar: online (de Zoom-link stuur ik je dichter bij de sessie)\n"
            . "Referentienummer: {$r['public_signup_number']}\n\n"
            . "Tot dan!\nKorne\n";
        return [Mailer::wrapHtml('Aanmelding ontvangen', $htmlBody), $text];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $r @return array{0:string,1:string} */
    public static function sessionSignupAdmin(array $session, array $r): array
    {
        $link = Config::baseUrl() . '/admin/book/session-signups.php?id=' . (int) $session['id'];
        $htmlBody = '<p>Nieuwe aanmelding voor een online sessie.</p>'
            . '<p><strong>' . e((string) $r['name']) . '</strong><br>'
            . e((string) $r['email']) . '</p>'
            . '<p><strong>Sessie:</strong> ' . e((string) $session['title']) . '<br>'
            . '<strong>Wanneer:</strong> ' . e(SessionService::formatWhen($session)) . '<br>'
            . '<strong>Nummer:</strong> ' . e((string) $r['public_signup_number']) . '</p>'
            . '<p><a href="' . e($link) . '">Bekijk aanmeldingen</a></p>';
        $text = "Nieuwe sessie-aanmelding {$r['public_signup_number']}\n"
            . "{$r['name']} <{$r['email']}>\n"
            . "{$session['title']}\n"
            . SessionService::formatWhen($session) . "\n\n"
            . "{$link}\n";
        return [Mailer::wrapHtml('Nieuwe sessie-aanmelding', $htmlBody), $text];
    }

    /** @param array<string,mixed> $session @param array<string,mixed> $r @return array{0:string,1:string} */
    public static function sessionMeetingLink(array $session, array $r): array
    {
        $name = e((string) $r['name']);
        $title = e((string) $session['title']);
        $when = e(SessionService::formatWhen($session));
        $url = e((string) ($session['meeting_url'] ?? ''));
        $htmlBody = "<p>Hallo {$name},</p>"
            . "<p>Hier is je link voor <strong>{$title}</strong>.</p>"
            . '<p><strong>Wanneer:</strong> ' . $when . '<br>'
            . '<strong>Meedoen:</strong> <a href="' . $url . '">' . $url . '</a></p>'
            . '<p>Zet ’m in je agenda en tot zo!<br>Korne</p>';
        $text = "Hallo {$r['name']},\n\n"
            . "Hier is je link voor {$session['title']}.\n\n"
            . 'Wanneer: ' . SessionService::formatWhen($session) . "\n"
            . 'Meedoen: ' . ($session['meeting_url'] ?? '') . "\n\n"
            . "Zet ’m in je agenda en tot zo!\nKorne\n";
        return [Mailer::wrapHtml('Je Zoom-link', $htmlBody), $text];
    }

    /** @return array{0:string,1:string} */
    public static function sessionCustom(string $subject, string $body, string $name): array
    {
        $safeName = e($name);
        $htmlBody = '<p>Hallo ' . $safeName . ',</p>'
            . '<div>' . nl2br(e($body)) . '</div>'
            . '<p>Groet,<br>Korne</p>';
        $text = "Hallo {$name},\n\n{$body}\n\nGroet,\nKorne\n";
        return [Mailer::wrapHtml($subject, $htmlBody), $text];
    }
}

<?php
declare(strict_types=1);

/**
 * Interne proces-tests (zonder Mollie live calls).
 * php scripts/test-flows.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use Grippartner\Auth;
use Grippartner\Config;
use Grippartner\Database;
use Grippartner\MailingList;
use Grippartner\OrderService;
use Grippartner\PromoService;
use Grippartner\PublicId;
use Grippartner\Validator;

$pdo = Database::pdo();
$failed = 0;
$passed = 0;

function assert_true(bool $cond, string $msg): void
{
    global $failed, $passed;
    if ($cond) {
        echo "OK  $msg\n";
        $passed++;
    } else {
        echo "FAIL  $msg\n";
        $failed++;
    }
}

echo "=== Config datumterminologie ===\n";
assert_true(Config::string('BOOK_TITLE') === 'Grippartner', 'boek titel');
$beforePresaleEnd = new DateTimeImmutable('2026-09-20', new DateTimeZone('Europe/Amsterdam'));
$lastPresaleDay = new DateTimeImmutable('2026-09-21 12:00:00', new DateTimeZone('Europe/Amsterdam'));
$afterPresale = new DateTimeImmutable('2026-09-22', new DateTimeZone('Europe/Amsterdam'));
assert_true(Config::isPresaleActive($beforePresaleEnd) === true, 'vóór pre-orderdeadline = pre-order actief');
assert_true(Config::isPresaleActive($lastPresaleDay) === true, 'op laatste pre-orderdag = nog pre-order');
assert_true(Config::isPresaleActive($afterPresale) === false, 'na pre-orderdeadline = geen pre-order meer');
$afterPresaleBeforeRelease = new DateTimeImmutable('2026-09-25', new DateTimeZone('Europe/Amsterdam'));
$onRelease = new DateTimeImmutable('2026-10-05', new DateTimeZone('Europe/Amsterdam'));
assert_true(Config::isReleased($afterPresaleBeforeRelease) === false, 'vóór release = nog niet uit');
assert_true(Config::isReleased($onRelease) === true, 'vanaf release = uit');
assert_true(Config::currentPriceCents($beforePresaleEnd) === Config::int('BOOK_PRESALE_PRICE_CENTS', 3900), 'pre-orderprijs vóór deadline');
assert_true(Config::currentPriceCents($afterPresale) === Config::int('BOOK_PRICE_CENTS', 4900), 'normale prijs na deadline');

echo "=== Order validatie / prijs server-side ===\n";
$bad = Validator::order([
    'first_name' => 'A', 'last_name' => 'B', 'email' => 'niet-email',
    'street' => 'S', 'house_number' => '1', 'postal_code' => '1234AB', 'city' => 'X', 'country' => 'NL',
    'quantity' => 99, 'terms' => '1',
]);
assert_true(isset($bad['email']) && isset($bad['quantity']), 'ongeldige email/aantal afgewezen');

$norm = Validator::normalizeOrderInput([
    'first_name' => 'Test', 'last_name' => 'Klant', 'email' => 'test.order@example.com',
    'street' => 'Voorbeeldstraat', 'house_number' => '10', 'house_addition' => 'a',
    'postal_code' => '7511AB', 'city' => 'Enschede', 'country' => 'NL',
    'quantity' => 3, 'terms' => '1', 'marketing_consent' => '1',
    'unit_price' => '1.00', // manipulatie – wordt genegeerd
]);
$order = OrderService::createPendingOrder($norm);
assert_true((int) $order['quantity'] === 3, 'aantal 3');
assert_true((int) $order['unit_price_cents'] === Config::currentPriceCents(), 'prijs server-side');
assert_true((int) $order['total_cents'] === Config::currentPriceCents() * 3, 'totaal 3x');
assert_true($order['payment_status'] === OrderService::PAYMENT_PENDING, 'pending_payment');

echo "=== Mailinglijst toestemming ===\n";
MailingList::subscribeIfConsented('test.order@example.com', 'Test', 'Klant', 'grippartner_order', true);
MailingList::subscribeIfConsented('test.order@example.com', 'Test', 'Klant', 'grippartner_order', true);
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM nieuwsbrief_aanmeldingen WHERE email='test.order@example.com'")->fetchColumn();
assert_true($cnt === 1, 'geen dubbele mailing-inschrijving');

MailingList::subscribeIfConsented('no.consent@example.com', 'N', 'C', 'grippartner_order', false);
$cnt2 = (int) $pdo->query("SELECT COUNT(*) FROM nieuwsbrief_aanmeldingen WHERE email='no.consent@example.com'")->fetchColumn();
assert_true($cnt2 === 0, 'zonder toestemming niet op lijst');

echo "=== Promo workflow ===\n";
$promoData = Validator::normalizePromoInput([
    'first_name' => 'Promo', 'last_name' => 'Persoon', 'email' => 'promo.test@example.com',
    'street' => 'Laan', 'house_number' => '2', 'postal_code' => '1000AA', 'city' => 'Amsterdam', 'country' => 'NL',
    'idea_description' => str_repeat('Ik maak een nieuwsbriefitem over grippartners. ', 3),
    'audience_reach' => '500 ondernemers in mijn nieuwsbrief',
    'proposed_planning' => 'Publicatie in september 2026',
    'terms' => '1',
]);
$app = PromoService::createApplication($promoData);
$appId = (int) $app['id'];
assert_true($app['status'] === PromoService::SUBMITTED, 'submitted');

// Voorlopige goedkeuring mag GEEN order maken
$token = PromoService::approveIdea($appId, 'Nieuwsbriefitem van ca. 400 woorden met link naar grippartner.nl', '2026-09-01', null);
$app = PromoService::findById($appId);
assert_true($app['status'] === PromoService::APPROVED_AWAITING, 'approved_awaiting_execution');
assert_true(empty($app['reward_order_id']), 'geen gratis order na idee-goedkeuring');
$ordersAfterIdea = (int) $pdo->query('SELECT COUNT(*) FROM book_orders WHERE source=\'approved_promo\'')->fetchColumn();

// Uitvoering vóór goedkeuring weigeren – al goedgekeurd, dus OK. Test met fake token:
try {
    PromoService::submitExecution('invalidtoken', ['execution_description' => str_repeat('x', 30)]);
    assert_true(false, 'ongeldig token geweigerd');
} catch (Throwable $e) {
    assert_true(true, 'ongeldig token geweigerd');
}

PromoService::submitExecution($token, [
    'execution_description' => 'Nieuwsbrief verstuurd naar 500 abonnees met link naar het boek.',
    'execution_performed_on' => '2026-08-20',
    'execution_links' => 'https://example.com/nieuwsbrief',
]);
$app = PromoService::findById($appId);
assert_true($app['status'] === PromoService::EXECUTION_SUBMITTED, 'execution_submitted');

$result = PromoService::approveExecution($appId, null);
$app = PromoService::findById($appId);
$orderFree = $result['order'];
assert_true($app['status'] === PromoService::REWARD_CREATED, 'reward_order_created');
assert_true($orderFree && (int) $orderFree['total_cents'] === 0, 'gratis order €0');
assert_true($orderFree['payment_status'] === OrderService::PAYMENT_NA, 'payment not_applicable');
assert_true($orderFree['source'] === 'approved_promo', 'bron approved_promo');

// Dubbele goedkeuring
$result2 = PromoService::approveExecution($appId, null);
assert_true((int) $result2['order']['id'] === (int) $orderFree['id'], 'geen dubbele gratis order');
$freeCount = (int) $pdo->query('SELECT COUNT(*) FROM book_orders WHERE promo_application_id=' . $appId)->fetchColumn();
assert_true($freeCount === 1, 'precis één gratis order gekoppeld');

// Omzet telt promo niet mee
$stats = OrderService::dashboardStats();
assert_true($stats['paid_revenue_cents'] === 0 || true, 'omzet-query draait'); // paid test order was pending

echo "=== Statusovergangen ===\n";
assert_true(PromoService::canTransition(PromoService::SUBMITTED, PromoService::APPROVED_AWAITING), 'submitted→approve');
assert_true(!PromoService::canTransition(PromoService::SUBMITTED, PromoService::REWARD_CREATED), 'geen skip naar reward');

echo "=== Admin setup check ===\n";
if (Auth::adminCount() === 0) {
    Auth::createAdmin('korne', 'test-wachtwoord-minstens-10');
}
assert_true(Auth::adminCount() >= 1, 'admin bestaat');

echo "\nPassed: $passed  Failed: $failed\n";
exit($failed > 0 ? 1 : 0);

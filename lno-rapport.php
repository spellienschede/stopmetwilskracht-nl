<?php
/**
 * LNO Rapport per e-mail
 * POST (JSON): email, activities [{ text, category }], score { L, N, O }
 * Rate limit: max 3 per IP per uur.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Alleen POST toegestaan']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data['email'])) {
    echo json_encode(['ok' => false, 'error' => 'Ongeldige aanvraag of e-mail ontbreekt']);
    exit;
}

$email = trim($data['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'Ongeldig e-mailadres']);
    exit;
}

// Rate limit: max 3 per IP per uur
$rateDir = __DIR__ . '/lno_rapport_rate';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0755, true);
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = $rateDir . '/' . preg_replace('/[^a-fA-F0-9\.]/', '_', $ip) . '.txt';
$now = time();
$window = 3600; // 1 uur
$requests = [];
if (file_exists($rateFile)) {
    $content = file_get_contents($rateFile);
    $requests = array_filter(array_map('intval', explode("\n", trim($content))), function ($t) use ($now, $window) {
        return $t > $now - $window;
    });
}
if (count($requests) >= 3) {
    echo json_encode(['ok' => false, 'error' => 'Te veel rapporten aangevraagd. Probeer over een uur opnieuw.']);
    exit;
}
$requests[] = $now;
file_put_contents($rateFile, implode("\n", $requests), LOCK_EX);

require_once __DIR__ . '/config.php';

$activities = isset($data['activities']) && is_array($data['activities']) ? $data['activities'] : [];
$score = isset($data['score']) && is_array($data['score']) ? $data['score'] : ['L' => 0, 'N' => 0, 'O' => 0];
$total = array_sum($score);
$pL = $total > 0 ? round(100 * ($score['L'] ?? 0) / $total) : 0;
$pN = $total > 0 ? round(100 * ($score['N'] ?? 0) / $total) : 0;
$pO = $total > 0 ? round(100 * ($score['O'] ?? 0) / $total) : 0;
$lnoScore = $total > 0 ? min(10, (int) round(10 * (($score['L'] ?? 0) / $total * 1 + ($score['N'] ?? 0) / $total * 0.5))) : 0;

$byCat = ['L' => [], 'N' => [], 'O' => []];
foreach ($activities as $a) {
    if (!is_array($a) || empty($a['text'])) continue;
    $cat = isset($a['category']) && in_array($a['category'], ['L', 'N', 'O'], true) ? $a['category'] : 'N';
    $byCat[$cat][] = $a['text'];
}

$body = "Hallo,\n\n";
$body .= "Hier is je LNO-agendarapport van Grippartner.\n\n";
$body .= "--- LNO-framework ---\n";
$body .= "L = Leverage: activiteiten waar input en output sterk positief samenhangen.\n";
$body .= "N = Neutraal: activiteiten waar input en output in balans zijn.\n";
$body .= "O = Overhead: activiteiten waar de relatie tussen input en output kleiner is dan 1.\n\n";
$body .= "--- Je score ---\n";
$body .= "Leverage:  " . $pL . "%\n";
$body .= "Neutraal:   " . $pN . "%\n";
$body .= "Overhead:   " . $pO . "%\n";
$body .= "LNO-score (1-10): " . $lnoScore . "\n\n";
$body .= "--- Leverage ---\n";
$body .= (count($byCat['L']) > 0 ? implode("\n", array_map(function ($t) { return "• " . $t; }, $byCat['L'])) : "Geen") . "\n\n";
$body .= "--- Neutraal ---\n";
$body .= (count($byCat['N']) > 0 ? implode("\n", array_map(function ($t) { return "• " . $t; }, $byCat['N'])) : "Geen") . "\n\n";
$body .= "--- Overhead ---\n";
$body .= (count($byCat['O']) > 0 ? implode("\n", array_map(function ($t) { return "• " . $t; }, $byCat['O'])) : "Geen") . "\n\n";
$body .= "Met vriendelijke groet,\nGrippartner\n";

$subject = "Je LNO-agendarapport – Grippartner";
$headers = "From: " . (defined('FROM_NAME') ? FROM_NAME : 'Grippartner') . " <" . (defined('FROM_EMAIL') ? FROM_EMAIL : 'info@grippartner.nl') . ">\r\n";
$headers .= "Reply-To: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'info@grippartner.nl') . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = @mail($email, $subject, $body, $headers);
if (!$sent) {
    echo json_encode(['ok' => false, 'error' => 'E-mail kon niet worden verstuurd. Probeer het later opnieuw.']);
    exit;
}

echo json_encode(['ok' => true]);
?>

<?php
/**
 * Email handler voor uitschrijvingen
 * Configureer dit als email forwarding rule in je hosting:
 * Alle emails naar info@grippartner.nl met onderwerp "uitschrijven" 
 * of "unsubscribe" worden doorgestuurd naar dit script
 */

require_once 'config.php';

// Dit script wordt aangeroepen via email forwarding
// Je moet dit configureren in je hosting panel

// Haal email uit headers of POST data (afhankelijk van je email forwarding setup)
$email = $_POST['email'] ?? $_GET['email'] ?? '';

// Probeer email uit headers te halen
if (empty($email)) {
    $headers = getallheaders();
    $email = $headers['X-Original-From'] ?? $headers['From'] ?? '';
    // Extract email from "Name <email@example.com>" format
    if (preg_match('/<(.+?)>/', $email, $matches)) {
        $email = $matches[1];
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Already valid email
    } else {
        $email = '';
    }
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die('Ongeldig emailadres');
}

$pdo = getDbConnection();
if (!$pdo) {
    http_response_code(500);
    die('Database connectie mislukt');
}

try {
    $stmt = $pdo->prepare("UPDATE nieuwsbrief_aanmeldingen SET uitgeschreven = 1, uitgeschreven_datum = NOW() WHERE email = ?");
    $stmt->execute([strtolower($email)]);
    
    if ($stmt->rowCount() > 0) {
        // Bevestigingsmail
        $subject = "Je bent uitgeschreven";
        $message = "Je bent succesvol uitgeschreven van de nieuwsbrief.\n\n";
        $message .= "Je ontvangt geen wekelijkse tips meer.\n\n";
        $message .= "Opnieuw aanmelden kan via " . NEWSLETTER_SITE_URL;

        sendNewsletterMail($email, $subject, $message);
        
        http_response_code(200);
        echo "Uitgeschreven";
    } else {
        http_response_code(404);
        echo "Emailadres niet gevonden";
    }
} catch (PDOException $e) {
    error_log("Uitschrijf fout: " . $e->getMessage());
    http_response_code(500);
    die('Database fout');
}
?>







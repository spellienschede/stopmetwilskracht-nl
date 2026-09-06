<?php
// Output buffering om te voorkomen dat output wordt verzonden voordat headers
ob_start();

// Error logging inschakelen voor debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Functie om naar eigen logfile te schrijven
function logError($message) {
    $logFile = __DIR__ . '/aanmelden_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    // Maak bestand aan met juiste permissies als het niet bestaat
    if (!file_exists($logFile)) {
        @touch($logFile);
        @chmod($logFile, 0666);
    }
    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    // Ook naar standaard error log
    error_log($message);
}

// Shutdown handler om laatste errors te vangen
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logError("Fatal error in aanmelden.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        if (!headers_sent()) {
            header('Location: tip.php?error=database');
        }
    }
});

require_once 'config.php';

// Controleren of constants bestaan
if (!defined('FROM_EMAIL') || !defined('FROM_NAME')) {
    logError("ERROR: FROM_EMAIL or FROM_NAME not defined in config.php");
    ob_end_clean();
    header('Location: tip.php?error=database');
    exit;
}

// Veiligheid: alleen POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Location: tip.php');
    exit;
}

// Data ophalen en valideren
$voornaam = trim($_POST['voornaam'] ?? '');
$achternaam = trim($_POST['achternaam'] ?? '');
$email = trim($_POST['email'] ?? '');

// Validatie
if (empty($voornaam) || empty($achternaam) || empty($email)) {
    ob_end_clean();
    header('Location: tip.php?error=velden');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_end_clean();
    header('Location: tip.php?error=email');
    exit;
}

// Sanitize
$voornaam = htmlspecialchars($voornaam, ENT_QUOTES, 'UTF-8');
$achternaam = htmlspecialchars($achternaam, ENT_QUOTES, 'UTF-8');
$email = strtolower(trim($email));

// Database connectie
$pdo = getDbConnection();
if (!$pdo) {
    logError("Database connection failed. DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . ", DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED'));
    ob_end_clean();
    header('Location: tip.php?error=database');
    exit;
}

try {
    // Check of email al bestaat
    $stmt = $pdo->prepare("SELECT id, uitgeschreven FROM nieuwsbrief_aanmeldingen WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['uitgeschreven'] == 0) {
            // Al aangemeld en niet uitgeschreven
            ob_end_clean();
            header('Location: tip.php?success=al_aangemeld');
            exit;
        } else {
            // Opnieuw aanmelden na uitschrijving
            $stmt = $pdo->prepare("UPDATE nieuwsbrief_aanmeldingen SET voornaam = ?, achternaam = ?, uitgeschreven = 0, uitgeschreven_datum = NULL WHERE email = ?");
            $stmt->execute([$voornaam, $achternaam, $email]);
        }
    } else {
        // Nieuwe aanmelding
        $stmt = $pdo->prepare("INSERT INTO nieuwsbrief_aanmeldingen (voornaam, achternaam, email, aanmeldmoment) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$voornaam, $achternaam, $email]);
        
        // Notificatie mail naar info@kornepot.nl bij nieuwe aanmelding
        if (function_exists('mail')) {
            $notification_subject = "Nieuwe aanmelding nieuwsbrief Korne Pot";
            $notification_message = "Er is een nieuwe aanmelding voor de nieuwsbrief:\n\n";
            $notification_message .= "Naam: $voornaam $achternaam\n";
            $notification_message .= "Email: $email\n";
            $notification_message .= "Aangemeld op: " . date('d-m-Y H:i:s') . "\n";
            
            $notification_headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
            $notification_headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
            $notification_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $notification_headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            
            $mail_sent = @mail('info@kornepot.nl', $notification_subject, $notification_message, $notification_headers);
            if (!$mail_sent) {
                logError("Failed to send notification mail to info@kornepot.nl");
            }
        } else {
            logError("mail() function not available on server");
        }
    }
    
    // Bevestigingsmail versturen
    if (function_exists('mail')) {
        $subject = "Welkom bij de wekelijkse tip van Korne Pot";
        $message = "Hallo $voornaam,\n\n";
        $message .= "Bedankt voor je aanmelding.\n\n";
        $message .= "Elke week ontvang je een praktische tip die je direct kunt toepassen.\n\n";
        $message .= "Geen spam. Geen verkooppraatjes. Alleen waardevolle inzichten.\n\n";
        $message .= "Groet,\n";
        $message .= "Korne Pot\n\n";
        $message .= "---\n";
        $message .= "Uitschrijven kan altijd door te antwoorden op deze email.";

        $extraHeaders = "X-Mailer: PHP/" . phpversion() . "\r\n";
        $mail_sent = sendNewsletterMail($email, $subject, $message, $extraHeaders);
        if (!$mail_sent) {
            logError("Failed to send confirmation mail to $email");
        }
    } else {
        logError("mail() function not available on server");
    }
    
    ob_end_clean(); // Clear any output before redirect
    header('Location: tip.php?success=1');
    exit;
    
} catch (PDOException $e) {
    $error_msg = "Database error in aanmelden.php: " . $e->getMessage();
    logError($error_msg);
    logError("Stack trace: " . $e->getTraceAsString());
    logError("POST data: " . print_r($_POST, true));
    logError("SQL State: " . $e->getCode());
    ob_end_clean();
    header('Location: tip.php?error=database');
    exit;
} catch (Throwable $e) {
    $error_msg = "Fatal error in aanmelden.php: " . $e->getMessage();
    logError($error_msg);
    logError("Stack trace: " . $e->getTraceAsString());
    logError("POST data: " . print_r($_POST, true));
    ob_end_clean();
    header('Location: tip.php?error=database');
    exit;
}
?>





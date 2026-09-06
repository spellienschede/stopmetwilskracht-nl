<?php
declare(strict_types=1);

/**
 * Backward-compatible config voor bestaande scripts (aanmelden.php, lno-rapport.php, …).
 * Secrets horen in config.local.php (niet in Git).
 */

$localFile = __DIR__ . '/config.local.php';
$local = is_readable($localFile) ? require $localFile : [];
if (!is_array($local)) {
    $local = [];
}

if (!defined('DB_HOST')) {
    define('DB_HOST', (string) ($local['DB_HOST'] ?? 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string) ($local['DB_NAME'] ?? 'grippartner'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string) ($local['DB_USER'] ?? 'grip'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string) ($local['DB_PASS'] ?? ''));
}

if (!defined('FROM_EMAIL')) {
    define('FROM_EMAIL', 'info@grippartner.nl');
}
if (!defined('FROM_NAME')) {
    define('FROM_NAME', 'Grippartner');
}
if (!defined('NEWSLETTER_FROM_EMAIL')) {
    define('NEWSLETTER_FROM_EMAIL', (string) ($local['MAIL_FROM_ADDRESS'] ?? 'info@kornepot.nl'));
}
if (!defined('NEWSLETTER_FROM_NAME')) {
    define('NEWSLETTER_FROM_NAME', 'Korne Pot');
}
if (!defined('NEWSLETTER_SITE_URL')) {
    define('NEWSLETTER_SITE_URL', 'https://kornepot.nl');
}

if (!function_exists('sendNewsletterMail')) {
    function sendNewsletterMail(string $to, string $subject, string $message, string $extraHeaders = ''): bool
    {
        $from = NEWSLETTER_FROM_EMAIL;
        $headers = 'From: ' . NEWSLETTER_FROM_NAME . ' <' . $from . ">\r\n";
        $headers .= 'Reply-To: ' . $from . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= $extraHeaders;
        ini_set('sendmail_from', $from);
        return @mail($to, $subject, $message, $headers, '-f' . $from);
    }
}

if (!function_exists('getDbConnection')) {
    function getDbConnection()
    {
        $logFile = __DIR__ . '/aanmelden_errors.log';
        $logError = static function ($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
            error_log($message);
        };

        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $logError('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}

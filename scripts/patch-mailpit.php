<?php
$p = dirname(__DIR__) . '/config.local.php';
$c = require $p;
if (!is_array($c)) {
    exit(1);
}
// Lokaal → Mailpit (geen wachtwoord)
$c['SMTP_HOST'] = '127.0.0.1';
$c['SMTP_PORT'] = 1025;
$c['SMTP_SECURE'] = 'none';
$c['SMTP_USER'] = '';
$c['SMTP_PASS'] = '';
$c['MAIL_BCC_ADMIN'] = true;
unset($c['MAIL_SEND_REAL'], $c['MAIL_OUTBOX_ONLY']);
file_put_contents($p, "<?php\n\nreturn " . var_export($c, true) . ";\n");
echo "Mailpit gezet\n";

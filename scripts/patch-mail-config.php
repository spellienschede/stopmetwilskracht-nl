<?php
$p = dirname(__DIR__) . '/config.local.php';
$c = require $p;
if (!is_array($c)) {
    fwrite(STDERR, "config.local.php invalid\n");
    exit(1);
}
$c['ADMIN_NOTIFICATION_EMAIL'] = 'info@kornepot.nl';
$c['MAIL_BCC_ADMIN'] = true;
$c['SMTP_HOST'] = $c['SMTP_HOST'] ?? 'mail.kornepot.nl';
$c['SMTP_PORT'] = $c['SMTP_PORT'] ?? 465;
$c['SMTP_USER'] = $c['SMTP_USER'] ?? 'info@kornepot.nl';
$c['SMTP_PASS'] = $c['SMTP_PASS'] ?? '';
$c['SMTP_SECURE'] = $c['SMTP_SECURE'] ?? 'ssl';
$c['MAIL_SEND_REAL'] = $c['MAIL_SEND_REAL'] ?? false;
file_put_contents($p, "<?php\n\nreturn " . var_export($c, true) . ";\n");
echo "config.local.php mail-keys bijgewerkt\n";

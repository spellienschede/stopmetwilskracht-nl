<?php
declare(strict_types=1);

namespace Grippartner;

final class Mailer
{
    /**
     * Stuur HTML + plain text, maximaal één keer per mail_key.
     */
    public static function sendOnce(string $mailKey, string $to, string $subject, string $html, string $text): bool
    {
        $pdo = Database::pdo();
        try {
            $ins = $pdo->prepare('INSERT INTO outbound_mail_log (mail_key, recipient, subject) VALUES (?, ?, ?)');
            $ins->execute([substr($mailKey, 0, 160), substr($to, 0, 255), substr($subject, 0, 255)]);
        } catch (\PDOException $e) {
            if ((int) $e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate')) {
                return true;
            }
            throw $e;
        }

        $ok = self::send($to, $subject, $html, $text);
        if (!$ok) {
            $del = $pdo->prepare('DELETE FROM outbound_mail_log WHERE mail_key = ?');
            $del->execute([substr($mailKey, 0, 160)]);
            Logger::error('Mail send failed', ['to' => $to, 'key' => $mailKey]);
        }
        return $ok;
    }

    public static function send(string $to, string $subject, string $html, string $text): bool
    {
        $fromEmail = Config::string('MAIL_FROM_ADDRESS', 'info@kornepot.nl');
        $fromName = Config::string('MAIL_FROM_NAME', 'Korne Pot / Grippartner');
        $bccAdmin = Config::bool('MAIL_BCC_ADMIN', true);
        $admin = self::adminEmail();

        if (self::smtpConfigured()) {
            return self::sendSmtp($to, $subject, $html, $text, $fromEmail, $fromName, $bccAdmin ? $admin : null);
        }

        // Alleen outbox als expliciet gevraagd
        if (Config::bool('MAIL_OUTBOX_ONLY', false)) {
            $dir = app_path('storage/logs');
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $entry = "----\n" . date('c') . " To: {$to}";
            if ($bccAdmin && strcasecmp($to, $admin) !== 0) {
                $entry .= " | Bcc: {$admin}";
            }
            $entry .= "\nSubject: {$subject}\n{$text}\n";
            @file_put_contents($dir . '/mail-outbox.log', $entry, FILE_APPEND | LOCK_EX);
            return true;
        }

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers = [];
        $headers[] = 'From: ' . self::encodeAddress($fromName, $fromEmail);
        $headers[] = 'Reply-To: ' . $fromEmail;
        if ($bccAdmin && strcasecmp($to, $admin) !== 0) {
            $headers[] = 'Bcc: ' . $admin;
        }
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $headers[] = 'X-Mailer: Grippartner';

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        ini_set('sendmail_from', $fromEmail);
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $fromEmail);
    }

    public static function adminEmail(): string
    {
        $email = trim(Config::string('ADMIN_NOTIFICATION_EMAIL', 'info@kornepot.nl'));
        return $email !== '' ? $email : 'info@kornepot.nl';
    }

    /** SMTP host, of lokaal automatisch Mailpit. */
    public static function smtpConfigured(): bool
    {
        return Config::string('SMTP_HOST') !== '' || Config::string('APP_ENV') === 'local';
    }

    /** @return array{host:string,port:int,secure:string,user:string,pass:string} */
    private static function smtpSettings(): array
    {
        $host = Config::string('SMTP_HOST');
        $port = Config::int('SMTP_PORT', 0);
        $secure = strtolower(Config::string('SMTP_SECURE', ''));
        $user = Config::string('SMTP_USER');
        $pass = Config::string('SMTP_PASS');

        // Laragon Mailpit: geen auth, poort 1025, UI http://127.0.0.1:8025
        if ($host === '' && Config::string('APP_ENV') === 'local') {
            return [
                'host' => '127.0.0.1',
                'port' => 1025,
                'secure' => 'none',
                'user' => '',
                'pass' => '',
            ];
        }

        if ($port <= 0) {
            $port = ($secure === 'ssl') ? 465 : (($secure === 'tls') ? 587 : 1025);
        }
        if ($secure === '') {
            $secure = ($port === 465) ? 'ssl' : (($port === 587) ? 'tls' : 'none');
        }

        return [
            'host' => $host,
            'port' => $port,
            'secure' => $secure,
            'user' => $user,
            'pass' => $pass,
        ];
    }

    private static function encodeAddress(string $name, string $email): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    private static function sendSmtp(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $fromEmail,
        string $fromName,
        ?string $bcc = null
    ): bool {
        $settings = self::smtpSettings();
        $host = $settings['host'];
        $port = $settings['port'];
        $user = $settings['user'];
        $pass = $settings['pass'];
        $secure = $settings['secure'];

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            Logger::error('SMTP connect failed', ['detail' => $errstr, 'errno' => $errno, 'host' => $host, 'port' => $port]);
            return false;
        }
        stream_set_timeout($socket, 20);

        $read = static function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $cmd = static function (string $command) use ($socket, $read): string {
            fwrite($socket, $command . "\r\n");
            return $read();
        };
        $okCode = static function (string $response, array $codes): bool {
            return in_array((int) substr(trim($response), 0, 3), $codes, true);
        };

        try {
            if (!$okCode($read(), [220])) {
                throw new \RuntimeException('greeting');
            }
            $ehloHost = parse_url(Config::baseUrl(), PHP_URL_HOST) ?: 'grippartner.nl';
            if (!$okCode($cmd('EHLO ' . $ehloHost), [250])) {
                throw new \RuntimeException('ehlo');
            }
            if ($secure === 'tls') {
                if (!$okCode($cmd('STARTTLS'), [220])) {
                    throw new \RuntimeException('starttls');
                }
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('tls');
                }
                if (!$okCode($cmd('EHLO ' . $ehloHost), [250])) {
                    throw new \RuntimeException('ehlo_tls');
                }
            }
            // Mailpit e.d.: geen AUTH als user/pass leeg zijn
            if ($user !== '' && $pass !== '') {
                if (!$okCode($cmd('AUTH LOGIN'), [334])) {
                    throw new \RuntimeException('auth');
                }
                if (!$okCode($cmd(base64_encode($user)), [334])) {
                    throw new \RuntimeException('user');
                }
                if (!$okCode($cmd(base64_encode($pass)), [235])) {
                    throw new \RuntimeException('pass');
                }
            }
            if (!$okCode($cmd('MAIL FROM:<' . $fromEmail . '>'), [250])) {
                throw new \RuntimeException('mail_from');
            }
            if (!$okCode($cmd('RCPT TO:<' . $to . '>'), [250, 251])) {
                throw new \RuntimeException('rcpt');
            }
            if ($bcc !== null && $bcc !== '' && strcasecmp($bcc, $to) !== 0) {
                if (!$okCode($cmd('RCPT TO:<' . $bcc . '>'), [250, 251])) {
                    throw new \RuntimeException('rcpt_bcc');
                }
            }
            if (!$okCode($cmd('DATA'), [354])) {
                throw new \RuntimeException('data');
            }

            $boundary = 'b_' . bin2hex(random_bytes(8));
            $data = 'From: ' . self::encodeAddress($fromName, $fromEmail) . "\r\n";
            $data .= 'To: <' . $to . ">\r\n";
            if ($bcc !== null && $bcc !== '' && strcasecmp($bcc, $to) !== 0) {
                $data .= 'Bcc: <' . $bcc . ">\r\n";
            }
            $data .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
            $data .= "MIME-Version: 1.0\r\n";
            $data .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n\r\n";
            $data .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$text}\r\n\r\n";
            $data .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n\r\n";
            $data .= "--{$boundary}--\r\n.";

            if (!$okCode($cmd($data), [250])) {
                throw new \RuntimeException('body');
            }
            $cmd('QUIT');
            fclose($socket);
            return true;
        } catch (\Throwable $e) {
            Logger::error('SMTP send failed', ['step' => $e->getMessage()]);
            fclose($socket);
            // Lokaal: schrijf naar outbox zodat je de mail toch kunt zien
            if (Config::string('APP_ENV') === 'local') {
                $dir = app_path('storage/logs');
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
                $entry = "----\n" . date('c') . " SMTP-FAILED To: {$to}\nSubject: {$subject}\n{$text}\n";
                @file_put_contents($dir . '/mail-outbox.log', $entry, FILE_APPEND | LOCK_EX);
            }
            return false;
        }
    }

    public static function wrapHtml(string $title, string $bodyHtml): string
    {
        return '<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>'
            . e($title) . '</title></head><body style="font-family:Georgia,serif;line-height:1.55;color:#1c1917;background:#faf7f2;padding:24px;">'
            . '<div style="max-width:560px;margin:0 auto;background:#fff;padding:28px 24px;border:1px solid #e7e0d5;">'
            . $bodyHtml
            . '<p style="margin-top:28px;font-size:13px;color:#78716c;">Korne Pot · Grippartner<br>info@kornepot.nl</p>'
            . '</div></body></html>';
    }
}

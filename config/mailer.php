<?php
/*
 |--------------------------------------------------------------------
 | OUTGOING EMAIL — used for the password-reset confirmation code
 |--------------------------------------------------------------------
 | Configured entirely through environment variables (never committed):
 |
 |   MAIL_TRANSPORT     smtp (default) | log
 |                      "log" writes the message to logs/mail.log
 |                      instead of sending — for local testing only.
 |   MAIL_HOST          SMTP server, e.g. smtp.gmail.com
 |   MAIL_PORT          587 (STARTTLS, default) or 465 (implicit TLS)
 |   MAIL_USERNAME      SMTP login
 |   MAIL_PASSWORD      SMTP password / app password
 |   MAIL_FROM_ADDRESS  sender address shown to recipients
 |   MAIL_FROM_NAME     sender name (default KULTOURA)
 */

if (!function_exists('kt_send_mail')) {
    function kt_send_mail(string $to, string $subject, string $body): bool
    {
        $transport = strtolower(getenv('MAIL_TRANSPORT') ?: 'smtp');
        $fromAddress = getenv('MAIL_FROM_ADDRESS') ?: 'kultouramalvar@gmail.com';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'KULTOURA';

        if ($transport === 'log') {
            $dir = __DIR__ . '/../logs';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $entry = "=== " . date('Y-m-d H:i:s') . " to: $to\nSubject: $subject\n\n$body\n\n";
            return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND) !== false;
        }

        $host = getenv('MAIL_HOST') ?: '';
        $port = (int) (getenv('MAIL_PORT') ?: 587);
        $user = getenv('MAIL_USERNAME') ?: $fromAddress;
        $pass = getenv('MAIL_PASSWORD') ?: '';
        if ($host === '' || $user === '' || $pass === '' || $fromAddress === '') {
            error_log('Mail not configured: set MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD and MAIL_FROM_ADDRESS.');
            return false;
        }

        $remote = $port === 465 ? "ssl://$host:$port" : "$host:$port";
        $sock = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$sock) {
            error_log("SMTP connect failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($sock, 15);

        $expect = function (string $codePrefix) use ($sock): bool {
            $line = '';
            while (($l = fgets($sock, 512)) !== false) {
                $line .= $l;
                if (isset($l[3]) && $l[3] === ' ') break; // last line of a multi-line reply
            }
            return str_starts_with($line, $codePrefix);
        };
        $send = function (string $cmd) use ($sock): void {
            fwrite($sock, $cmd . "\r\n");
        };

        $ok = $expect('220');
        $send('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $ok = $ok && $expect('250');

        if ($port !== 465) {
            $send('STARTTLS');
            $ok = $ok && $expect('220')
                && stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $ok = $ok && $expect('250');
        }

        $send('AUTH LOGIN');
        $ok = $ok && $expect('334');
        $send(base64_encode($user));
        $ok = $ok && $expect('334');
        $send(base64_encode($pass));
        $ok = $ok && $expect('235');

        $send("MAIL FROM:<$fromAddress>");
        $ok = $ok && $expect('250');
        $send("RCPT TO:<$to>");
        $ok = $ok && $expect('250');
        $send('DATA');
        $ok = $ok && $expect('354');

        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $message = "From: $encodedName <$fromAddress>\r\n"
            . "To: <$to>\r\n"
            . "Subject: $encodedSubject\r\n"
            . "Date: " . date('r') . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body));
        $message = str_replace("\n", "\r\n", $message);

        fwrite($sock, $message . "\r\n.\r\n");
        $ok = $ok && $expect('250');
        $send('QUIT');
        fclose($sock);

        if (!$ok) {
            error_log('SMTP send failed for ' . $to);
        }
        return $ok;
    }
}

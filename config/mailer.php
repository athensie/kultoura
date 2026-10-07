<?php
/*
 |--------------------------------------------------------------------
 | OUTGOING EMAIL — used for the password-reset confirmation code
 |--------------------------------------------------------------------
 | Configured entirely through environment variables (never committed):
 |
 |   MAIL_TRANSPORT     smtp (default) | log | brevo
 |                      "log" writes the message to logs/mail.log
 |                      instead of sending — for local testing only.
 |   BREVO_API_KEY      if set (and transport is not log), sends through
 |                      Brevo's HTTPS API instead of SMTP.
 |   MAIL_HOST          SMTP server (default smtp.gmail.com)
 |   MAIL_PORT          587 (STARTTLS, default) or 465 (implicit TLS)
 |   MAIL_USERNAME      SMTP login (default: MAIL_FROM_ADDRESS)
 |   MAIL_PASSWORD      SMTP password / app password
 |   MAIL_FROM_ADDRESS  sender address (default kultouramalvar@gmail.com)
 |   MAIL_FROM_NAME     sender name (default KULTOURA)
 |
 | SMTP uses PHPMailer, vendored under lib/PHPMailer (no Composer needed).
 */

require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

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

        $brevoKey = getenv('BREVO_API_KEY') ?: '';
        if ($transport === 'brevo') {
            $payload = json_encode([
                'sender'      => ['email' => $fromAddress, 'name' => $fromName],
                'to'          => [['email' => $to]],
                'subject'     => $subject,
                'textContent' => $body,
            ]);
            $ch = curl_init('https://api.brevo.com/v3/smtp/email');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => ['api-key: ' . $brevoKey, 'Content-Type: application/json', 'Accept: application/json'],
            ]);
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status < 200 || $status >= 300) {
                error_log("Brevo send failed ($status): " . $response);
                return false;
            }
            return true;
        }

        $host = getenv('MAIL_HOST') ?: 'smtp.gmail.com';
        $port = (int) (getenv('MAIL_PORT') ?: 587);
        $user = getenv('MAIL_USERNAME') ?: $fromAddress;
        $pass = getenv('MAIL_PASSWORD') ?: '';
        if ($pass === '') {
            error_log('Mail not configured: set MAIL_PASSWORD (and optionally MAIL_HOST, MAIL_USERNAME, MAIL_FROM_ADDRESS).');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = $port === 465
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Timeout = 15;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('PHPMailer send failed: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
            return false;
        }
    }
}

<?php
// KULTOURA — Newsletter (subscribe, welcome digest, announcement dispatch)
// Sends through the existing kt_send_mail() — plain text.

function kt_newsletter_ensure_schema(mysqli $conn): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $conn->query("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            token CHAR(32) NOT NULL,
            status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $r = $conn->query("SHOW COLUMNS FROM announcements LIKE 'newsletter_sent'");
        if ($r && $r->num_rows === 0) {
            if ($conn->query("ALTER TABLE announcements ADD COLUMN newsletter_sent TINYINT(1) NOT NULL DEFAULT 0")) {
                // Existing announcements are NOT blasted to subscribers
                $conn->query("UPDATE announcements SET newsletter_sent = 1");
            }
        }
    } catch (Throwable $e) {
        error_log('newsletter schema: ' . $e->getMessage());
    }
}

function kt_newsletter_base_url(): string
{
    $env = getenv('SITE_URL');
    if ($env) return rtrim($env, '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}

function kt_newsletter_send(string $to, string $subject, string $body): bool
{
    if (!function_exists('kt_send_mail')) {
        error_log('newsletter: kt_send_mail() not loaded');
        return false;
    }
    // HTML version: same text, with every web address turned into a clickable link
    $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#2b2b2b">'
          . preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', nl2br(htmlspecialchars($body)))
          . '</div>';
    return kt_send_mail($to, $subject, $body, $html);
}

function kt_newsletter_footer(string $token): string
{
    return "\n\n--\nYou are receiving this because you subscribed to KULTOURA updates.\n"
         . 'Unsubscribe: ' . kt_newsletter_base_url() . '/index.php?unsubscribe=' . $token . "\n";
}

function kt_newsletter_item_text(array $a): string
{
    $date = date('F j, Y', strtotime($a['published_at'] ?? $a['created_at'] ?? 'now'));
    $body = mb_strimwidth(trim((string) $a['body']), 0, 300, '...');
    return $a['title'] . "\n" . $date . "\n" . $body . "\n";
}

function kt_newsletter_digest_text(mysqli $conn, string $token): string
{
    $txt = "Thanks for subscribing to KULTOURA!\nHere is what is happening in Malvar:\n\n";

    $news = $conn->query("SELECT title, body, published_at, created_at FROM announcements
                          WHERE status = 'live' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 5");
    if ($news && $news->num_rows) {
        $txt .= "LATEST ANNOUNCEMENTS\n--------------------\n";
        while ($a = $news->fetch_assoc()) $txt .= kt_newsletter_item_text($a) . "\n";
    }

    $ev = $conn->query("SELECT fiesta_name, celebration_date, location FROM fiestas
                        WHERE status = 'active' AND celebration_date >= CURDATE()
                        ORDER BY celebration_date ASC LIMIT 5");
    if ($ev && $ev->num_rows) {
        $txt .= "UPCOMING FIESTAS AND EVENTS\n---------------------------\n";
        while ($f = $ev->fetch_assoc()) {
            $txt .= '- ' . $f['fiesta_name'] . ' | ' . date('M j, Y', strtotime($f['celebration_date']))
                  . ' | ' . $f['location'] . "\n";
        }
        $txt .= "\n";
    }

    $txt .= "We will email you whenever a new announcement is published.\n";
    $txt .= 'Visit: ' . kt_newsletter_base_url() . '/index.php';
    return $txt . kt_newsletter_footer($token);
}

function kt_newsletter_subscribe(mysqli $conn, string $email): array
{
    kt_newsletter_ensure_schema($conn);
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    $stmt = $conn->prepare("SELECT id, status, token FROM newsletter_subscribers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['status'] === 'active') {
        return ['ok' => true, 'message' => 'You are already subscribed.'];
    }

    if ($row) {
        $token = $row['token'];
        $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'active' WHERE id = ?");
        $stmt->bind_param('i', $row['id']);
    } else {
        $token = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, token) VALUES (?, ?)");
        $stmt->bind_param('ss', $email, $token);
    }
    $stmt->execute();
    $stmt->close();

    $sent = kt_newsletter_send($email, 'Welcome to KULTOURA - upcoming announcements', kt_newsletter_digest_text($conn, $token));
    return $sent
        ? ['ok' => true, 'message' => 'Subscribed! Check your inbox for the latest announcements.']
        : ['ok' => true, 'message' => 'Subscribed, but we could not send the email right now. You will still get future announcements.'];
}

function kt_newsletter_unsubscribe(mysqli $conn, string $token): bool
{
    kt_newsletter_ensure_schema($conn);
    $token = preg_replace('/[^a-f0-9]/', '', $token);
    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    // NEW: start of added block
    try {
        $u = $conn->prepare("UPDATE users SET promotional_email = 0
                             WHERE email = (SELECT email FROM newsletter_subscribers WHERE token = ?)");
        $u->bind_param('s', $token);
        $u->execute();
        $u->close();
    } catch (Throwable $e) {
        error_log('unsubscribe user flag: ' . $e->getMessage());
    }
    // NEW: end of added block

    return $ok;
}

// Emails each newly published (or newly promoted scheduled) announcement once.
function kt_newsletter_dispatch_pending(mysqli $conn): void
{
    kt_newsletter_ensure_schema($conn);
    @set_time_limit(120);
    ignore_user_abort(true);
    try {
        $res = $conn->query("SELECT id, title, body, type, published_at, created_at FROM announcements
                             WHERE status = 'live' AND newsletter_sent = 0 ORDER BY id ASC LIMIT 3");
        if (!$res || !$res->num_rows) return;
        $items = $res->fetch_all(MYSQLI_ASSOC);

        $s = $conn->query("SELECT email, token FROM newsletter_subscribers WHERE status = 'active'");
        $subs = $s ? $s->fetch_all(MYSQLI_ASSOC) : [];

        foreach ($items as $a) {
            // Atomic claim: two simultaneous page loads never double-send
            $claim = $conn->prepare("UPDATE announcements SET newsletter_sent = 1 WHERE id = ? AND newsletter_sent = 0");
            $claim->bind_param('i', $a['id']);
            $claim->execute();
            $won = $claim->affected_rows === 1;
            $claim->close();
            if (!$won) continue;

            foreach ($subs as $sub) {
                $body = "New announcement from KULTOURA:\n\n" . kt_newsletter_item_text($a)
                      . "\nRead more: " . kt_newsletter_base_url() . '/index.php';
                kt_newsletter_send($sub['email'], 'KULTOURA: ' . $a['title'], $body . kt_newsletter_footer($sub['token']));
            }
        }
    } catch (Throwable $e) {
        error_log('newsletter dispatch: ' . $e->getMessage());
    }
}
<?php
/*
 |--------------------------------------------------------------------
 | LOGIN THROTTLING — brute-force protection
 |--------------------------------------------------------------------
 | Failed attempts are counted per client IP over a rolling 60-second
 | window. The 5th failure inside that window starts a fixed lockout
 | that lasts exactly LOGIN_THROTTLE_LOCKOUT_SECONDS from that moment;
 | the counter is reset when the lockout starts, so the user gets a
 | fresh 5 attempts once it ends. Both tables auto-create on first use.
 |
 | Keyed on IP alone (not username): locking by username would let an
 | attacker lock a real admin out just by failing their username from
 | anywhere.
 */

if (!defined('LOGIN_THROTTLE_MAX_ATTEMPTS')) {
    define('LOGIN_THROTTLE_MAX_ATTEMPTS', 5);
}
if (!defined('LOGIN_THROTTLE_WINDOW_SECONDS')) {
    define('LOGIN_THROTTLE_WINDOW_SECONDS', 60);
}
if (!defined('LOGIN_THROTTLE_LOCKOUT_SECONDS')) {
    define('LOGIN_THROTTLE_LOCKOUT_SECONDS', 60);
}

if (!function_exists('login_throttle_ensure_table')) {
    function login_throttle_ensure_table(mysqli $conn): void
    {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_ip_time (ip_address, attempted_at)
            )"
        );
        $conn->query(
            "CREATE TABLE IF NOT EXISTS login_lockouts (
                ip_address VARCHAR(45) PRIMARY KEY,
                locked_until DATETIME NOT NULL
            )"
        );
    }
}

if (!function_exists('login_client_ip')) {
    function login_client_ip(): string
    {
        // Railway (and most reverse proxies) put the real client IP first
        // in X-Forwarded-For; REMOTE_ADDR alone would just be the proxy.
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if ($ip !== '') {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// Returns a message if this IP is locked out right now, or null if it may proceed.
if (!function_exists('login_throttle_check')) {
    function login_throttle_check(mysqli $conn): ?string
    {
        login_throttle_ensure_table($conn);
        $ip = login_client_ip();

        $stmt = $conn->prepare(
            "SELECT GREATEST(1, CEIL(TIMESTAMPDIFF(MICROSECOND, NOW(), locked_until) / 1000000)) AS remaining
             FROM login_lockouts
             WHERE ip_address = ? AND locked_until > NOW()"
        );
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $seconds = (int) $row['remaining'];
            return "Too many failed login attempts. Please try again in {$seconds} second" . ($seconds === 1 ? '' : 's') . '.';
        }
        return null;
    }
}

// Records one failed attempt. Returns how many attempts are left in the
// current window (0 means this failure just started a lockout).
if (!function_exists('login_throttle_record_failure')) {
    function login_throttle_record_failure(mysqli $conn): int
    {
        login_throttle_ensure_table($conn);
        $ip = login_client_ip();

        $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempted_at) VALUES (?, NOW())");
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL " . (int) LOGIN_THROTTLE_WINDOW_SECONDS . " SECOND)"
        );
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $failures = (int) $stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();

        if ($failures >= LOGIN_THROTTLE_MAX_ATTEMPTS) {
            $stmt = $conn->prepare(
                "INSERT INTO login_lockouts (ip_address, locked_until) VALUES (?, NOW() + INTERVAL " . (int) LOGIN_THROTTLE_LOCKOUT_SECONDS . " SECOND)
                 ON DUPLICATE KEY UPDATE locked_until = VALUES(locked_until)"
            );
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->bind_param('s', $ip);
            $stmt->execute();
            $stmt->close();
            return 0;
        }

        return LOGIN_THROTTLE_MAX_ATTEMPTS - $failures;
    }
}

// The user-facing message after a failed attempt, including attempts left.
if (!function_exists('login_throttle_failure_message')) {
    function login_throttle_failure_message(string $base, int $attemptsLeft): string
    {
        if ($attemptsLeft <= 0) {
            return 'Too many failed login attempts. Please try again in ' . LOGIN_THROTTLE_LOCKOUT_SECONDS . ' seconds.';
        }
        return $base . ' You have ' . $attemptsLeft . ' attempt' . ($attemptsLeft === 1 ? '' : 's') . ' left before a '
            . LOGIN_THROTTLE_LOCKOUT_SECONDS . '-second lockout.';
    }
}

if (!function_exists('login_throttle_clear')) {
    function login_throttle_clear(mysqli $conn): void
    {
        login_throttle_ensure_table($conn);
        $ip = login_client_ip();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM login_lockouts WHERE ip_address = ?");
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();
    }
}

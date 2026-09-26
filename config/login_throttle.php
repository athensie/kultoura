<?php
/*
 |--------------------------------------------------------------------
 | LOGIN THROTTLING — brute-force protection
 |--------------------------------------------------------------------
 | Tracks failed login attempts per client IP in a small DB table
 | (auto-created on first use — no manual migration needed on any
 | environment, including a fresh Railway database). After
 | MAX_ATTEMPTS failures inside WINDOW_MINUTES, further attempts from
 | that IP are blocked until the window rolls off.
 |
 | Deliberately keyed on IP alone (not username): locking by username
 | would let an attacker lock a real admin out just by failing their
 | username repeatedly from anywhere.
 */

if (!defined('LOGIN_THROTTLE_MAX_ATTEMPTS')) {
    define('LOGIN_THROTTLE_MAX_ATTEMPTS', 5);
}
if (!defined('LOGIN_THROTTLE_WINDOW_MINUTES')) {
    define('LOGIN_THROTTLE_WINDOW_MINUTES', 15);
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

// Returns a user-facing message if this IP is currently blocked, or null if it may proceed.
if (!function_exists('login_throttle_check')) {
    function login_throttle_check(mysqli $conn): ?string
    {
        login_throttle_ensure_table($conn);
        $ip = login_client_ip();

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS attempts
             FROM login_attempts
             WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL " . LOGIN_THROTTLE_WINDOW_MINUTES . " MINUTE)"
        );
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ((int) ($row['attempts'] ?? 0) >= LOGIN_THROTTLE_MAX_ATTEMPTS) {
            return 'Too many failed login attempts. Please wait a few minutes and try again.';
        }
        return null;
    }
}

if (!function_exists('login_throttle_record_failure')) {
    function login_throttle_record_failure(mysqli $conn): void
    {
        login_throttle_ensure_table($conn);
        $ip = login_client_ip();
        $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, attempted_at) VALUES (?, NOW())");
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('login_throttle_clear')) {
    function login_throttle_clear(mysqli $conn): void
    {
        $ip = login_client_ip();
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->close();
    }
}

<?php
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
if (!function_exists('login_throttle_failure_message')) {
    function login_throttle_failure_message(string $base, int $attemptsLeft): string
    {
        if ($attemptsLeft <= 0) {
            return 'Too many failed login attempts. Please try again in ' . LOGIN_THROTTLE_LOCKOUT_SECONDS . ' seconds.';
        }
        $attemptsText = 'You have ' . $attemptsLeft . ' attempt' . ($attemptsLeft === 1 ? '' : 's') . ' left before a '
            . LOGIN_THROTTLE_LOCKOUT_SECONDS . '-second lockout.';
        return $base === '' ? $attemptsText : $base . ' ' . $attemptsText;
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

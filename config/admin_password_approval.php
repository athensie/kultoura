<?php
/*
 |--------------------------------------------------------------------
 | ADMIN PASSWORD CHANGE — requires email approval
 |--------------------------------------------------------------------
 | Submitting the "Change Password" form in Settings doesn't change the
 | password immediately. It stores the new password (already hashed)
 | as pending, emails the admin's own address on file two links —
 | Approve and Reject — and only applies the change once Approve is
 | clicked (see auth/confirm_admin_password.php). Expires after 30
 | minutes. Self-creating columns, same pattern as session_security.php.
 */

if (!function_exists('kt_pwreq_ensure_columns')) {
    function kt_pwreq_ensure_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $alters = [
            "ADD COLUMN pending_password_hash VARCHAR(255) NULL DEFAULT NULL",
            "ADD COLUMN pending_password_token VARCHAR(64) NULL DEFAULT NULL",
            "ADD COLUMN pending_password_expires DATETIME NULL DEFAULT NULL",
        ];
        foreach ($alters as $alter) {
            try {
                $conn->query("ALTER TABLE admins $alter");
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1060) { // 1060 = Duplicate column name — already added
                    throw $e;
                }
            }
        }
    }
}

// Stores the pending password change and returns the approval token.
if (!function_exists('kt_pwreq_create')) {
    function kt_pwreq_create(mysqli $conn, int $adminId, string $newHash): string
    {
        kt_pwreq_ensure_columns($conn);
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare(
            "UPDATE admins SET pending_password_hash = ?, pending_password_token = ?,
             pending_password_expires = NOW() + INTERVAL 30 MINUTE WHERE admin_id = ?"
        );
        $stmt->bind_param('ssi', $newHash, $token, $adminId);
        $stmt->execute();
        $stmt->close();
        return $token;
    }
}

// Looks up a still-valid pending request by its token.
if (!function_exists('kt_pwreq_find')) {
    function kt_pwreq_find(mysqli $conn, string $token): ?array
    {
        kt_pwreq_ensure_columns($conn);
        $stmt = $conn->prepare(
            "SELECT admin_id, email, pending_password_hash FROM admins
             WHERE pending_password_token = ? AND pending_password_expires > NOW() LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('kt_pwreq_clear')) {
    function kt_pwreq_clear(mysqli $conn, int $adminId): void
    {
        $stmt = $conn->prepare(
            "UPDATE admins SET pending_password_hash = NULL, pending_password_token = NULL,
             pending_password_expires = NULL WHERE admin_id = ?"
        );
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }
}

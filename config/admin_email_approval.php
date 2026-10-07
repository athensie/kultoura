<?php
/*
 |--------------------------------------------------------------------
 | ADMIN EMAIL CHANGE — requires approval from the CURRENT email
 |--------------------------------------------------------------------
 | Typing a new email in Settings doesn't change it right away — it
 | emails the admin's CURRENT address on file (not the new one) an
 | Approve/Reject link. Only approving it — proving the admin still
 | controls the old inbox — replaces the address. Expires after 30
 | minutes. Self-creating columns, same pattern as session_security.php.
 */

if (!function_exists('kt_emailreq_ensure_columns')) {
    function kt_emailreq_ensure_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $alters = [
            "ADD COLUMN pending_email_new VARCHAR(100) NULL DEFAULT NULL",
            "ADD COLUMN pending_email_token VARCHAR(64) NULL DEFAULT NULL",
            "ADD COLUMN pending_email_expires DATETIME NULL DEFAULT NULL",
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

// Starts an email-change request and returns the approval token.
if (!function_exists('kt_emailreq_create')) {
    function kt_emailreq_create(mysqli $conn, int $adminId, string $newEmail): string
    {
        kt_emailreq_ensure_columns($conn);
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare(
            "UPDATE admins SET pending_email_new = ?, pending_email_token = ?,
             pending_email_expires = NOW() + INTERVAL 30 MINUTE WHERE admin_id = ?"
        );
        $stmt->bind_param('ssi', $newEmail, $token, $adminId);
        $stmt->execute();
        $stmt->close();
        return $token;
    }
}

// Looks up a still-valid pending request by its token.
if (!function_exists('kt_emailreq_find')) {
    function kt_emailreq_find(mysqli $conn, string $token): ?array
    {
        kt_emailreq_ensure_columns($conn);
        $stmt = $conn->prepare(
            "SELECT admin_id, pending_email_new FROM admins
             WHERE pending_email_token = ? AND pending_email_expires > NOW() LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('kt_emailreq_clear')) {
    function kt_emailreq_clear(mysqli $conn, int $adminId): void
    {
        $stmt = $conn->prepare(
            "UPDATE admins SET pending_email_new = NULL, pending_email_token = NULL,
             pending_email_expires = NULL WHERE admin_id = ?"
        );
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $stmt->close();
    }
}

// "athenamacahia17@gmail.com" -> "ath************@gmail.com" — enough to
// recognize the account is right without showing the full address.
if (!function_exists('kt_mask_email')) {
    function kt_mask_email(string $email): string
    {
        if (!str_contains($email, '@')) {
            return str_repeat('*', max(3, strlen($email)));
        }
        [$local, $domain] = explode('@', $email, 2);
        $visible = min(3, strlen($local));
        $hidden  = max(3, strlen($local) - $visible);
        return substr($local, 0, $visible) . str_repeat('*', $hidden) . '@' . $domain;
    }
}

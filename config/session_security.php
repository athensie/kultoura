<?php
/*
 |--------------------------------------------------------------------
 | SINGLE-SESSION ENFORCEMENT
 |--------------------------------------------------------------------
 | Each account (site user or admin) has one valid session_token on
 | file. Logging in anywhere issues a fresh one, which makes every
 | previously-logged-in device's session stop matching on its very next
 | request — effectively one active session per account. Changing the
 | password does the same, so a session open elsewhere can't survive a
 | reset. dbmain.php checks this on every authenticated page load.
 */

if (!function_exists('kt_session_ensure_columns')) {
    function kt_session_ensure_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        foreach (['users' => 'id', 'admins' => 'admin_id'] as $table => $idCol) {
            try {
                $conn->query("ALTER TABLE `$table` ADD COLUMN session_token VARCHAR(64) NULL DEFAULT NULL");
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1060) { // 1060 = Duplicate column name — already added
                    throw $e;
                }
            }
        }
    }
}

// Issues a fresh session token for this account, invalidating whatever
// device is currently logged into it. Call on login, signup, and
// password change; store the result in $_SESSION['session_token'].
if (!function_exists('kt_session_issue')) {
    function kt_session_issue(mysqli $conn, string $table, string $idCol, int $id): string
    {
        kt_session_ensure_columns($conn);
        $token = bin2hex(random_bytes(32));
        $stmt = $conn->prepare("UPDATE `$table` SET session_token = ? WHERE `$idCol` = ?");
        $stmt->bind_param('si', $token, $id);
        $stmt->execute();
        $stmt->close();
        return $token;
    }
}

// False means this account logged in elsewhere, or changed its
// password, since this session's token was issued.
if (!function_exists('kt_session_is_current')) {
    function kt_session_is_current(mysqli $conn, string $table, string $idCol, int $id, ?string $token): bool
    {
        if (!$token) return false;
        kt_session_ensure_columns($conn);

        $stmt = $conn->prepare("SELECT session_token FROM `$table` WHERE `$idCol` = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row && $row['session_token'] !== null && hash_equals($row['session_token'], $token);
    }
}

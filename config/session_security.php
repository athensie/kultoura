<?php
if (!function_exists('kt_session_ensure_columns')) {
    function kt_session_ensure_columns(mysqli $conn): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $conn->query(
            'CREATE TABLE IF NOT EXISTS account_sessions (
                session_key CHAR(64) NOT NULL PRIMARY KEY,
                account_table VARCHAR(16) NOT NULL,
                account_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX account_lookup (account_table, account_id)
            ) ENGINE=InnoDB'
        );
        // Older deployments stored a single active-session token on each account.
        // Keep the column available during the one-time migration below.
        foreach (['users', 'admins'] as $accountTable) {
            $column = $conn->query("SHOW COLUMNS FROM `$accountTable` LIKE 'session_token'");
            if ($column && $column->num_rows === 0) {
                try {
                    $conn->query("ALTER TABLE `$accountTable` ADD COLUMN session_token VARCHAR(128) NULL");
                } catch (mysqli_sql_exception $e) {
                    // Another request may have added it at the same time.
                    if ((int) $e->getCode() !== 1060) throw $e;
                }
            }
            if ($column) $column->close();
        }
    }
}
if (!function_exists('kt_session_issue')) {
    function kt_session_issue(mysqli $conn, string $table, string $idCol, int $id): string
    {
        kt_session_ensure_columns($conn);
        $token = bin2hex(random_bytes(32));
        $sessionKey = hash('sha256', session_id());
        $stmt = $conn->prepare('INSERT INTO account_sessions (session_key, account_table, account_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE account_table = VALUES(account_table), account_id = VALUES(account_id)');
        $stmt->bind_param('ssi', $sessionKey, $table, $id);
        $stmt->execute();
        $stmt->close();
        // A legacy session can be migrated once; keep its token until it does.
        return $token;
    }
}
if (!function_exists('kt_session_is_current')) {
    function kt_session_is_current(mysqli $conn, string $table, string $idCol, int $id, ?string $token): bool
    {
        if (!$token) return false;
        kt_session_ensure_columns($conn);
        $sessionKey = hash('sha256', session_id());
        $stmt = $conn->prepare('SELECT 1 FROM account_sessions WHERE session_key = ? AND account_table = ? AND account_id = ? LIMIT 1');
        $stmt->bind_param('ssi', $sessionKey, $table, $id);
        $stmt->execute();
        $valid = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        if (!$valid && in_array($table, ['users', 'admins'], true)
            && (($table === 'users' && $idCol === 'id') || ($table === 'admins' && $idCol === 'admin_id'))) {
            // Migrate a still-valid session from the former single-device model.
            // Compare-and-clear makes migration one-time and safe across requests.
            $conn->begin_transaction();
            try {
                $legacy = $conn->prepare("UPDATE `$table` SET session_token = NULL WHERE `$idCol` = ? AND session_token = ?");
                $legacy->bind_param('is', $id, $token);
                $legacy->execute();
                $claimed = $legacy->affected_rows === 1;
                $legacy->close();
                if ($claimed) {
                    $insert = $conn->prepare('INSERT INTO account_sessions (session_key, account_table, account_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE account_table = VALUES(account_table), account_id = VALUES(account_id)');
                    $insert->bind_param('ssi', $sessionKey, $table, $id);
                    $insert->execute();
                    $insert->close();
                    $conn->commit();
                    return true;
                }
                $conn->rollback();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        }
        return $valid;
    }
}
if (!function_exists('kt_session_revoke_others')) {
    function kt_session_revoke_others(mysqli $conn, string $table, int $id): void
    {
        kt_session_ensure_columns($conn);
        $currentKey = hash('sha256', session_id());
        $stmt = $conn->prepare('DELETE FROM account_sessions WHERE account_table = ? AND account_id = ? AND session_key <> ?');
        $stmt->bind_param('sis', $table, $id, $currentKey);
        $stmt->execute();
        $stmt->close();
        if (in_array($table, ['users', 'admins'], true)) {
            $clear = $conn->prepare("UPDATE `$table` SET session_token = NULL WHERE " . ($table === 'admins' ? 'admin_id' : 'id') . ' = ?');
            $clear->bind_param('i', $id);
            $clear->execute();
            $clear->close();
        }
    }
}
if (!function_exists('kt_session_revoke_all')) {
    function kt_session_revoke_all(mysqli $conn, string $table, int $id): void
    {
        kt_session_ensure_columns($conn);
        $stmt = $conn->prepare('DELETE FROM account_sessions WHERE account_table = ? AND account_id = ?');
        $stmt->bind_param('si', $table, $id);
        $stmt->execute();
        $stmt->close();
        if (in_array($table, ['users', 'admins'], true)) {
            $clear = $conn->prepare("UPDATE `$table` SET session_token = NULL WHERE " . ($table === 'admins' ? 'admin_id' : 'id') . ' = ?');
            $clear->bind_param('i', $id);
            $clear->execute();
            $clear->close();
        }
    }
}

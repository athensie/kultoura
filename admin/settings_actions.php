<?php
/*
 |--------------------------------------------------------------------
 | Settings — account update, password change, danger-zone actions
 |--------------------------------------------------------------------
 | Two response styles depending on the caller:
 |   - update_account / reset_analytics / clear_sessions: plain form
 |     POSTs from adminsettings.php — classic POST/Redirect/GET with a
 |     session-flashed message shown as a toast on reload.
 |   - toggle_setting / change_password: fetch() calls from
 |     adminsettings.js — respond directly (JSON for change_password,
 |     since the modal needs a success/failure result without a full
 |     page reload; a bare 200 for toggle_setting, which only checks
 |     response.ok).
 */
require_once __DIR__ . '/../config/session_boot.php';

define('BASE_URL', '/kultoura');

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'super admin'], true)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/admin/adminsettings.php");
    exit;
}

csrf_verify();

$action = $_POST['action'] ?? '';
$adminId = (int) $_SESSION['user_id'];

function settings_redirect_with_message(string $message): void
{
    $_SESSION['flash_message'] = $message;
    header("Location: " . BASE_URL . "/admin/adminsettings.php");
    exit;
}

function settings_json(bool $success, string $message): void
{
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/* ── Update display name / email ── */
if ($action === 'update_account') {
    $displayName = trim($_POST['display_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');

    if ($displayName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        settings_redirect_with_message('Please provide a valid name and email.');
    }

    // The form only collects one "Display Name" field, but admins are
    // stored as first_name/last_name — split on the first space so a
    // single name still saves sensibly into first_name alone.
    $parts     = explode(' ', $displayName, 2);
    $firstName = $parts[0];
    $lastName  = $parts[1] ?? '';

    $stmt = $conn->prepare("UPDATE admins SET first_name = ?, last_name = ?, email = ? WHERE admin_id = ?");
    $stmt->bind_param('sssi', $firstName, $lastName, $email, $adminId);
    $stmt->execute();
    $stmt->close();

    settings_redirect_with_message('Account details updated.');
}

/* ── Change password (fetch-based, JSON response for the modal) ── */
if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        settings_json(false, 'New password and confirmation do not match.');
    }
    if (strlen($new) < 8) {
        settings_json(false, 'New password must be at least 8 characters.');
    }

    $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ? LIMIT 1");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password'])) {
        settings_json(false, 'Current password is incorrect.');
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
    $stmt->bind_param('si', $newHash, $adminId);
    $stmt->execute();
    $stmt->close();

    settings_json(true, 'Password updated.');
}

/* ── Setting toggles — no settings table yet (see adminsettings.php's
   comment on the $settings array), so this just acknowledges the
   request instead of 404ing; nothing to persist until that table
   exists. ── */
if ($action === 'toggle_setting') {
    http_response_code(200);
    exit;
}

/* ── Reset all analytics data ── */
if ($action === 'reset_analytics') {
    $conn->query("TRUNCATE TABLE page_views");
    $conn->query("TRUNCATE TABLE item_views");
    settings_redirect_with_message('Analytics data has been reset.');
}

/* ── Clear all active sessions (best-effort: this admin's own session
   is intentionally left alone so the click doesn't log the caller
   out too) ── */
if ($action === 'clear_sessions') {
    $savePath = session_save_path() ?: sys_get_temp_dir();
    $ownFile  = $savePath . '/sess_' . session_id();
    $cleared  = 0;

    foreach (glob($savePath . '/sess_*') ?: [] as $file) {
        if ($file !== $ownFile && is_file($file)) {
            @unlink($file);
            $cleared++;
        }
    }

    settings_redirect_with_message($cleared > 0 ? "Cleared {$cleared} active session(s)." : 'No other active sessions to clear.');
}

/* ── Unknown action ── */
settings_redirect_with_message('Unknown action.');

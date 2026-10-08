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
require_once __DIR__ . '/../config/password_policy.php';
require_once __DIR__ . '/../config/site_settings.php';
require_once __DIR__ . '/../config/mailer.php';
require_once __DIR__ . '/../config/admin_password_approval.php';
require_once __DIR__ . '/../config/admin_email_approval.php';
require_once __DIR__ . '/../config/admin_requests.php';

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

/* ── Update display name (immediate) / email (requires approval) ── */
if ($action === 'update_account') {
    $displayName = trim($_POST['display_name'] ?? '');
    $newEmail    = trim($_POST['email'] ?? '');

    if ($displayName === '') {
        settings_redirect_with_message('Please provide a display name.');
    }

    // The form only collects one "Display Name" field, but admins are
    // stored as first_name/last_name — split on the first space so a
    // single name still saves sensibly into first_name alone.
    $parts     = explode(' ', $displayName, 2);
    $firstName = $parts[0];
    $lastName  = $parts[1] ?? '';

    $stmt = $conn->prepare("UPDATE admins SET first_name = ?, last_name = ? WHERE admin_id = ?");
    $stmt->bind_param('ssi', $firstName, $lastName, $adminId);
    $stmt->execute();
    $stmt->close();

    // The email field is left blank by default (see adminsettings.php —
    // it shows the current address masked as a placeholder, not a real
    // value), so a blank submit here just means "didn't ask to change it".
    if ($newEmail === '') {
        settings_redirect_with_message('Account details updated.');
    }

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        settings_redirect_with_message('Display name saved, but that email address looks invalid — your email was not changed.');
    }

    $stmt = $conn->prepare("SELECT email FROM admins WHERE admin_id = ? LIMIT 1");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $currentEmail = $stmt->get_result()->fetch_assoc()['email'] ?? '';
    $stmt->close();

    if (strcasecmp($newEmail, $currentEmail) === 0) {
        settings_redirect_with_message('Account details updated.');
    }

    // Approval goes to the CURRENT address, not the new one — proving the
    // admin still controls the old inbox before it's replaced.
    $token = kt_emailreq_create($conn, $adminId, $newEmail);
    $approveUrl = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . BASE_URL
        . '/auth/confirm_admin_email.php?token=' . $token . '&action=approve';
    $rejectUrl = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . BASE_URL
        . '/auth/confirm_admin_email.php?token=' . $token . '&action=reject';

    $sent = kt_send_mail(
        $currentEmail,
        'Approve your KULTOURA admin email change',
        "A request was made to change this KULTOURA admin account's email to: $newEmail\r\n\r\n"
        . "If this was you, approve it here:\r\n$approveUrl\r\n\r\n"
        . "If this wasn't you, reject it here instead — your email will stay the same:\r\n$rejectUrl\r\n\r\n"
        . "This link expires in 30 minutes."
    );

    if (!$sent) {
        kt_emailreq_clear($conn, $adminId);
        settings_redirect_with_message("Display name saved, but we couldn't send the approval email for the email change. Please try again.");
    }

    settings_redirect_with_message('Display name saved. Check ' . kt_mask_email($currentEmail) . ' to approve changing your email.');
}

/* ── Request a password change (fetch-based, JSON response for the
   modal). Only verifies identity and sends the approval email — the
   new password itself is chosen later, after approval (see
   auth/confirm_admin_password.php + auth.php's set_admin_password). ── */
if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';

    $stmt = $conn->prepare("SELECT password, email FROM admins WHERE admin_id = ? LIMIT 1");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password'])) {
        settings_json(false, 'Current password is incorrect.');
    }
    if (empty($row['email'])) {
        settings_json(false, 'This admin account has no email on file to send the approval to.');
    }

    $token = kt_pwreq_create($conn, $adminId);

    $approveUrl = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . BASE_URL
        . '/auth/confirm_admin_password.php?token=' . $token . '&action=approve';
    $rejectUrl = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . BASE_URL
        . '/auth/confirm_admin_password.php?token=' . $token . '&action=reject';

    $sent = kt_send_mail(
        $row['email'],
        'Approve your KULTOURA admin password change',
        "A password change was requested for your KULTOURA admin account.\r\n\r\n"
        . "If this was you, approve it here and you'll be asked to choose the new password:\r\n$approveUrl\r\n\r\n"
        . "If this wasn't you, reject it here instead — nothing will change:\r\n$rejectUrl\r\n\r\n"
        . "This link expires in 30 minutes."
    );

    if (!$sent) {
        kt_pwreq_clear($conn, $adminId);
        settings_json(false, "Couldn't send the approval email right now. Please try again in a few minutes.");
    }

    settings_json(true, "We've emailed an approval link to confirm this. Approve it there to choose your new password.");
}

/* ── Setting toggles — no settings table yet (see adminsettings.php's
   comment on the $settings array), so this just acknowledges the
   request instead of 404ing; nothing to persist until that table
   exists. ── */
if ($action === 'toggle_setting') {
    $validKeys = [
        'maintenance_mode',
        'email_alerts', 'new_listing_alert', 'flagged_content_alert', 'weekly_summary',
    ];
    $key = $_POST['key'] ?? '';
    if (!in_array($key, $validKeys, true)) {
        http_response_code(400);
        exit;
    }
    site_settings_set($conn, $key, ($_POST['value'] ?? '') === '1');
    http_response_code(200);
    exit;
}

/* ── Danger Zone — Super Admin only; the page already hides this
   section from a plain Admin, but the actions are gated here too
   since a POST can always be sent directly. ── */
if (in_array($action, ['reset_analytics', 'clear_sessions'], true) && !kt_is_super_admin()) {
    settings_redirect_with_message('Only a Super Admin can do that.');
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

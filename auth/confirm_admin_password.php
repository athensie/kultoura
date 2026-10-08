<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/admin_password_approval.php';
require_once __DIR__ . '/../config/password_policy.php';

define('BASE_URL', '/kultoura');

$token  = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

// A validation error from auth.php's set_admin_password action (e.g.
// passwords didn't match) flashes back here so the form can be re-shown
// with the message, instead of just failing silently.
$formError = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

$title     = 'Link Invalid or Expired';
$message   = "This password-change link isn't valid anymore — it may have expired (links last 30 minutes) or already been used. If you still want to change your password, start again from Settings.";
$isError   = true;
$showForm  = false;

$pending = $token !== '' ? kt_pwreq_find($conn, $token) : null;

if ($pending && $action === 'approve') {
    $showForm = true;
} elseif ($pending && $action === 'reject') {
    kt_pwreq_clear($conn, (int) $pending['admin_id']);

    $title   = 'Password Change Rejected';
    $message = "This password change request has been cancelled. The account's password was not changed. If you didn't request this, consider changing your password from a device you trust.";
    $isError = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $showForm ? 'Set New Password' : htmlspecialchars($title); ?> – KULTOURA</title>

    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="auth-page">
    <main class="auth-content">
        <div class="auth-card">

            <div class="auth-brand">
                <img src="../assets/images/kultoura.png" alt="KulToura">
            </div>

            <?php if ($showForm): ?>

                <h2 class="auth-title">Set a New Password</h2>
                <p class="auth-sub">Approved — choose the new password for this admin account.</p>

                <?php if ($formError): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($formError); ?></div>
                <?php endif; ?>

                <form action="auth.php" method="POST" class="auth-form">
                    <input type="hidden" name="action" value="set_admin_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="form-group">
                        <label for="password">New Password</label>
                        <div class="pw-field">
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your new password"
                                minlength="8"
                                pattern="^(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$"
                                title="At least 8 characters, including one uppercase letter and one special character."
                                required>
                            <span class="toggle-pw" onclick="togglePw('password', this)" aria-label="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                        </div>
                        <p class="field-hint">At least 8 characters, with 1 uppercase letter and 1 special character</p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="pw-field">
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm your new password"
                                minlength="8"
                                required>
                            <span class="toggle-pw" onclick="togglePw('confirm_password', this)" aria-label="Show password">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="auth-btn">SET NEW PASSWORD</button>
                </form>

            <?php else: ?>

                <h2 class="auth-title"><?php echo htmlspecialchars($title); ?></h2>
                <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>" style="margin-top:16px;">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <p class="auth-switch" style="margin-top:18px;">
                    <a href="login.php">Go to Sign In</a>
                </p>

            <?php endif; ?>

        </div>
    </main>
</div>

<script src="../assets/js/index.js"></script>

</body>
</html>

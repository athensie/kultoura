<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/admin_password_approval.php';

define('BASE_URL', '/kultoura');

$token  = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

$title   = 'Link Invalid or Expired';
$message = "This password-change link isn't valid anymore — it may have expired (links last 30 minutes) or already been used. If you still want to change your password, start again from Settings.";
$isError = true;

$pending = $token !== '' ? kt_pwreq_find($conn, $token) : null;

if ($pending && $action === 'approve') {
    $adminId = (int) $pending['admin_id'];
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
    $stmt->bind_param('si', $pending['pending_password_hash'], $adminId);
    $stmt->execute();
    $stmt->close();

    kt_pwreq_clear($conn, $adminId);

    // Approving is a fresh credential for the account — every device
    // currently logged in (including whichever one requested this) needs
    // to sign in again with the new password.
    kt_session_issue($conn, 'admins', 'admin_id', $adminId);

    $title   = 'Password Changed';
    $message = 'This admin account\'s password has been updated. Every device signed into it, including this one, has been signed out — please sign in again with the new password.';
    $isError = false;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> – KULTOURA</title>

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

            <h2 class="auth-title"><?php echo htmlspecialchars($title); ?></h2>

            <div class="alert <?php echo $isError ? 'alert-error' : 'alert-success'; ?>" style="margin-top:16px;">
                <?php echo htmlspecialchars($message); ?>
            </div>

            <p class="auth-switch" style="margin-top:18px;">
                <a href="login.php">Go to Sign In</a>
            </p>

        </div>
    </main>
</div>

</body>
</html>

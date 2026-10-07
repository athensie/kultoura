<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/admin_email_approval.php';

define('BASE_URL', '/kultoura');

$token  = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

$title   = 'Link Invalid or Expired';
$message = "This email-change link isn't valid anymore — it may have expired (links last 30 minutes) or already been used. If you still want to change your email, start again from Settings.";
$isError = true;

$pending = $token !== '' ? kt_emailreq_find($conn, $token) : null;

if ($pending && $action === 'approve') {
    $adminId  = (int) $pending['admin_id'];
    $newEmail = $pending['pending_email_new'];

    $stmt = $conn->prepare("UPDATE admins SET email = ? WHERE admin_id = ?");
    $stmt->bind_param('si', $newEmail, $adminId);
    $stmt->execute();
    $stmt->close();

    kt_emailreq_clear($conn, $adminId);

    $title   = 'Email Changed';
    $message = "This admin account's email has been changed to $newEmail.";
    $isError = false;
} elseif ($pending && $action === 'reject') {
    kt_emailreq_clear($conn, (int) $pending['admin_id']);

    $title   = 'Email Change Rejected';
    $message = "This email change request has been cancelled. The account's email was not changed. If you didn't request this, consider changing your password too, from a device you trust.";
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

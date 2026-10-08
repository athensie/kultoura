<?php
require_once __DIR__ . '/../config/session_boot.php';

// Same base path used in admin/admindashboard.php — keep these in sync.
define('BASE_URL', '/kultoura');

if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role'] ?? '');
    if (in_array($role, ['admin', 'super admin'], true)) {
        header("Location: " . BASE_URL . "/admin/admindashboard.php");
    } else {
        header("Location: " . BASE_URL . "/index.php");
    }
    exit;
}

// Only reachable right after a reset was requested (see auth.php).
$pending = $_SESSION['reset_pending'] ?? null;
if (!$pending || time() > $pending['expires']) {
    unset($_SESSION['reset_pending']);
    $_SESSION['error'] = 'Please enter your username and email to get a confirmation code.';
    header("Location: " . BASE_URL . "/auth/forgot-password.php");
    exit;
}

$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Shows e.g. "ath****@gmail.com" so the page doesn't reveal the full address.
[$localPart, $domain] = explode('@', $pending['email'], 2);
$maskedEmail = substr($localPart, 0, 3) . str_repeat('*', max(3, strlen($localPart) - 3)) . '@' . $domain;
$secondsUntilResend = max(0, 60 - (time() - $pending['sent_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Password Change – KULTOURA</title>

    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="auth-page">

    <header class="navbar">
        <nav class="nav-links">
            <a href="../index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
            <a href="../pages/tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR</span></a>
            <a href="../pages/tourism/restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
        </nav>

        <a href="login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg><span>SIGN IN</span></a>

        <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <main class="auth-content">
        <div class="auth-card">

            <div class="auth-brand">
                <img src="../assets/images/kultoura.png" alt="KulToura">
            </div>

            <h2 class="auth-title">Enter Your Code</h2>
            <p class="auth-sub">We sent a 6-digit confirmation code to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong> to confirm you're changing this account's password.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form action="auth.php" method="POST" class="auth-form">
                <input type="hidden" name="action" value="verify_code">

                <div class="form-group">
                    <label for="code">Confirmation Code</label>
                    <input
                        type="text"
                        id="code"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="\d{6}"
                        maxlength="6"
                        placeholder="6-digit code"
                        required>
                    <p class="field-hint">The code expires 10 minutes after it was sent.</p>
                </div>

                <button type="submit" class="auth-btn">
                    CONFIRM CODE
                </button>
            </form>

            <form action="auth.php" method="POST" class="auth-form" style="margin-top:14px;">
                <input type="hidden" name="action" value="resend_code">
                <button type="submit" class="auth-btn" <?php echo $secondsUntilResend > 0 ? 'disabled' : ''; ?>>
                    <?php echo $secondsUntilResend > 0 ? "RESEND CODE IN {$secondsUntilResend}S" : 'RESEND CODE'; ?>
                </button>
            </form>

            <p class="auth-switch"><a href="forgot-password.php">Use a different username or email</a></p>

        </div>
    </main>

</div>

<script src="../assets/js/navbar.js"></script>

</body>
</html>

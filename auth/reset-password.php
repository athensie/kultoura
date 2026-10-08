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

// Only reachable right after a successful verify_reset (see auth.php) —
// anyone landing here without that fresh, unexpired flag gets bounced
// back to re-verify their identity first.
$verifiedExpires = $_SESSION['reset_verified_expires'] ?? 0;
if (empty($_SESSION['reset_verified_id']) || time() > $verifiedExpires) {
    unset($_SESSION['reset_verified_id'], $_SESSION['reset_verified_table'], $_SESSION['reset_verified_expires']);
    $_SESSION['error'] = 'Please verify your identity first.';
    header("Location: " . BASE_URL . "/auth/forgot-password.php");
    exit;
}

$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/kultoura/assets/images/K.png?v=20261008">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – KULTOURA</title>

    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="auth-page">

    <header class="navbar">
        <nav class="nav-links">
            <a href="../index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>HOME</span></a>
            <div class="dropdown">
                <a href="../pages/tourism.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18L9 7l4 6"/><path d="M11 18l6-10 4 10"/></svg></span><span>EXPLORE MALVAR ▾</span></a>
                <div class="mega-menu">
                    <div class="mega-column">
                        <h4>Local Products</h4>
                        <a href="../pages/tourism/products.php">Products</a>
                    </div>
                    <div class="mega-column">
                        <h4>Local Destinations</h4>
                        <a href="../pages/tourism/nature.php">Nature</a>
                        <a href="../pages/tourism/industry.php">Industry Zone</a>
                        <a href="../pages/tourism/resort.php">Resort</a>
                        <a href="../pages/tourism/churches.php">Churches</a>
                    </div>
                    <div class="mega-column">
                        <h4>Culture &amp; Services</h4>
                        <a href="../pages/tourism/fiestas.php">Fiestas</a>
                        <a href="../pages/tourism/people.php">People of Malvar</a>
                        <a href="../pages/tourism/services.php">Other Services</a>
                    </div>
                </div>
            </div>
            <a href="../pages/tourism/restaurants.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v6a2 2 0 0 0 2 2v10"/><path d="M5 3v6M9 3v6"/><path d="M17 3c-1.5 0-3 1.5-3 4v4c0 1 1 2 2 2v8"/></svg></span><span>RESTAURANTS</span></a>
            <a href="../pages/tourism/accommodation.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 4v16"/><path d="M22 12v8"/><path d="M2 12h20"/><path d="M2 8h6a2 2 0 0 1 2 2v2"/><path d="M22 8h-6a2 2 0 0 0-2 2v2"/></svg></span><span>ACCOMMODATION</span></a>
            <a href="../pages/tourism/banks.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M4 10v11"/><path d="M20 10v11"/><path d="M2 10h20L12 4z"/><path d="M8 14v4M12 14v4M16 14v4"/></svg></span><span>BANKS</span></a>
            <div class="dropdown">
                <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none"/></svg></span><span>MORE ▾</span></a>
                <div class="mega-menu mega-menu-simple">
                    <div class="mega-column">
                        <a href="../pages/foryou.php">For You</a>
                        <a href="../pages/traveldiary.php">Travel Diary</a>
                        <a href="../pages/favorites.php">Favorites</a>
                        <a href="../pages/mostpopular.php">Most Popular</a>
                        <a href="../pages/about.php">About</a>
                    </div>
                </div>
            </div>
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

            <h2 class="auth-title">Set a New Password</h2>
            <p class="auth-sub">Identity verified — choose a new password for your account.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST" class="auth-form">

                <input type="hidden" name="action" value="do_reset">

                <!-- New Password -->
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

                <!-- Confirm New Password -->
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

                <button type="submit" class="auth-btn">
                    RESET PASSWORD
                </button>

            </form>

        </div>
    </main>

</div>

<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/index.js"></script>

</body>
</html>

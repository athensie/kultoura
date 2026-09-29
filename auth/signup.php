<?php
require_once __DIR__ . '/../config/session_boot.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up – KULTOURA</title>

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

        <a href="login.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN IN</span></a>

        <button type="button" class="navbar-hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </header>

    <main class="auth-content">
        <div class="auth-card">

            <div class="auth-brand">
                <img src="../assets/images/kultoura.png" alt="KulToura">
            </div>

            <h2 class="auth-title">Create Your Account</h2>
            <p class="auth-sub">Join Kultoura and discover Malvar</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="auth.php" method="POST" class="auth-form">

                <input type="hidden" name="action" value="signup">

                <!-- Full Name -->
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input
                        type="text"
                        id="fullname"
                        name="fullname"
                        placeholder="Juan dela Cruz"
                        pattern="^[A-Za-z\s.'-]+$"
                        title="Full name should only contain letters."
                        required>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Choose a username"
                        minlength="3"
                        maxlength="20"
                        pattern="^[A-Za-z0-9_]+$"
                        title="Username can only contain letters, numbers, and underscores."
                        required>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@email.com"
                        required>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="pw-field">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Minimum 8 characters"
                            minlength="8"
                            required>

                        <span class="toggle-pw" onclick="togglePw('password', this)" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                    </div>
                    <p class="field-hint">Must be at least 8 characters</p>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="pw-field">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="••••••••"
                            minlength="8"
                            required>

                        <span class="toggle-pw" onclick="togglePw('confirm_password', this)" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        </span>
                    </div>
                </div>

                <!-- Terms & Conditions (required) -->
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="agree_terms" value="1" required>
                        I agree to the <button type="button" class="tc-link" onclick="openAuthModal('terms-modal')">Terms of Service</button> and <button type="button" class="tc-link" onclick="openAuthModal('privacy-modal')">Privacy Policy</button>.
                    </label>
                </div>

                <!-- Promotional Email -->
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="promotional_email" value="1">
                        I agree to receive promotional emails, travel updates, and special offers from KULTOURA.
                    </label>
                </div>

                <button type="submit" class="auth-btn">
                    CREATE ACCOUNT
                </button>

            </form>

            <p class="auth-switch">
                Already have an account?
                <a href="login.php">Sign In</a>
            </p>

        </div>
    </main>

    <!-- Terms of Service Modal -->
    <div class="tc-modal-overlay" id="terms-modal" onclick="if (event.target === this) closeAuthModal('terms-modal')">
        <div class="tc-modal-card">
            <button type="button" class="tc-modal-close" onclick="closeAuthModal('terms-modal')" aria-label="Close">&times;</button>
            <h3 class="tc-modal-title">Terms of Service</h3>
            <p class="tc-modal-sub">Please read these terms before creating a KulToura account.</p>
            <div class="tc-modal-body">
                <h4>1. Acceptance of Terms</h4>
                <p>By creating an account on KulToura, you agree to use this platform responsibly to explore, save, and share information about Malvar's tourism destinations, products, and services.</p>

                <h4>2. Your Account</h4>
                <p>You are responsible for keeping your username and password confidential and for all activity that happens under your account. Please notify us if you suspect any unauthorized use.</p>

                <h4>3. Acceptable Use</h4>
                <p>You agree not to misuse the platform, including posting false information, attempting to disrupt the service, or accessing other users' accounts without permission.</p>

                <h4>4. Content</h4>
                <p>Features such as Favorites and Travel Diary let you save and organize content within KulToura. This content remains tied to your account and may be removed if it violates these terms.</p>

                <h4>5. Changes</h4>
                <p>KulToura may update these terms from time to time. Continued use of the platform after changes means you accept the updated terms.</p>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="tc-modal-overlay" id="privacy-modal" onclick="if (event.target === this) closeAuthModal('privacy-modal')">
        <div class="tc-modal-card">
            <button type="button" class="tc-modal-close" onclick="closeAuthModal('privacy-modal')" aria-label="Close">&times;</button>
            <h3 class="tc-modal-title">Privacy Policy</h3>
            <p class="tc-modal-sub">How KulToura collects, uses, and protects your information.</p>
            <div class="tc-modal-body">
                <h4>1. Information We Collect</h4>
                <p>When you sign up, we collect your full name, username, email address, and password (stored securely as a one-way hash, never in plain text).</p>

                <h4>2. How We Use Your Information</h4>
                <p>Your account information is used to let you sign in, personalize your experience (such as Favorites, For You, and Travel Diary), and, only if you opt in, send promotional emails about Malvar tourism.</p>

                <h4>3. Data Sharing</h4>
                <p>We do not sell your personal information to third parties. Your data is used only within KulToura to operate and improve the platform.</p>

                <h4>4. Data Security</h4>
                <p>We apply reasonable safeguards to protect your account, including password hashing and login attempt monitoring to prevent unauthorized access.</p>

                <h4>5. Your Choices</h4>
                <p>You can update your account details at any time, and you may unsubscribe from promotional emails whenever you like.</p>
            </div>
        </div>
    </div>

</div>

<script src="../assets/js/navbar.js"></script>
<script src="../assets/js/index.js"></script>

</body>
</html>
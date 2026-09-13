<?php
session_start();

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

$error   = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

unset($_SESSION['error'], $_SESSION['success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – KULTOURA</title>

    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>

<div class="hero auth-page">

    <div class="bg-curves">
        <svg viewBox="0 0 1324 700" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M-50,150 C150,150 150,-50 350,-50"
                  stroke="rgba(255,255,255,0.15)" stroke-width="2" fill="none"/>
            <path d="M-50,50 C150,50 150,-150 350,-150"
                  stroke="rgba(255,255,255,0.10)" stroke-width="2" fill="none"/>
            <path d="M974,750 C1174,750 1174,550 1374,550"
                  stroke="rgba(255,255,255,0.12)" stroke-width="2" fill="none"/>
        </svg>
    </div>

    <header class="navbar">
        <nav class="nav-links">
            <a href="../index.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7"/><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9"/></svg></span><span>EXPLORE</span></a>
            <a href="../pages/foryou.php" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-2 6-6 2 2-6z"/></svg></span><span>FOR YOU</span></a>
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M16 4v3h3"/><path d="M8 10h8M8 14h8M8 18h5"/></svg></span><span>TRAVEL DIARY</span></a>
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20s-7-4.5-9.5-9C.5 7 2 3.5 5.5 3.5c2 0 3.5 1 4.5 2.5 1-1.5 2.5-2.5 4.5-2.5C18 3.5 19.5 7 19.5 11 17 15.5 12 20 12 20z"/></svg></span><span>FAVORITE</span></a>
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c4 0 6-3 6-6.5 0-2.5-1.5-4-2.5-5.5.5 2-1 3-2 2 0-2.5-1.5-4-3-6-.5 3-3 4.5-3 8 0 1-1 1.5-2 1-.5 3 2 7 6.5 7z"/></svg></span><span>MOST POPULAR</span></a>
            <a href="#" class="nav-item"><span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11.5v5"/><circle cx="12" cy="7.8" r="0.9" fill="currentColor" stroke="none"/></svg></span><span>ABOUT</span></a>
        </nav>

        <a href="signup.php" class="sign-in-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 21h4a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-4"/><path d="M8 7l-5 5 5 5"/><path d="M3 12h12"/></svg><span>SIGN UP</span></a>
    </header>

    <main class="auth-content">
        <div class="auth-card">

            <div class="auth-brand">
                <span class="brand-light">KUL</span><span class="brand-accent">TOURA</span>
            </div>

            <h2 class="auth-title">Welcome Back</h2>
            <p class="auth-sub">Sign in to continue exploring Malvar</p>

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

                <input type="hidden" name="action" value="login">

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Enter your username"
                        minlength="3"
                        maxlength="20"
                        pattern="^[A-Za-z0-9_]+$"
                        title="Username can only contain letters, numbers, and underscores."
                        required>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        minlength="6"
                        required>

                    <span class="toggle-pw" onclick="togglePw('password', this)">
                        Show
                    </span>
                </div>

                <button type="submit" class="auth-btn">
                    SIGN IN
                </button>

            </form>

            <p class="auth-switch">
                Don't have an account?
                <a href="signup.php">Sign Up</a>
            </p>

        </div>
    </main>

</div>

<script src="../assets/js/index.js"></script>

</body>
</html>
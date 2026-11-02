<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/login_throttle.php';
require_once __DIR__ . '/../config/password_policy.php';
require_once __DIR__ . '/../config/google_password_setup.php';
define('BASE_URL', '/kultoura');

$pending = $_SESSION['google_password_setup'] ?? null;
if (!$pending || !is_array($pending) || time() > (int) ($pending['expires'] ?? 0)) {
    unset($_SESSION['google_password_setup'], $_SESSION['google_password_csrf']);
    $_SESSION['error'] = 'Your Google account setup expired. Please continue with Google again.';
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $csrf = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['google_password_csrf'] ?? ''), $csrf)) {
        $error = 'This form expired. Please continue with Google again.';
    } elseif (!isset($_POST['agree_terms'], $_POST['agree_privacy'])) {
        $error = 'Please agree to the Terms of Service and Privacy Policy to continue.';
    } elseif (!kt_password_meets_policy($password)) {
        $error = 'Use at least 8 characters, including an uppercase letter and a special character.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        try {
            kt_google_password_setup_ensure_schema($conn);
            $email = (string) $pending['email'];
            $promo = isset($_POST['promotional_email']) ? 1 : 0;
            $find = $conn->prepare('SELECT id, username, fullname, status FROM users WHERE LOWER(email) = ? LIMIT 1');
            $find->bind_param('s', $email);
            $find->execute();
            $user = $find->get_result()->fetch_assoc();
            $find->close();
            if ($user && ($user['status'] ?? 'Active') === 'Inactive') {
                $error = 'This account has been deactivated.';
            } else {
                if ($user) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare('UPDATE users SET password = ?, google_password_set = 1, promotional_email = ? WHERE id = ?');
                    $update->bind_param('sii', $hash, $promo, $user['id']);
                    $update->execute();
                    $update->close();
                } else {
                    $base = preg_replace('/[^A-Za-z0-9_]/', '', (string) strstr($email, '@', true));
                    $base = substr($base, 0, 16);
                    if (strlen($base) < 4) $base = 'user' . $base;
                    $username = $base;
                    $checkU = $conn->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
                    $checkA = $conn->prepare('SELECT 1 FROM admins WHERE username = ? LIMIT 1');
                    for ($i = 0; $i < 20; $i++) {
                        $checkU->bind_param('s', $username);
                        $checkU->execute();
                        $checkU->store_result();
                        $takenU = $checkU->num_rows > 0;
                        $checkA->bind_param('s', $username);
                        $checkA->execute();
                        $checkA->store_result();
                        $takenA = $checkA->num_rows > 0;
                        if (!$takenU && !$takenA) break;
                        $username = substr($base, 0, 12) . random_int(1000, 999999);
                    }
                    $checkU->close();
                    $checkA->close();
                    $fullname = trim((string) ($pending['fullname'] ?? '')) ?: $username;
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $insert = $conn->prepare('INSERT INTO users (fullname, username, email, password, promotional_email, google_password_set) VALUES (?, ?, ?, ?, ?, 1)');
                        $insert->bind_param('ssssi', $fullname, $username, $email, $hash, $promo);
                        $insert->execute();
                        $user = ['id' => (int) $conn->insert_id, 'username' => $username, 'fullname' => $fullname, 'status' => 'Active'];
                        $insert->close();
                    } catch (mysqli_sql_exception $e) {
                        if ((int) $e->getCode() !== 1062) throw $e;
                        $find = $conn->prepare('SELECT id, username, fullname, status FROM users WHERE LOWER(email) = ? LIMIT 1');
                        $find->bind_param('s', $email);
                        $find->execute();
                        $user = $find->get_result()->fetch_assoc();
                        $find->close();
                        if (!$user || ($user['status'] ?? 'Active') === 'Inactive') throw $e;
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $update = $conn->prepare('UPDATE users SET password = ?, google_password_set = 1, promotional_email = ? WHERE id = ?');
                        $update->bind_param('sii', $hash, $promo, $user['id']);
                        $update->execute();
                        $update->close();
                    }
                }
                unset($_SESSION['google_password_setup'], $_SESSION['google_password_csrf']);
                login_throttle_clear($conn);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = 'User';
                $_SESSION['user_name'] = trim((string) ($user['fullname'] ?? '')) ?: $user['username'];
                $_SESSION['session_token'] = kt_session_issue($conn, 'users', 'id', (int) $user['id']);
                $_SESSION['success'] = 'Your KULTOURA password is set. Your username is ' . $user['username'] . '. Use both for manual sign-in.';
                header('Location: ' . BASE_URL . '/index.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Google password setup error: ' . $e->getMessage());
            $error = 'Could not finish account setup. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set your KULTOURA password</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <style>
        :root{--gp-green:#315c2b;--gp-deep:#10291b;--gp-cream:#faf7f2;--gp-ink:#171916}
        body{min-height:100vh;margin:0;background:radial-gradient(ellipse at 50% 110%,#244b31 0,#112b1d 55%,#0c2016 100%);font-family:'Helvetica Neue',Arial,sans-serif;color:var(--gp-ink)}
        .google-password-overlay{min-height:100vh;box-sizing:border-box;display:grid;place-items:center;padding:22px 16px}
        .google-password-card{position:relative;isolation:isolate;overflow-y:auto;width:min(100%,500px);max-height:calc(100vh - 44px);box-sizing:border-box;padding:32px 34px 76px;border:1px solid rgba(49,92,43,.12);border-radius:22px;background:var(--gp-cream);box-shadow:0 24px 80px rgba(0,0,0,.3)}
        .google-password-card>*{position:relative;z-index:1}
        .google-password-card:after{content:'';position:absolute;z-index:0;left:0;right:0;bottom:0;height:86px;background:linear-gradient(166deg,transparent 0 28%,#dce6c9 28.5% 49%,#b5ce9b 49.5% 70%,#648c4b 70.5% 100%);clip-path:polygon(0 24%,12% 35%,30% 76%,49% 86%,68% 67%,83% 62%,100% 0,100% 100%,0 100%)}
        .google-password-card h1{margin:0 0 18px;color:#10120f;font-family:Georgia,'Times New Roman',serif;font-size:clamp(1.65rem,4vw,2.1rem);line-height:1.08;letter-spacing:-.035em}
        .google-password-card p{line-height:1.48;color:#30362e}
        .google-password-note{display:flex;align-items:flex-start;gap:12px;margin:0 0 12px;padding:12px 14px;border-radius:14px;background:#edf0dd;font-size:.88rem}
        .google-password-note svg{width:25px;height:25px;flex:0 0 25px;color:#fff;background:var(--gp-green);border-radius:50%;padding:4px;box-sizing:border-box}
        .google-password-username{margin:0 0 16px 3px;font-size:.9rem}
        .google-password-error{padding:11px 14px;border:1px solid #efc2bc;border-radius:10px;background:#fff0ed;color:#9d2922!important}
        .google-password-form{display:flex;flex-direction:column;gap:12px}
        .google-password-card .form-group{margin:0;gap:8px}
        .google-password-card .form-group label{color:#2d4527;font-size:.92rem;font-weight:750;letter-spacing:0}
        .google-password-field{position:relative}
        .google-password-field input{width:100%;height:48px;box-sizing:border-box;padding:10px 46px 10px 42px;border:1.5px solid #929a92;border-radius:13px;background:#fffdfa;color:#20251f;font-size:.95rem}
        .google-password-field input:focus{outline:3px solid rgba(75,119,59,.19);border-color:#527845}
        .google-password-lock{position:absolute;left:14px;top:50%;width:17px;height:17px;transform:translateY(-50%);color:#647166;pointer-events:none}
        .google-password-toggle{position:absolute;right:10px;top:50%;display:grid;place-items:center;width:34px;height:34px;transform:translateY(-50%);border:0;border-radius:50%;background:transparent;color:#626a64;cursor:pointer}
        .google-password-toggle:hover{background:#e9ecdf;color:var(--gp-green)}.google-password-toggle svg{width:22px;height:22px}
        .google-password-rule{display:flex;align-items:flex-start;gap:10px;margin:0;color:#30362e;font-size:.84rem}
        .google-password-rule svg{width:25px;height:25px;flex:0 0 25px;color:var(--gp-green)}
        .google-password-consents{display:flex;flex-direction:column;gap:10px;margin-top:3px;padding-top:13px;border-top:1px solid #d7d5cc}
        .google-password-consent{display:flex;align-items:flex-start;gap:10px;color:#242822;font-size:.83rem;line-height:1.4;cursor:pointer}
        .google-password-consent input{width:19px;height:19px;flex:0 0 19px;margin:2px 0 0;accent-color:var(--gp-green)}
        .google-password-consent a,.google-password-consent button{padding:0;border:0;background:none;color:var(--gp-green);font:inherit;font-weight:700;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
        .google-password-consent .optional{margin-left:5px;color:#777d75;font-style:italic}
        .google-password-submit{width:100%;margin-top:3px;padding:13px!important;background:linear-gradient(105deg,#32642d,#467c35)!important;font-size:.88rem!important;letter-spacing:.04em!important}
        .google-password-submit:hover{background:#2c5728!important}
        .gp-dialog{width:min(90vw,620px);max-height:75vh;overflow:auto;padding:30px;border:0;border-radius:18px;background:#fffdf9;color:#26312b;box-shadow:0 20px 70px rgba(0,0,0,.35)}
        .gp-dialog::backdrop{background:rgba(9,24,15,.68)}.gp-dialog h2{margin-top:0;font-family:Georgia,serif}.gp-dialog-close{float:right;padding:6px 11px;border:0;border-radius:50%;background:#edf0dd;font-size:1.2rem;cursor:pointer}
        @media(max-width:640px){.google-password-overlay{padding:12px}.google-password-card{max-height:calc(100vh - 24px);padding:26px 22px 68px;border-radius:19px}.google-password-card h1{font-size:1.75rem}.google-password-consent{font-size:.8rem}}
    </style>
</head>
<body>
<main class="google-password-overlay">
    <section class="google-password-card" role="dialog" aria-modal="true" aria-labelledby="setup-title">
        <h1 id="setup-title">Set a KULTOURA password</h1>
        <p class="google-password-note"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M12 10.5v6M12 7.5h.01" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/></svg><span>This is a separate password for manual KULTOURA sign-in using your username. It does not change your Google password. You can still continue using Google to sign in.</span></p>
        <p class="google-password-username"><?php if (!empty($pending['username'])): ?>Your username is <strong><?= htmlspecialchars((string) $pending['username'], ENT_QUOTES, 'UTF-8') ?></strong>.<?php else: ?>A username will be assigned to your account after setup.<?php endif; ?></p>
        <?php if ($error !== ''): ?><p class="google-password-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post" action="google-password.php" class="google-password-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['google_password_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group"><label for="password">KULTOURA password</label><div class="google-password-field"><svg class="google-password-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3" stroke-linecap="round"/></svg><input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required><button class="google-password-toggle" type="button" data-target="password" aria-label="Show KULTOURA password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
            <div class="form-group"><label for="confirm_password">Confirm password</label><div class="google-password-field"><svg class="google-password-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3" stroke-linecap="round"/></svg><input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required><button class="google-password-toggle" type="button" data-target="confirm_password" aria-label="Show confirm password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
            <p class="google-password-rule"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2 20 5v6c0 5-3.4 8.7-8 11-4.6-2.3-8-6-8-11V5l8-3z"/><path d="m8.5 12 2.3 2.3 4.8-5"/></svg><span>Use at least 8 characters, including an uppercase letter and a special character.</span></p>
            <div class="google-password-consents">
                <div class="google-password-consent"><input id="agree_terms" type="checkbox" name="agree_terms" value="1" <?= isset($_POST['agree_terms']) ? 'checked' : '' ?> required><span><label for="agree_terms">I agree to the</label> <button type="button" data-dialog="terms-dialog">Terms of Service</button>.</span></div>
                <div class="google-password-consent"><input id="agree_privacy" type="checkbox" name="agree_privacy" value="1" <?= isset($_POST['agree_privacy']) ? 'checked' : '' ?> required><span><label for="agree_privacy">I agree to the</label> <button type="button" data-dialog="privacy-dialog">Privacy Policy</button>.</span></div>
                <div class="google-password-consent"><input id="promotional_email" type="checkbox" name="promotional_email" value="1" <?= isset($_POST['promotional_email']) ? 'checked' : '' ?>><label for="promotional_email">I agree to receive promotional emails, travel updates, and special offers from KULTOURA. <span class="optional">(Optional)</span></label></div>
            </div>
            <button class="auth-btn google-password-submit" type="submit">CONFIRM PASSWORD</button>
        </form>
    </section>
</main>
<dialog class="gp-dialog" id="terms-dialog"><button type="button" class="gp-dialog-close" aria-label="Close">&times;</button><h2>Terms of Service</h2><p>By creating a KULTOURA account, you agree to use the platform responsibly to explore, save, and share information about Malvar's tourism destinations, products, and services.</p><p>You are responsible for keeping your username and password confidential and for activity under your account. Do not misuse the platform, post false information, disrupt the service, or access another user's account.</p><p>KULTOURA may update these terms from time to time. Continued use after changes means you accept the updated terms.</p></dialog>
<dialog class="gp-dialog" id="privacy-dialog"><button type="button" class="gp-dialog-close" aria-label="Close">&times;</button><h2>Privacy Policy</h2><p>KULTOURA collects your name, username, email address, and password to operate your account. Your password is stored as a one-way hash, never in plain text.</p><p>Your information personalizes your experience and is not sold to third parties. Promotional emails are sent only if you opt in; you can unsubscribe at any time.</p><p>We use safeguards including password hashing and login attempt monitoring to protect your account.</p></dialog>
<script>
document.querySelectorAll('.google-password-toggle').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.dataset.target);var reveal=input.type==='password';input.type=reveal?'text':'password';button.setAttribute('aria-pressed',reveal?'true':'false');button.setAttribute('aria-label',(reveal?'Hide ':'Show ')+(button.dataset.target==='password'?'KULTOURA password':'confirm password'));});});
document.querySelectorAll('[data-dialog]').forEach(function(button){button.addEventListener('click',function(){document.getElementById(button.dataset.dialog).showModal();});});
document.querySelectorAll('.gp-dialog').forEach(function(dialog){dialog.querySelector('.gp-dialog-close').addEventListener('click',function(){dialog.close();});dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});});
</script>
</body>
</html>

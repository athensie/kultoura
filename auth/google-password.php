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
    } elseif (!kt_password_meets_policy($password)) {
        $error = 'Use at least 8 characters, including an uppercase letter and a special character.';
    } elseif ($password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        try {
            kt_google_password_setup_ensure_schema($conn);
            $email = (string) $pending['email'];
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
                    $update = $conn->prepare('UPDATE users SET password = ?, google_password_set = 1 WHERE id = ?');
                    $update->bind_param('si', $hash, $user['id']);
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
                        $insert = $conn->prepare('INSERT INTO users (fullname, username, email, password, promotional_email, google_password_set) VALUES (?, ?, ?, ?, 0, 1)');
                        $insert->bind_param('ssss', $fullname, $username, $email, $hash);
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
                        $update = $conn->prepare('UPDATE users SET password = ?, google_password_set = 1 WHERE id = ?');
                        $update->bind_param('si', $hash, $user['id']);
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
        .google-password-overlay{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;padding:20px;background:rgba(18,25,22,.66)}
        .google-password-card{width:min(100%,460px);box-sizing:border-box;padding:32px;border-radius:18px;background:#fff;color:#26312b;box-shadow:0 20px 70px rgba(0,0,0,.25)}
        .google-password-card h1{margin:0 0 10px;font-size:1.55rem}.google-password-card p{line-height:1.55;color:#58645d}
        .google-password-card .form-group{margin:18px 0}.google-password-card input{width:100%;box-sizing:border-box}
        .google-password-note{padding:12px 14px;border-radius:10px;background:#f1f6f0;font-size:.92rem}
        .google-password-error{padding:10px 12px;border-radius:8px;background:#fff0ef;color:#9d2922}
        .google-password-submit{width:100%;margin-top:8px}
    </style>
</head>
<body>
<main class="google-password-overlay">
    <section class="google-password-card" role="dialog" aria-modal="true" aria-labelledby="setup-title">
        <h1 id="setup-title">Set a KULTOURA password</h1>
        <p class="google-password-note">This is a separate password for manual KULTOURA sign-in using your username. It does not change your Google password. You can still continue using Google to sign in.</p>
        <p><?php if (!empty($pending['username'])): ?>Your username is <strong><?= htmlspecialchars((string) $pending['username'], ENT_QUOTES, 'UTF-8') ?></strong>.<?php else: ?>A username will be assigned to your account after setup.<?php endif; ?></p>
        <?php if ($error !== ''): ?><p class="google-password-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post" action="google-password.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['google_password_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group"><label for="password">KULTOURA password</label><input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required></div>
            <div class="form-group"><label for="confirm_password">Confirm password</label><input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
            <p>Use at least 8 characters, including an uppercase letter and a special character.</p>
            <button class="auth-btn google-password-submit" type="submit">SET PASSWORD &amp; CONTINUE</button>
        </form>
    </section>
</main>
</body>
</html>

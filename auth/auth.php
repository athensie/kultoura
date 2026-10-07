<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once '../config/dbmain.php';
require_once '../config/login_throttle.php';
require_once '../config/password_policy.php';
require_once '../config/mailer.php';

/*
 |--------------------------------------------------------------------
 | BASE URL
 |--------------------------------------------------------------------
 | This file lives in /kultoura/auth/, so a bare header("Location: login.php")
 | resolves relative to /auth/ — which is exactly why admins were landing
 | on /kultoura/auth/admindashboard.php instead of /kultoura/admin/admindashboard.php.
 | Anchoring every redirect to a fixed base path fixes that regardless of
 | which folder a script is called from. Keep this in sync with the same
 | constant in login.php / admindashboard.php.
 */
define('BASE_URL', '/kultoura');

/* ─────────────────────────────────────────────
   TABLE STRUCTURE (confirmed):

   `admins` table:
     admin_id, first_name, last_name, username, email,
     password (hashed), role, status, last_login,
     created_at, updated_at

   `users` table:
     id, fullname, username, email, password (hashed),
     promotional_email, created_at
───────────────────────────────────────────── */


$action = $_GET['action'] ?? ($_POST['action'] ?? '');

/*
 |--------------------------------------------------------------------
 | LOGOUT
 |--------------------------------------------------------------------
 | The sidebar "Log Out" link hits this as a GET request
 | (auth.php?action=logout). Clear the session completely, then send
 | everyone — admin, super admin, or regular user — to the site root.
 */
if ($action === 'logout') {
    $_SESSION = [];

    // Also remove the session cookie itself, not just its contents.
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_unset();
    session_destroy();

    header("Location: " . BASE_URL . "/index.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | SIGNUP
 |--------------------------------------------------------------------
 */
if ($action === 'signup') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $promo    = isset($_POST['promotional_email']) ? 1 : 0;
    $agreedTerms  = isset($_POST['agree_terms']);
    $agreedPrivacy = isset($_POST['agree_privacy']);

    $error = null;
    if ($fullname === '' || !preg_match("/^[A-Za-z\s.'-]+$/", $fullname)) {
        $error = 'Full name should only contain letters.';
    } elseif ($username === '' || !preg_match('/^[A-Za-z0-9_]+$/', $username) || strlen($username) < 4 || strlen($username) > 20) {
        $error = 'Username must be 4-20 characters and contain only letters, numbers, and underscores.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!kt_password_meets_policy($password)) {
        $error = 'Password must be at least 8 characters long and include an uppercase letter and a special character.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$agreedTerms || !$agreedPrivacy) {
        $error = 'You must agree to both the Terms of Service and the Privacy Policy to create an account.';
    }

    if ($error === null) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (fullname, username, email, password, promotional_email) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssi', $fullname, $username, $email, $hash, $promo);

        // mysqli throws on error by default (PHP 8.1+ driver default report
        // mode) rather than returning false — a duplicate username/email
        // must be caught here or it surfaces as an uncaught fatal error.
        try {
            $stmt->execute();
            $newUserId = $stmt->insert_id;
            $stmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id']   = $newUserId;
            $_SESSION['username']  = $username;
            $_SESSION['role']      = 'User';
            $_SESSION['user_name'] = $fullname;

            header("Location: " . BASE_URL . "/index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            // 1062 = duplicate key (username or email already taken).
            $error = ($e->getCode() === 1062)
                ? 'That username or email is already registered.'
                : 'Could not create your account. Please try again.';
        }
    }

    $_SESSION['error'] = $error;
    header("Location: " . BASE_URL . "/auth/signup.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | FORGOT PASSWORD — step 1: verify identity (username + email match)
 |--------------------------------------------------------------------
 | No email is actually sent (this project has no mail-sending
 | capability configured). Instead, a visitor proves ownership by
 | supplying BOTH the username AND the email on file for that account;
 | a match unlocks a short-lived (10-minute) session flag that lets
 | them set a new password on the very next request. This is weaker
 | than a real emailed reset link (no out-of-band confirmation), but
 | it's the realistic self-service option without adding SMTP/PHPMailer.
 */
if ($action === 'verify_reset') {
    $throttleMessage = login_throttle_check($conn);
    if ($throttleMessage !== null) {
        $_SESSION['error'] = $throttleMessage;
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if ($username === '' || $email === '') {
        $_SESSION['error'] = 'Please enter both your username and email address.';
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $matchedId    = null;
    $matchedTable = null;

    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE username = ? AND email = ? LIMIT 1");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $matchedId    = (int) $row['admin_id'];
        $matchedTable = 'admins';
    }

    if ($matchedId === null) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND email = ? LIMIT 1");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $matchedId    = (int) $row['id'];
            $matchedTable = 'users';
        }
    }

    if ($matchedId === null) {
        $attemptsLeft = login_throttle_record_failure($conn);
        $_SESSION['error'] = login_throttle_failure_message(
            "We couldn't find an account with that username and email combination.",
            $attemptsLeft
        );
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    login_throttle_clear($conn);
    session_regenerate_id(true);

    $code = (string) random_int(100000, 999999);
    $sent = kt_send_mail(
        $email,
        'Confirm your KULTOURA password change',
        "We received a request to change the password for your KULTOURA account.\r\n\r\n"
        . "Your confirmation code is: $code\r\n\r\n"
        . "The code expires in 10 minutes. If you didn't request this, you can ignore this email; your password won't change."
    );
    if (!$sent) {
        $_SESSION['error'] = "We couldn't send the confirmation email right now. Please try again in a few minutes.";
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $_SESSION['reset_pending'] = [
        'id'        => $matchedId,
        'table'     => $matchedTable,
        'email'     => $email,
        'code_hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires'   => time() + 600,
        'sent_at'   => time(),
        'attempts'  => 0,
    ];
    $_SESSION['success'] = 'We sent a 6-digit confirmation code to your email.';

    header("Location: " . BASE_URL . "/auth/reset-code.php");
    exit;
}

/* ── FORGOT PASSWORD — step 1b: check the emailed confirmation code ── */
if ($action === 'verify_code') {
    $pending = $_SESSION['reset_pending'] ?? null;
    if (!$pending || time() > $pending['expires']) {
        unset($_SESSION['reset_pending']);
        $_SESSION['error'] = 'That code has expired. Please start again.';
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $submitted = preg_replace('/\D/', '', $_POST['code'] ?? '');
    if (!password_verify($submitted, $pending['code_hash'])) {
        $pending['attempts']++;
        if ($pending['attempts'] >= 5) {
            unset($_SESSION['reset_pending']);
            $_SESSION['error'] = 'Too many incorrect codes. Please start again.';
            header("Location: " . BASE_URL . "/auth/forgot-password.php");
            exit;
        }
        $_SESSION['reset_pending'] = $pending;
        $left = 5 - $pending['attempts'];
        $_SESSION['error'] = 'That code is incorrect. You have ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
        header("Location: " . BASE_URL . "/auth/reset-code.php");
        exit;
    }

    unset($_SESSION['reset_pending']);
    $_SESSION['reset_verified_id']      = $pending['id'];
    $_SESSION['reset_verified_table']   = $pending['table'];
    $_SESSION['reset_verified_expires'] = time() + 600;

    header("Location: " . BASE_URL . "/auth/reset-password.php");
    exit;
}

/* ── FORGOT PASSWORD — resend the confirmation code (60s cooldown) ── */
if ($action === 'resend_code') {
    $pending = $_SESSION['reset_pending'] ?? null;
    if (!$pending) {
        $_SESSION['error'] = 'Please enter your username and email again.';
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $wait = 60 - (time() - $pending['sent_at']);
    if ($wait > 0) {
        $_SESSION['error'] = "Please wait {$wait} seconds before requesting another code.";
        header("Location: " . BASE_URL . "/auth/reset-code.php");
        exit;
    }

    $code = (string) random_int(100000, 999999);
    $sent = kt_send_mail(
        $pending['email'],
        'Confirm your KULTOURA password change',
        "Your new confirmation code is: $code\r\n\r\n"
        . "The code expires in 10 minutes. If you didn't request this, you can ignore this email; your password won't change."
    );
    if (!$sent) {
        $_SESSION['error'] = "We couldn't send the email right now. Please try again in a few minutes.";
        header("Location: " . BASE_URL . "/auth/reset-code.php");
        exit;
    }

    $pending['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
    $pending['expires']   = time() + 600;
    $pending['sent_at']   = time();
    $pending['attempts']  = 0;
    $_SESSION['reset_pending'] = $pending;
    $_SESSION['success'] = 'A new confirmation code was sent to your email.';

    header("Location: " . BASE_URL . "/auth/reset-code.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | FORGOT PASSWORD — step 2: actually set the new password
 |--------------------------------------------------------------------
 | Only reachable with a fresh 'reset_verified_*' session flag set by
 | the verify_reset step above (see reset-password.php's own guard too).
 */
if ($action === 'do_reset') {
    $verifiedId      = $_SESSION['reset_verified_id'] ?? null;
    $verifiedTable   = $_SESSION['reset_verified_table'] ?? null;
    $verifiedExpires = $_SESSION['reset_verified_expires'] ?? 0;

    if (!$verifiedId || !in_array($verifiedTable, ['admins', 'users'], true) || time() > $verifiedExpires) {
        unset($_SESSION['reset_verified_id'], $_SESSION['reset_verified_table'], $_SESSION['reset_verified_expires']);
        $_SESSION['error'] = 'Your reset session expired. Please verify your identity again.';
        header("Location: " . BASE_URL . "/auth/forgot-password.php");
        exit;
    }

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!kt_password_meets_policy($password)) {
        $_SESSION['error'] = 'Password must be at least 8 characters long and include an uppercase letter and a special character.';
        header("Location: " . BASE_URL . "/auth/reset-password.php");
        exit;
    }
    if ($password !== $confirm) {
        $_SESSION['error'] = 'Passwords do not match.';
        header("Location: " . BASE_URL . "/auth/reset-password.php");
        exit;
    }

    $hash  = password_hash($password, PASSWORD_DEFAULT);
    $idCol = $verifiedTable === 'admins' ? 'admin_id' : 'id';
    $stmt  = $conn->prepare("UPDATE {$verifiedTable} SET password = ? WHERE {$idCol} = ?");
    $stmt->bind_param('si', $hash, $verifiedId);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['reset_verified_id'], $_SESSION['reset_verified_table'], $_SESSION['reset_verified_expires']);

    $_SESSION['success'] = 'Your password has been reset. You can now sign in.';
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

if ($action !== 'login') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Keeps what was typed on the login page after a failed attempt. Stored
// one-time only: login.php reads and clears it on the very next page load.
$keepLoginInput = function () use ($username, $password) {
    $_SESSION['login_retry'] = ['username' => $username, 'password' => $password];
};

/* ── Brute-force check — before touching credentials or the DB tables ── */
$throttleMessage = login_throttle_check($conn);
if ($throttleMessage !== null) {
    $_SESSION['error'] = $throttleMessage;
    $keepLoginInput();
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Please enter both username and password.';
    $keepLoginInput();
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

/* ── Try admins table first ── */
$stmt = $conn->prepare("SELECT admin_id, username, password, role, first_name, last_name FROM admins WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if ($admin && password_verify($password, $admin['password'])) {
    login_throttle_clear($conn);
    session_regenerate_id(true); // new session ID on every login — blocks session fixation

    $_SESSION['user_id']   = $admin['admin_id'];
    $_SESSION['username']  = $admin['username'];
    $_SESSION['role']      = $admin['role'];
    $_SESSION['user_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);

    header("Location: " . BASE_URL . "/admin/admindashboard.php");
    exit;
}

/* ── Fall back to users table ── */
$stmt = $conn->prepare("SELECT id, username, password, fullname FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    login_throttle_clear($conn);
    session_regenerate_id(true); // new session ID on every login — blocks session fixation

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = 'User';
    $_SESSION['user_name'] = $user['fullname'];

    header("Location: " . BASE_URL . "/index.php");
    exit;
}

/* ── Neither table matched ── */
$attemptsLeft = login_throttle_record_failure($conn);
$_SESSION['error'] = login_throttle_failure_message('', $attemptsLeft);

// Which field is wrong: the username doesn't exist, or it exists but the password didn't match.
$usernameExists = $admin !== null || $user !== null;
$_SESSION['login_field_errors'] = $usernameExists
    ? ['password' => 'Your password is incorrect.']
    : ['username' => 'This username does not exist.'];

$keepLoginInput();
header("Location: " . BASE_URL . "/auth/login.php");
exit;
<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once '../config/dbmain.php';
require_once '../config/login_throttle.php';

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

    $error = null;
    if ($fullname === '' || !preg_match("/^[A-Za-z\s.'-]+$/", $fullname)) {
        $error = 'Full name should only contain letters.';
    } elseif ($username === '' || !preg_match('/^[A-Za-z0-9_]+$/', $username) || strlen($username) < 3 || strlen($username) > 20) {
        $error = 'Username must be 3-20 characters and contain only letters, numbers, and underscores.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
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

if ($action !== 'login') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

/* ── Brute-force check — before touching credentials or the DB tables ── */
$throttleMessage = login_throttle_check($conn);
if ($throttleMessage !== null) {
    $_SESSION['error'] = $throttleMessage;
    header("Location: " . BASE_URL . "/auth/login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error'] = 'Please enter both username and password.';
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
login_throttle_record_failure($conn);
$_SESSION['error'] = 'Invalid username or password.';
header("Location: " . BASE_URL . "/auth/login.php");
exit;
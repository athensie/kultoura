<?php
session_start();
require_once '../config/dbmain.php';

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

if ($action !== 'login') {
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
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = 'User';
    $_SESSION['user_name'] = $user['fullname'];

    header("Location: " . BASE_URL . "/index.php");
    exit;
}

/* ── Neither table matched ── */
$_SESSION['error'] = 'Invalid username or password.';
header("Location: " . BASE_URL . "/auth/login.php");
exit;
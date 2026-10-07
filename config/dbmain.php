<?php
/*
 |--------------------------------------------------------------------
 | DB CONNECTION — XAMPP locally, env vars in hosted deployments
 |--------------------------------------------------------------------
 | Local XAMPP has no env vars set, so these fall back to the same
 | defaults as before (root / no password / kultoura_db). A host like
 | Railway's MySQL plugin injects MYSQLHOST/MYSQLPORT/MYSQLUSER/
 | MYSQLPASSWORD/MYSQLDATABASE automatically — no code change needed
 | there beyond setting those in the service's environment.
 */
$host   = getenv('MYSQLHOST') ?: "localhost";
$port   = (int) (getenv('MYSQLPORT') ?: 3306);
$user   = getenv('MYSQLUSER') ?: "root";
$pass   = getenv('MYSQLPASSWORD') ?: "";            // default XAMPP: no password
$dbname = getenv('MYSQLDATABASE') ?: "kultoura_db";

/*
 |--------------------------------------------------------------------
 | ERROR VISIBILITY — hide details in production, keep them local
 |--------------------------------------------------------------------
 | Same "is this hosted?" signal dbmain.php already uses above. On
 | Railway, PHP's default display_errors=On would otherwise print raw
 | DB errors, file paths, and stack traces straight to visitors.
 | Locally it's left alone so XAMPP development still shows errors.
 */
$isHosted = (bool) getenv('MYSQLHOST');
if ($isHosted) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die($isHosted ? 'Something went wrong. Please try again shortly.' : "Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/*
 |--------------------------------------------------------------------
 | SINGLE-SESSION ENFORCEMENT + ONLINE/OFFLINE HEARTBEAT
 |--------------------------------------------------------------------
 | Every page that includes this file has already called session_start()
 | first, so $_SESSION is available here. If the current request belongs
 | to a logged-in admin or site user, confirm this is still that
 | account's one valid session (see config/session_security.php — a
 | newer login or a password change elsewhere issues a new token, which
 | makes this one stop matching) before stamping last_activity so
 | adminusers.php can tell who's currently active.
 |
 | Sessions logged in before this feature shipped have no
 | session_token yet and are left alone here — they'll pick one up
 | next time they log in.
 |
 | Requires these columns (run once):
 |   ALTER TABLE admins ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
 |   ALTER TABLE users  ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
 */
require_once __DIR__ . '/session_security.php';

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    // Site users also get a 'role' key (set to 'User'), so presence alone
    // doesn't distinguish them from admins — only the actual value does.
    $isAdminSession = in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'super admin'], true);
    $table = $isAdminSession ? 'admins' : 'users';
    $idCol = $isAdminSession ? 'admin_id' : 'id';
    $uid   = (int) $_SESSION['user_id'];

    if (isset($_SESSION['session_token']) && !kt_session_is_current($conn, $table, $idCol, $uid, $_SESSION['session_token'])) {
        $_SESSION = [];
        $_SESSION['error'] = "You were signed out because this account was signed in somewhere else, or its password was changed.";
    } else {
        $stmt = $conn->prepare("UPDATE `$table` SET last_activity = NOW() WHERE `$idCol` = ?");
        if ($stmt) {
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>
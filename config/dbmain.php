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
 | ONLINE/OFFLINE HEARTBEAT
 |--------------------------------------------------------------------
 | Every page that includes this file has already called session_start()
 | first, so $_SESSION is available here. If the current request belongs
 | to a logged-in admin or site user, stamp their last_activity so
 | adminusers.php can tell who's currently active.
 |
 | Requires these columns (run once):
 |   ALTER TABLE admins ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
 |   ALTER TABLE users  ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL;
 */
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    if (isset($_SESSION['role'])) {
        // Admin session (admin pages set both user_id and role)
        $stmt = $conn->prepare("UPDATE admins SET last_activity = NOW() WHERE admin_id = ?");
    } else {
        // Site visitor session
        $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    }
    if ($stmt) {
        $uid = (int) $_SESSION['user_id'];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
    }
}
?>
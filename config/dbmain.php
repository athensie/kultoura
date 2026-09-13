<?php
$host   = "localhost";
$user   = "root";
$pass   = "";            // default XAMPP: no password
$dbname = "kultoura_db";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
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
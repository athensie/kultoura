<?php
$host   = getenv('MYSQLHOST') ?: "localhost";
$port   = (int) (getenv('MYSQLPORT') ?: 3306);
$user   = getenv('MYSQLUSER') ?: "root";
$pass   = getenv('MYSQLPASSWORD') ?: "";
$dbname = getenv('MYSQLDATABASE') ?: "kultoura_db";
$isHosted = (bool) getenv('MYSQLHOST');
if ($isHosted) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
$conn = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die($isHosted ? 'Something went wrong. Please try again shortly.' : "Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
require_once __DIR__ . '/admin_requests.php';
kt_requests_ensure_schema($conn);
require_once __DIR__ . '/session_security.php';
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
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

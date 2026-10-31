<?php
require_once __DIR__ . '/../config/session_boot.php';
$role = strtolower($_SESSION['role'] ?? '');
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super admin'], true)) {
    header("Location: ../auth/login.php");
    exit;
}

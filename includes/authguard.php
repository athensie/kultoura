<?php
/**
 * Include this at the very top of every admin dashboard page,
 * before any HTML output.
 */
session_start();

$role = strtolower($_SESSION['role'] ?? '');

if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'super admin'], true)) {
    header("Location: ../auth/login.php");
    exit;
}
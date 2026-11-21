<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['admin', 'super admin'], true)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}
$reviewParams = ['review_tab' => ($_GET['tab'] ?? '') === 'reports' ? 'reports' : 'ratings'];
if (isset($_GET['rating'])) $reviewParams['rating'] = (int) $_GET['rating'];
header('Location: ' . BASE_URL . '/admin/admindashboard.php?' . http_build_query($reviewParams) . '#community');
exit;

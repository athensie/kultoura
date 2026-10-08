<?php
/*
 |--------------------------------------------------------------------
 | NOTIFICATIONS API — small JSON endpoint behind the bell icon that
 | assets/js/admin-notifications.js polls. Any logged-in admin or
 | super admin can read their own notifications; nothing here lets one
 | admin see or touch another's.
 |--------------------------------------------------------------------
 */
require_once __DIR__ . '/../config/session_boot.php';

define('BASE_URL', '/kultoura');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'super admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/admin_requests.php';

$adminId = (int) $_SESSION['user_id'];
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list');

if ($action === 'mark_all_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    kt_notifications_mark_all_read($conn, $adminId);
    echo json_encode(['ok' => true]);
    exit;
}

$notifications = array_map(function ($n) {
    return [
        'id'        => (int) $n['notification_id'],
        'message'   => $n['message'],
        'link'      => $n['link'],
        'isRead'    => (bool) $n['is_read'],
        'createdAt' => $n['created_at'],
    ];
}, kt_notifications_list($conn, $adminId));

echo json_encode([
    'unread'        => kt_notifications_unread_count($conn, $adminId),
    'notifications' => $notifications,
]);

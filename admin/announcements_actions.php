<?php
require_once __DIR__ . '/../config/session_boot.php';
define('BASE_URL', '/kultoura');
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'super admin'], true)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
include '../config/dbmain.php';
include '../config/announcements.php';
require_once '../config/csrf.php';
require_once '../config/admin_requests.php';
$isSuperAdmin = kt_is_super_admin();
$adminName    = $_SESSION['username'] ?? 'Admin';
kt_requests_ensure_schema($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: adminannouncements.php");
    exit;
}
csrf_verify();
function kt_handle_announcement_image_upload(): ?string
{
    if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return null;
    }
    if (@getimagesize($_FILES['image_file']['tmp_name']) === false) {
        return null;
    }
    $uploadDir = __DIR__ . '/../assets/uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $filename = uniqid('ann_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename)) {
        return BASE_URL . '/assets/uploads/announcements/' . $filename;
    }
    return null;
}
$validTypes    = ['info', 'alert', 'event', 'update', 'maintenance'];
$validStatuses = ['live', 'draft', 'scheduled', 'archived'];
$action = $_POST['action'] ?? '';
$entityAction = $action === 'create' ? 'create' : ($action === 'update' ? 'update' : 'archive');
$id = ($action === 'update' || $action === 'delete') ? (int) ($_POST['id'] ?? 0) : null;
if ($entityAction === 'archive') {
    $data = ['archive_note' => $_POST['archive_note'] ?? ''];
    $stmt = $conn->prepare("SELECT title AS name FROM announcements WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $title = $row['name'] ?? ('Announcement #' . $id);
} else {
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $type     = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'info';
    $status   = in_array($_POST['status'] ?? '', $validStatuses, true) ? $_POST['status'] : 'draft';
        $audience = trim($_POST['audience'] ?? '');
    if (!in_array($audience, ['All Subscribers', 'Website Only'], true)) {
        $audience = 'All Subscribers';
        if ($entityAction === 'update' && $id) {
            $q = $conn->prepare("SELECT audience FROM announcements WHERE id = ?");
            $q->bind_param('i', $id);
            $q->execute();
            $old = $q->get_result()->fetch_assoc();
            $q->close();
            if (!empty($old['audience'])) $audience = $old['audience'];
        }
    }
    $newImage = kt_handle_announcement_image_upload();
    $image    = $entityAction === 'create' ? ($newImage ?? '') : ($newImage ?? trim($_POST['existing_image'] ?? ''));
    $scheduledAt = null;
    if ($status === 'scheduled' && !empty($_POST['scheduled_at'])) {
        $ts = strtotime($_POST['scheduled_at']);
        if ($ts !== false) $scheduledAt = date('Y-m-d H:i:s', $ts);
        if (!$scheduledAt) $status = 'draft';
    }
    if ($title === '' || $body === '') {
        $_SESSION['admin_flash'] = 'Please enter a title and a message.';
        header("Location: adminannouncements.php");
        exit;
    }
    $data = [
        'title'        => $title,
        'body'         => $body,
        'type'         => $type,
        'audience'     => $audience,
        'image'        => $image,
        'status'       => $status,
        'scheduled_at' => $scheduledAt,
    ];
}
if ($isSuperAdmin) {
    kt_apply_entity_change($conn, 'announcement', $entityAction, $id, $data);
    $_SESSION['admin_flash'] = $entityAction === 'archive'
        ? '"' . $title . '" archived.'
        : ($entityAction === 'create'
            ? (($data['status'] ?? '') === 'scheduled' ? '"' . $title . '" was scheduled.' : ((($data['status'] ?? '') === 'draft') ? '"' . $title . '" was saved as a draft.' : '"' . $title . '" was published.'))
            : '"' . $title . '" was updated.');
} else {
    kt_requests_create($conn, 'announcement', $entityAction, $id, $data, $title, (int) $_SESSION['user_id'], $adminName);
    $_SESSION['admin_flash'] = 'Your request to ' . ($entityAction === 'archive' ? 'archive' : $entityAction) . ' "' . $title . '" was submitted for Super Admin approval.';
}
header("Location: adminannouncements.php");
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); } else { @ob_flush(); flush(); }
if ($isSuperAdmin) {
    require_once '../config/mailer.php';
    require_once '../config/newsletter.php';
    kt_newsletter_dispatch_pending($conn);
}
exit;

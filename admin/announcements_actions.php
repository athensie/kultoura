<?php
/*
 |--------------------------------------------------------------------
 | Announcements — Create / Update / Delete
 |--------------------------------------------------------------------
 | Classic POST -> redirect -> GET back to adminannouncements.php, same
 | convention as the rest of the admin panel. This is a separate file
 | (rather than inline, like admindestinations.php etc.) because the
 | announcements composer/edit/delete forms already targeted this exact
 | path — kept that instead of rewiring three form actions.
 */
session_start();

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: adminannouncements.php");
    exit;
}

/*
 |--------------------------------------------------------------------
 | IMAGE UPLOAD — mirrors admin/adminfoodanddining.php's
 | kt_handle_image_upload(): returns an absolute site path so <img src>
 | resolves correctly from /admin/, /pages/, or index.php alike.
 |--------------------------------------------------------------------
 */
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

// Deletes the physical file behind a stored /kultoura/assets/uploads/... path,
// but only ever within that one uploads folder — never trusts the path enough
// to unlink anything outside it.
function kt_delete_announcement_image(?string $imagePath): void
{
    if (empty($imagePath)) return;
    $uploadDir = realpath(__DIR__ . '/../assets/uploads/announcements/');
    $target = realpath(__DIR__ . '/../' . ltrim(str_replace(BASE_URL, '', $imagePath), '/'));
    if ($uploadDir && $target && str_starts_with($target, $uploadDir) && is_file($target)) {
        unlink($target);
    }
}

$validTypes    = ['info', 'alert', 'event', 'update', 'maintenance'];
$validStatuses = ['live', 'draft', 'scheduled', 'archived'];
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $type     = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'info';
    $status   = in_array($_POST['status'] ?? '', $validStatuses, true) ? $_POST['status'] : 'draft';
    $audience = trim($_POST['audience'] ?? '') ?: 'All Users';
    $image    = kt_handle_announcement_image_upload();

    $scheduledAt = null;
    if ($status === 'scheduled' && !empty($_POST['scheduled_at'])) {
        $ts = strtotime($_POST['scheduled_at']);
        if ($ts !== false) $scheduledAt = date('Y-m-d H:i:s', $ts);
        if (!$scheduledAt) $status = 'draft'; // no valid date picked — fall back safely
    }

    if ($title === '' || $body === '') {
        $_SESSION['admin_flash'] = 'Please enter a title and a message.';
    } else {
        $publishedAt = $status === 'live' ? date('Y-m-d H:i:s') : null;

        $stmt = $conn->prepare(
            "INSERT INTO announcements (title, body, type, audience, image, status, scheduled_at, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssssss', $title, $body, $type, $audience, $image, $status, $scheduledAt, $publishedAt);
        $stmt->execute();
        $stmt->close();

        $_SESSION['admin_flash'] = $status === 'scheduled'
            ? '"' . $title . '" was scheduled.'
            : ($status === 'draft' ? '"' . $title . '" was saved as a draft.' : '"' . $title . '" was published.');
    }
} elseif ($action === 'update') {
    $id       = (int) ($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $type     = in_array($_POST['type'] ?? '', $validTypes, true) ? $_POST['type'] : 'info';
    $status   = in_array($_POST['status'] ?? '', $validStatuses, true) ? $_POST['status'] : 'draft';
    // Keep the existing image unless a new file was uploaded this time.
    $newImage = kt_handle_announcement_image_upload();
    $image    = $newImage ?? trim($_POST['existing_image'] ?? '');

    if ($id <= 0 || $title === '' || $body === '') {
        $_SESSION['admin_flash'] = 'Please enter a title and a message.';
    } else {
        // published_at is only ever set the first time a post goes live —
        // re-saving an already-live announcement doesn't bump its date.
        $stmt = $conn->prepare(
            "UPDATE announcements
             SET title = ?, body = ?, type = ?, status = ?, image = ?,
                 published_at = CASE WHEN ? = 'live' AND published_at IS NULL THEN NOW() ELSE published_at END
             WHERE id = ?"
        );
        $stmt->bind_param('ssssssi', $title, $body, $type, $status, $image, $status, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['admin_flash'] = '"' . $title . '" was updated.';
    }
} elseif ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT image FROM announcements WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        if ($row) kt_delete_announcement_image($row['image']);

        $_SESSION['admin_flash'] = 'Announcement removed.';
    }
}

header("Location: adminannouncements.php");
exit;

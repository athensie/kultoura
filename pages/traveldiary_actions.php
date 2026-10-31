<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'needsLogin' => true, 'message' => 'Please sign in to use your Travel Diary.']);
    exit;
}
$userId = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$validTypes = ['product', 'restaurant', 'destination', 'fiesta', 'person'];
function td_save_uploaded_photo(array $file): array
{
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxBytes   = 5 * 1024 * 1024;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'message' => 'That photo is too large (max 5MB).'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'message' => 'Please upload a JPG, PNG, WEBP, or GIF image.'];
    }
    if (@getimagesize($file['tmp_name']) === false) {
        return ['ok' => false, 'message' => 'That file doesn\'t look like a valid image.'];
    }
    $uploadDir = __DIR__ . '/../assets/uploads/diary/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $safeName = preg_replace('/[^a-z0-9_-]/i', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $filename = uniqid('diary_', true) . '_' . $safeName . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return ['ok' => false, 'message' => 'Could not save the uploaded photo.'];
    }
    return ['ok' => true, 'filename' => $filename];
}
if ($action === 'mark_visited') {
    $itemType         = $_POST['item_type'] ?? '';
    $itemId           = (int) ($_POST['item_id'] ?? 0);
    $note             = trim($_POST['note'] ?? '');
    $visitedDateInput = trim($_POST['visited_date'] ?? '');
    if (!in_array($itemType, $validTypes, true) || $itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit;
    }
    $today = date('Y-m-d');
    $visitedDate = $today;
    if ($visitedDateInput !== '') {
        $parsed = DateTime::createFromFormat('Y-m-d', $visitedDateInput);
        if ($parsed && $parsed->format('Y-m-d') === $visitedDateInput && $visitedDateInput <= $today) {
            $visitedDate = $visitedDateInput;
        }
    }
    $stmt = $conn->prepare(
        "INSERT INTO diary_entries (user_id, item_type, item_id, visited_date, note) VALUES (?, ?, ?, ?, ?)"
    );
    $noteParam = $note !== '' ? $note : null;
    $stmt->bind_param('isiss', $userId, $itemType, $itemId, $visitedDate, $noteParam);
    $stmt->execute();
    $entryId = $stmt->insert_id;
    $stmt->close();
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM diary_entries WHERE user_id = ? AND item_type = ? AND item_id = ?"
    );
    $countStmt->bind_param('isi', $userId, $itemType, $itemId);
    $countStmt->execute();
    $visitCount = (int) $countStmt->get_result()->fetch_assoc()['c'];
    $countStmt->close();
    echo json_encode(['success' => true, 'entryId' => $entryId, 'visitCount' => $visitCount]);
    exit;
}
if ($action === 'unmark_visited') {
    $itemType = $_POST['item_type'] ?? '';
    $itemId   = (int) ($_POST['item_id'] ?? 0);
    if (!in_array($itemType, $validTypes, true) || $itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit;
    }
    $stmt = $conn->prepare(
        "SELECT entry_id FROM diary_entries WHERE user_id = ? AND item_type = ? AND item_id = ?"
    );
    $stmt->bind_param('isi', $userId, $itemType, $itemId);
    $stmt->execute();
    $entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (count($entries) === 0) {
        echo json_encode(['success' => true, 'visitCount' => 0]);
        exit;
    }
    if (count($entries) > 1) {
        echo json_encode(['success' => false, 'message' => "You've logged multiple visits here — remove them individually from your Timeline below."]);
        exit;
    }
    $entryId = (int) $entries[0]['entry_id'];
    $photoStmt = $conn->prepare("SELECT COUNT(*) AS c FROM diary_photos WHERE entry_id = ?");
    $photoStmt->bind_param('i', $entryId);
    $photoStmt->execute();
    $hasPhotos = (int) $photoStmt->get_result()->fetch_assoc()['c'] > 0;
    $photoStmt->close();
    if ($hasPhotos) {
        echo json_encode(['success' => false, 'message' => "That visit has photos attached — remove it from your Timeline below."]);
        exit;
    }
    $delStmt = $conn->prepare("DELETE FROM diary_entries WHERE entry_id = ? AND user_id = ?");
    $delStmt->bind_param('ii', $entryId, $userId);
    $delStmt->execute();
    $delStmt->close();
    echo json_encode(['success' => true, 'visitCount' => 0]);
    exit;
}
if ($action === 'upload_photo') {
    $itemType = $_POST['item_type'] ?? '';
    $itemId   = (int) ($_POST['item_id'] ?? 0);
    $caption  = trim($_POST['caption'] ?? '');
    if (!in_array($itemType, $validTypes, true) || $itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit;
    }
    if (empty($_FILES['photo'])) {
        echo json_encode(['success' => false, 'message' => 'No photo was sent.']);
        exit;
    }
    $saved = td_save_uploaded_photo($_FILES['photo']);
    if (!$saved['ok']) {
        echo json_encode(['success' => false, 'message' => $saved['message']]);
        exit;
    }
    $findStmt = $conn->prepare(
        "SELECT entry_id FROM diary_entries WHERE user_id = ? AND item_type = ? AND item_id = ? AND visited_date = CURDATE() ORDER BY entry_id DESC LIMIT 1"
    );
    $findStmt->bind_param('isi', $userId, $itemType, $itemId);
    $findStmt->execute();
    $existing = $findStmt->get_result()->fetch_assoc();
    $findStmt->close();
    if ($existing) {
        $entryId = (int) $existing['entry_id'];
    } else {
        $insStmt = $conn->prepare(
            "INSERT INTO diary_entries (user_id, item_type, item_id, visited_date) VALUES (?, ?, ?, CURDATE())"
        );
        $insStmt->bind_param('isi', $userId, $itemType, $itemId);
        $insStmt->execute();
        $entryId = $insStmt->insert_id;
        $insStmt->close();
    }
    $photoStmt = $conn->prepare("INSERT INTO diary_photos (entry_id, image, caption) VALUES (?, ?, ?)");
    $captionParam = $caption !== '' ? $caption : null;
    $photoStmt->bind_param('iss', $entryId, $saved['filename'], $captionParam);
    $photoStmt->execute();
    $photoId = $photoStmt->insert_id;
    $photoStmt->close();
    echo json_encode([
        'success' => true,
        'entryId' => $entryId,
        'photoId' => $photoId,
        'image'   => '../assets/uploads/diary/' . $saved['filename'],
    ]);
    exit;
}
if ($action === 'save_note') {
    $itemType = $_POST['item_type'] ?? '';
    $itemId   = (int) ($_POST['item_id'] ?? 0);
    $note     = trim($_POST['note'] ?? '');
    if (!in_array($itemType, $validTypes, true) || $itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit;
    }
    $findStmt = $conn->prepare(
        "SELECT entry_id FROM diary_entries WHERE user_id = ? AND item_type = ? AND item_id = ? ORDER BY visited_date DESC, entry_id DESC LIMIT 1"
    );
    $findStmt->bind_param('isi', $userId, $itemType, $itemId);
    $findStmt->execute();
    $existing = $findStmt->get_result()->fetch_assoc();
    $findStmt->close();
    $noteParam = $note !== '' ? $note : null;
    if ($existing) {
        $entryId = (int) $existing['entry_id'];
        $updStmt = $conn->prepare("UPDATE diary_entries SET note = ? WHERE entry_id = ? AND user_id = ?");
        $updStmt->bind_param('sii', $noteParam, $entryId, $userId);
        $updStmt->execute();
        $updStmt->close();
    } else {
        $insStmt = $conn->prepare(
            "INSERT INTO diary_entries (user_id, item_type, item_id, visited_date, note) VALUES (?, ?, ?, CURDATE(), ?)"
        );
        $insStmt->bind_param('isis', $userId, $itemType, $itemId, $noteParam);
        $insStmt->execute();
        $entryId = $insStmt->insert_id;
        $insStmt->close();
    }
    echo json_encode(['success' => true, 'entryId' => $entryId]);
    exit;
}
if ($action === 'delete_entry') {
    $entryId = (int) ($_POST['entry_id'] ?? 0);
    if ($entryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid entry.']);
        exit;
    }
    $ownStmt = $conn->prepare("SELECT entry_id FROM diary_entries WHERE entry_id = ? AND user_id = ?");
    $ownStmt->bind_param('ii', $entryId, $userId);
    $ownStmt->execute();
    $owned = $ownStmt->get_result()->fetch_assoc();
    $ownStmt->close();
    if (!$owned) {
        echo json_encode(['success' => false, 'message' => 'Entry not found.']);
        exit;
    }
    $photoStmt = $conn->prepare("SELECT image FROM diary_photos WHERE entry_id = ?");
    $photoStmt->bind_param('i', $entryId);
    $photoStmt->execute();
    $photos = $photoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $photoStmt->close();
    foreach ($photos as $p) {
        $path = __DIR__ . '/../assets/uploads/diary/' . basename($p['image']);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $delPhotos = $conn->prepare("DELETE FROM diary_photos WHERE entry_id = ?");
    $delPhotos->bind_param('i', $entryId);
    $delPhotos->execute();
    $delPhotos->close();
    $delEntry = $conn->prepare("DELETE FROM diary_entries WHERE entry_id = ? AND user_id = ?");
    $delEntry->bind_param('ii', $entryId, $userId);
    $delEntry->execute();
    $delEntry->close();
    echo json_encode(['success' => true]);
    exit;
}
if ($action === 'delete_photo') {
    $photoId = (int) ($_POST['photo_id'] ?? 0);
    if ($photoId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid photo.']);
        exit;
    }
    $ownStmt = $conn->prepare(
        "SELECT p.photo_id, p.image, p.entry_id FROM diary_photos p
         JOIN diary_entries e ON e.entry_id = p.entry_id
         WHERE p.photo_id = ? AND e.user_id = ?"
    );
    $ownStmt->bind_param('ii', $photoId, $userId);
    $ownStmt->execute();
    $photo = $ownStmt->get_result()->fetch_assoc();
    $ownStmt->close();
    if (!$photo) {
        echo json_encode(['success' => false, 'message' => 'Photo not found.']);
        exit;
    }
    $path = __DIR__ . '/../assets/uploads/diary/' . basename($photo['image']);
    if (is_file($path)) {
        @unlink($path);
    }
    $delStmt = $conn->prepare("DELETE FROM diary_photos WHERE photo_id = ?");
    $delStmt->bind_param('i', $photoId);
    $delStmt->execute();
    $delStmt->close();
    echo json_encode(['success' => true, 'entryId' => (int) $photo['entry_id']]);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Unknown action.']);

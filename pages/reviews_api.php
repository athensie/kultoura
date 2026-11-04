<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/reviews.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function kt_review_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!kt_reviews_ensure_schema($conn)) kt_review_response(500, ['success' => false, 'message' => 'Reviews are temporarily unavailable.']);
$type = strtolower(trim((string) ($_REQUEST['item_type'] ?? '')));
$itemId = (int) ($_REQUEST['item_id'] ?? 0);
if (!in_array($type, kt_reviews_allowed_types(), true)) {
    kt_review_response(400, ['success' => false, 'message' => 'Choose a valid item to review.']);
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['summary_ids'])) {
    $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_GET['summary_ids'])), static fn($id) => $id > 0)));
    $ids = array_slice($ids, 0, 100);
    if (!$ids) kt_review_response(200, ['success' => true, 'summaries' => []]);
    $idList = implode(',', $ids);
    $summaryStmt = $conn->prepare("SELECT item_id, COUNT(*) review_count, ROUND(AVG(rating), 1) average_rating FROM item_reviews WHERE item_type = ? AND item_id IN ($idList) GROUP BY item_id");
    if (!$summaryStmt) kt_review_response(500, ['success' => false, 'message' => 'Ratings are temporarily unavailable.']);
    $summaryStmt->bind_param('s', $type);
    $summaryStmt->execute();
    $summaries = [];
    foreach ($summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $summaries[(int) $row['item_id']] = ['count' => (int) $row['review_count'], 'average' => (float) $row['average_rating']];
    $summaryStmt->close();
    kt_review_response(200, ['success' => true, 'summaries' => $summaries]);
}
if ($itemId < 1) kt_review_response(400, ['success' => false, 'message' => 'Choose a valid item to review.']);
$destinationCategories = ['nature', 'resort', 'industry', 'accommodation', 'church', 'service'];
$table = in_array($type, $destinationCategories, true) ? 'destination' : match ($type) {
    'restaurant' => 'restaurants', 'product' => 'products', 'fiesta' => 'fiestas', default => '',
};
$idColumn = match ($table) {
    'destination' => 'destination_id', 'restaurants' => 'restaurant_id', 'products' => 'product_id', 'fiestas' => 'fiesta_id', default => '',
};
$itemStmt = $conn->prepare("SELECT 1 FROM `$table` WHERE `$idColumn` = ? AND status = 'active'" . ($table === 'destination' ? ' AND category = ?' : '') . ' LIMIT 1');
if (!$itemStmt) kt_review_response(500, ['success' => false, 'message' => 'Reviews are temporarily unavailable.']);
if ($table === 'destination') $itemStmt->bind_param('is', $itemId, $type);
else $itemStmt->bind_param('i', $itemId);
$itemStmt->execute();
$itemExists = (bool) $itemStmt->get_result()->fetch_row();
$itemStmt->close();
if (!$itemExists) kt_review_response(404, ['success' => false, 'message' => 'This item is no longer available.']);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $starFilter = (int) ($_GET['stars'] ?? 0);
    if ($starFilter < 0 || $starFilter > 5) $starFilter = 0;
    $summaryStmt = $conn->prepare('SELECT COUNT(*) review_count, COALESCE(AVG(rating), 0) average_rating FROM item_reviews WHERE item_type = ? AND item_id = ?');
    $summaryStmt->bind_param('si', $type, $itemId);
    $summaryStmt->execute();
    $summary = $summaryStmt->get_result()->fetch_assoc();
    $summaryStmt->close();
    $counts = array_fill(1, 5, 0);
    $countStmt = $conn->prepare('SELECT rating, COUNT(*) total FROM item_reviews WHERE item_type = ? AND item_id = ? GROUP BY rating');
    $countStmt->bind_param('si', $type, $itemId);
    $countStmt->execute();
    foreach ($countStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $counts[(int) $row['rating']] = (int) $row['total'];
    $countStmt->close();
    if ($starFilter) {
        $reviewStmt = $conn->prepare('SELECT display_name, rating, review_text AS comment, created_at FROM item_reviews WHERE item_type = ? AND item_id = ? AND rating = ? ORDER BY created_at DESC LIMIT 100');
        $reviewStmt->bind_param('sii', $type, $itemId, $starFilter);
    } else {
        $reviewStmt = $conn->prepare('SELECT display_name, rating, review_text AS comment, created_at FROM item_reviews WHERE item_type = ? AND item_id = ? ORDER BY created_at DESC LIMIT 100');
        $reviewStmt->bind_param('si', $type, $itemId);
    }
    $reviewStmt->execute();
    $reviews = $reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $reviewStmt->close();
    kt_review_response(200, ['success' => true, 'count' => (int) ($summary['review_count'] ?? 0), 'average' => round((float) ($summary['average_rating'] ?? 0), 1), 'counts' => $counts, 'reviews' => $reviews]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') kt_review_response(405, ['success' => false, 'message' => 'Method not allowed.']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1 || in_array(strtolower((string) ($_SESSION['role'] ?? '')), ['admin', 'super admin'], true)) {
    kt_review_response(401, ['success' => false, 'needsLogin' => true, 'message' => 'Sign in with a visitor account to leave a review.']);
}
$rating = (int) ($_POST['rating'] ?? 0);
$comment = trim((string) ($_POST['comment'] ?? ''));
if ($rating < 1 || $rating > 5 || mb_strlen($comment) < 3 || mb_strlen($comment) > 1500) {
    kt_review_response(422, ['success' => false, 'message' => 'Choose 1–5 stars and write a comment between 3 and 1,500 characters.']);
}
$displayName = trim((string) ($_SESSION['username'] ?? $_SESSION['user_name'] ?? ''));
if ($displayName === '') $displayName = 'KulToura visitor';
$displayName = mb_substr($displayName, 0, 100);
$existsStmt = $conn->prepare('SELECT review_id, updated_at FROM item_reviews WHERE user_id = ? AND item_type = ? AND item_id = ? LIMIT 1');
$existsStmt->bind_param('isi', $userId, $type, $itemId);
$existsStmt->execute();
$existing = $existsStmt->get_result()->fetch_assoc();
$existsStmt->close();
if ($existing && strtotime((string) $existing['updated_at']) > time() - 30) {
    kt_review_response(429, ['success' => false, 'message' => 'Please wait a moment before editing this review again.']);
}
if (!$existing) {
    $recentStmt = $conn->prepare('SELECT created_at FROM item_reviews WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $recentStmt->bind_param('i', $userId);
    $recentStmt->execute();
    $recent = $recentStmt->get_result()->fetch_assoc();
    $recentStmt->close();
    if ($recent && strtotime((string) $recent['created_at']) > time() - 30) {
        kt_review_response(429, ['success' => false, 'message' => 'Please wait before submitting another review.']);
    }
}
$saveStmt = $conn->prepare('INSERT INTO item_reviews (user_id, item_type, item_id, display_name, rating, review_text) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), rating = VALUES(rating), review_text = VALUES(review_text), updated_at = CURRENT_TIMESTAMP');
$saveStmt->bind_param('isisis', $userId, $type, $itemId, $displayName, $rating, $comment);
if (!$saveStmt->execute()) {
    $saveStmt->close();
    kt_review_response(500, ['success' => false, 'message' => 'Could not save your review. Please try again.']);
}
$saveStmt->close();
kt_review_response(200, ['success' => true, 'message' => $existing ? 'Your review was updated.' : 'Your review was submitted.']);

<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
require_once __DIR__ . '/../config/promotions.php';
header('Content-Type: application/json');

$type = (string) ($_POST['item_type'] ?? '');
$id = (int) ($_POST['item_id'] ?? 0);
$event = (string) ($_POST['event'] ?? '');
$meta = kt_promotion_types()[$type] ?? null;
if (!$meta || $id <= 0 || !in_array($event, ['impression', 'click'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$check = $conn->prepare(
    "SELECT 1
     FROM promoted_content p
     INNER JOIN {$meta['table']} t ON t.{$meta['id']} = p.item_id
     WHERE p.item_type = ? AND p.item_id = ? AND t.status = 'active' AND {$meta['category']}
     LIMIT 1"
);
$check->bind_param('si', $type, $id);
$check->execute();
$isPromoted = (bool) $check->get_result()->fetch_row();
$check->close();
if (!$isPromoted) {
    http_response_code(404);
    echo json_encode(['success' => false]);
    exit;
}

$column = $event === 'impression' ? 'impressions' : 'clicks';
$sql = "INSERT INTO promoted_content_analytics (item_type, item_id, {$column}) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE {$column} = {$column} + 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('si', $type, $id);
$stmt->execute();
$stmt->close();

if ($event === 'click') {
    analytics_track_item($conn, $type, $id);
    $interactionType = array_key_exists($type, analytics_type_group_map()) ? 'destination' : $type;
    if (isset($_SESSION['user_id'])) {
        analytics_track_user_item($conn, (int) $_SESSION['user_id'], $interactionType, $id);
    }
}

echo json_encode(['success' => true]);

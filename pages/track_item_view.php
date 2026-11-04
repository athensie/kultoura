<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/analytics.php';
header('Content-Type: application/json');
$itemType = $_POST['item_type'] ?? '';
$itemId   = (int) ($_POST['item_id'] ?? 0);
$viewType = $itemType;
$interactionType = array_key_exists($itemType, analytics_type_group_map()) ? 'destination' : $itemType;
if ($itemType === 'destination' && $itemId > 0) {
    $categoryStmt = $conn->prepare("SELECT category FROM destination WHERE destination_id = ? AND status = 'active' LIMIT 1");
    if ($categoryStmt) {
        $categoryStmt->bind_param('i', $itemId);
        $categoryStmt->execute();
        $viewType = (string) ($categoryStmt->get_result()->fetch_assoc()['category'] ?? '');
        $categoryStmt->close();
    }
}
if ($itemId > 0 && array_key_exists($itemType, analytics_item_types()) && array_key_exists($viewType, analytics_item_types())) {
    // Interactions use the same canonical key as For You and favorites for
    // destination rows; the category is read from the destination itself.
    $interactionType = array_key_exists($viewType, analytics_type_group_map()) ? 'destination' : $viewType;
    analytics_track_item($conn, $viewType, $itemId);
    if (isset($_SESSION['user_id'])) {
        analytics_track_user_item($conn, (int) $_SESSION['user_id'], $interactionType, $itemId);
    }
    if (!isset($_SESSION['itemHistory'])) {
        $_SESSION['itemHistory'] = [];
    }
    $_SESSION['itemHistory'][] = ['type' => $interactionType, 'id' => $itemId];
    $_SESSION['itemHistory'] = array_slice($_SESSION['itemHistory'], -50);
}
echo json_encode(['success' => true]);

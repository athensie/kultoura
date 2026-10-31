<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
include '../config/analytics.php';
header('Content-Type: application/json');
$itemType = $_POST['item_type'] ?? '';
$itemId   = (int) ($_POST['item_id'] ?? 0);
analytics_track_item($conn, $itemType, $itemId);
if ($itemId > 0 && array_key_exists($itemType, analytics_item_types())) {
    if (!isset($_SESSION['itemHistory'])) {
        $_SESSION['itemHistory'] = [];
    }
    $_SESSION['itemHistory'][] = ['type' => $itemType, 'id' => $itemId];
    $_SESSION['itemHistory'] = array_slice($_SESSION['itemHistory'], -50);
}
echo json_encode(['success' => true]);

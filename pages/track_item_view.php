<?php
/*
 |--------------------------------------------------------------------
 | Public "View Details" tracking endpoint
 |--------------------------------------------------------------------
 | Called via fetch()/sendBeacon() from every listing page's "View
 | Details" action (see ktTrackItemView() in each page's inline script).
 | No auth required — this only ever writes a view-count row, never
 | reads or exposes anything. item_type is checked against the fixed
 | allowlist in analytics_item_types() before anything is written.
 */
session_start();
include '../config/dbmain.php';
include '../config/analytics.php';

header('Content-Type: application/json');

$itemType = $_POST['item_type'] ?? '';
$itemId   = (int) ($_POST['item_id'] ?? 0);

analytics_track_item($conn, $itemType, $itemId);

// Also remember the specific item in-session (not just the aggregate
// count above) so foryou.php can recommend based on what this visitor
// actually opened, not just which category pages they loaded.
if ($itemId > 0 && array_key_exists($itemType, analytics_item_types())) {
    if (!isset($_SESSION['itemHistory'])) {
        $_SESSION['itemHistory'] = [];
    }
    $_SESSION['itemHistory'][] = ['type' => $itemType, 'id' => $itemId];
    $_SESSION['itemHistory'] = array_slice($_SESSION['itemHistory'], -50);
}

echo json_encode(['success' => true]);

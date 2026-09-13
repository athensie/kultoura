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

echo json_encode(['success' => true]);

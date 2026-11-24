<?php
/*
 * Records how long the visitor actually looked at a page: visible time measured in the browser,
 * sent when the page is hidden or left. Updates exactly the page view named by the kt_pv cookie
 * (set when that page view was recorded), keeping the larger value if a page reports twice.
 */
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/analytics.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}
$originHost = !empty($_SERVER['HTTP_ORIGIN']) ? parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) : null;
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$requestHostName = strtolower((string) parse_url('//' . $requestHost, PHP_URL_HOST));
if ($originHost && strtolower((string) $originHost) !== $requestHostName) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
$visitId = (string) ($_COOKIE['kt_visit'] ?? '');
$viewId = (int) ($_COOKIE['kt_pv'] ?? 0);
$seconds = (int) ($_POST['seconds'] ?? 0);
if (!kt_analytics_should_record() || !preg_match('/^[a-f0-9]{32}$/', $visitId) || $viewId < 1 || $seconds < 1) {
    echo json_encode(['success' => true, 'ignored' => true]);
    exit;
}
$seconds = min($seconds, 1800);   // cap at 30 minutes: longer means the tab was left open
analytics_ensure_visit_columns($conn);
$stmt = $conn->prepare('UPDATE page_views SET engaged_seconds = GREATEST(COALESCE(engaged_seconds, 0), ?) WHERE view_id = ? AND visit_id = ? AND viewed_at >= (NOW() - INTERVAL 2 HOUR)');
$saved = false;
if ($stmt) {
    $stmt->bind_param('iis', $seconds, $viewId, $visitId);
    $saved = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
}
echo json_encode(['success' => $saved]);

<?php
require_once __DIR__ . '/../config/session_boot.php';
require_once __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/activity.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}
$originHost = !empty($_SERVER['HTTP_ORIGIN']) ? parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST) : null;
$refererHost = !empty($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : null;
$requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
if (($originHost && strtolower((string) $originHost) !== $requestHost) || ($refererHost && strtolower((string) $refererHost) !== $requestHost)) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
$agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
if (preg_match('/bot|crawler|spider|slurp|headless|lighthouse/i', $agent)) {
    echo json_encode(['success' => true, 'ignored' => true]);
    exit;
}
$now = time();
if (!isset($_SESSION['activity_rate']) || $now - (int) $_SESSION['activity_rate']['start'] >= 60) {
    $_SESSION['activity_rate'] = ['start' => $now, 'count' => 0];
}
if (++$_SESSION['activity_rate']['count'] > 80) {
    http_response_code(429);
    echo json_encode(['success' => false]);
    exit;
}
$type = (string) ($_POST['activity_type'] ?? '');
$allowedTypes = ['click', 'generate', 'download', 'recommendation', 'map_interaction', 'language', 'comment', 'other'];
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}
$categories = kt_activity_categories();
$category = (string) ($_POST['category'] ?? '');
if (!isset($categories[$category])) $category = null;
$itemType = (string) ($_POST['item_type'] ?? '');
if (!isset($categories[$itemType])) $itemType = null;
$itemId = (int) ($_POST['item_id'] ?? 0);
if ($itemId <= 0 || $itemType === null) $itemId = null;
$page = substr((string) ($_POST['page'] ?? 'other'), 0, 80);
$element = substr((string) ($_POST['element'] ?? ''), 0, 120);
$metadata = [];
// "Intent to visit" actions (directions, call, message, email, share, social, website).
$intent = strtolower((string) ($_POST['intent'] ?? ''));
if (in_array($intent, ['directions', 'call', 'message', 'email', 'share', 'social', 'website'], true)) {
    $metadata['intent'] = $intent;
    if ($intent === 'directions') $type = 'map_interaction';
}
if ($type === 'click' && $itemType === null) $category = kt_activity_category_for_page($page) ?? $category;
$saved = kt_activity_record($conn, $type, $category, $page, $element, $itemType, $itemId, $metadata);
echo json_encode(['success' => $saved]);

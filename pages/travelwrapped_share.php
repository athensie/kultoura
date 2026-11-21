<?php
require_once __DIR__ . '/../config/session_boot.php';
include __DIR__ . '/../config/dbmain.php';
require_once __DIR__ . '/../config/travel_wrapped_share.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function kt_wrapped_share_reply(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    kt_wrapped_share_reply(405, ['error' => 'Use POST to create a share link.']);
}
if (empty($_SESSION['user_id'])) {
    kt_wrapped_share_reply(401, ['error' => 'Sign in to create a Travel Wrapped link.']);
}
$originHeader = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$originParts = $originHeader !== '' ? parse_url($originHeader) : false;
$requestHost = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
$requestPort = parse_url('http://' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_PORT);
if (is_array($originParts) && (strcasecmp((string) ($originParts['host'] ?? ''), (string) $requestHost) !== 0
    || (int) ($originParts['port'] ?? 0) !== (int) ($requestPort ?? 0))) {
    kt_wrapped_share_reply(403, ['error' => 'Invalid request origin.']);
}
$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    kt_wrapped_share_reply(415, ['error' => 'Expected a JSON Travel Wrapped snapshot.']);
}
$requestBody = file_get_contents('php://input');
if ($requestBody === false || strlen($requestBody) > 2 * 1024 * 1024) {
    kt_wrapped_share_reply(413, ['error' => 'This Wrapped snapshot is too large to share.']);
}
$request = json_decode($requestBody, true);
$story = $request['story'] ?? null;
if (!is_array($story)
    || empty($story['config']) || !is_array($story['config'])
    || empty($story['places']) || !is_array($story['places'])
    || empty($story['visits']) || !is_array($story['visits'])
    || !isset($story['media']) || !is_array($story['media'])
    || empty($story['edit']) || !is_array($story['edit'])
    || empty($story['copy']) || !is_array($story['copy'])) {
    kt_wrapped_share_reply(400, ['error' => 'The Travel Wrapped snapshot is incomplete.']);
}
$payload = json_encode($story, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($payload === false || strlen($payload) > 2 * 1024 * 1024) {
    kt_wrapped_share_reply(413, ['error' => 'This Wrapped snapshot is too large to share.']);
}

try {
    kt_wrapped_share_ensure_table($conn);
    $userId = (int) $_SESSION['user_id'];
    $token = bin2hex(random_bytes(32));
    $shareHash = hash('sha256', $token);
    $insertStmt = $conn->prepare('INSERT INTO travel_wrapped_shares (user_id,share_hash,payload) VALUES (?,?,?)');
    $insertStmt->bind_param('iss', $userId, $shareHash, $payload);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        kt_wrapped_share_reply(500, ['error' => 'Could not create a Travel Wrapped link.']);
    }
    $insertStmt->close();

    $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/pages'))), '/');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $url = ($isHttps ? 'https://' : 'http://')
        . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $scriptDir . '/travelwrapped.php?share=' . rawurlencode($token);
    kt_wrapped_share_reply(200, ['url' => $url]);
} catch (Throwable $error) {
    error_log('Travel Wrapped share link error: ' . $error->getMessage());
    kt_wrapped_share_reply(500, ['error' => 'Could not create a Travel Wrapped link.']);
}

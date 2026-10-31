<?php
require_once __DIR__ . '/../config/dbmain.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
$username = trim($_GET['username'] ?? '');
if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
    echo json_encode(['exists' => null]);
    exit;
}
$exists = false;
foreach (['admins' => 'username', 'users' => 'username'] as $table => $col) {
    $stmt = $conn->prepare("SELECT 1 FROM $table WHERE $col = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        $exists = true;
    }
    $stmt->close();
    if ($exists) break;
}
echo json_encode(['exists' => $exists]);

<?php
require_once __DIR__ . '/../config/session_boot.php';
include '../config/dbmain.php';
require_once __DIR__ . '/../config/search.php';
header('Content-Type: application/json');
$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}
$results = kt_search_all($conn, $query, 4);
echo json_encode(['results' => array_slice($results, 0, 8)]);

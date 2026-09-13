<?php
session_start();
require_once '../../config/dbmain.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to save favorites.']);
    exit;
}

$userId    = $_SESSION['user_id'];
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

try {
    // NOTE: expects a table favorites(id, user_id, product_id, created_at)
    // with a UNIQUE(user_id, product_id) constraint recommended.
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $del = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $del->execute([$existing['id']]);
        echo json_encode(['success' => true, 'favorited' => false]);
    } else {
        $ins = $pdo->prepare("INSERT INTO favorites (user_id, product_id, created_at) VALUES (?, ?, NOW())");
        $ins->execute([$userId, $productId]);
        echo json_encode(['success' => true, 'favorited' => true]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
}
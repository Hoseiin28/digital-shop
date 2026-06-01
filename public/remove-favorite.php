<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا ابتدا وارد شوید']);
    exit();
}

$product_id = $_POST['product_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$product_id || !is_numeric($product_id)) {
    echo json_encode(['success' => false, 'message' => 'شناسه محصول نامعتبر است']);
    exit();
}


$stmt = $conn->prepare("DELETE FROM Favorites WHERE user_id = ? AND product_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'خطا در آماده‌سازی پرس و جو']);
    exit();
}

$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'محصول در لیست علاقه‌مندی‌ها یافت نشد']);
}

$stmt->close();
$conn->close();
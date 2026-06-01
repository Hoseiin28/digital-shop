<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید.']);
    exit;
}

if (!isset($_POST['product_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'پارامترهای لازم ارسال نشده است.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = intval($_POST['product_id']);
$action = $_POST['action'];

if ($action === 'add') {
    $stmt = $conn->prepare("INSERT INTO Favorites (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $product_id);
} else {
    $stmt = $conn->prepare("DELETE FROM Favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در اجرای عملیات.']);
}

$stmt->close();
$conn->close();
?>
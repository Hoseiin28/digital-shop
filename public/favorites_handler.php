<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'لطفاً ابتدا وارد حساب کاربری خود شوید.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$action = $_POST['action'];

if ($action === 'add') {
    $check = $conn->prepare("SELECT id FROM Favorites WHERE user_id = ? AND product_id = ?");
    $check->bind_param("ii", $user_id, $product_id);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO Favorites (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'محصول به مورد علاقه‌ها اضافه شد.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'خطا در اضافه کردن محصول.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'این محصول قبلاً به مورد علاقه‌ها اضافه شده است.']);
    }
} elseif ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM Favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'محصول از مورد علاقه‌ها حذف شد.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'خطا در حذف محصول.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'عملیات نامعتبر.']);
}
?>
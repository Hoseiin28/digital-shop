<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا وارد حساب کاربری خود شوید.']);
    exit;
}


$data = json_decode(file_get_contents('php://input'), true);
$product_id = $data['product_id'];
$user_id = $_SESSION['user_id'];

$check_sql = "SELECT * FROM Favorites WHERE user_id = ? AND product_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'این محصول قبلاً به لیست مورد علاقه شما اضافه شده است.']);
} else {
    $insert_sql = "INSERT INTO Favorites (user_id, product_id) VALUES (?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ii", $user_id, $product_id);
    
    if ($insert_stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در افزودن محصول به لیست مورد علاقه.']);
    }
}

$stmt->close();
$insert_stmt->close();
$conn->close();
?>
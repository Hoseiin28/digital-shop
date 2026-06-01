<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = 'ابتدا وارد حساب کاربری خود شوید.';
        header("Location: product-details.php?id=$product_id");
        exit;
    }

    if ($product_id <= 0 || $rating < 1 || $rating > 5 || empty($comment)) {
        $_SESSION['error_message'] = 'اطلاعات معتبر نیستند.';
        header("Location: product-details.php?id=$product_id");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO Reviews (user_id, product_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiis", $user_id, $product_id, $rating, $comment);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'نظر شما با موفقیت ثبت شد.';
    } else {
        $_SESSION['error_message'] = 'خطا در ثبت نظر.';
    }

    $stmt->close();

    header("Location: product-details.php?id=$product_id");
    exit;
} else {
    $_SESSION['error_message'] = 'درخواست نامعتبر است.';
    header("Location: index.php"); 
    exit;
}

$conn->close();
?>
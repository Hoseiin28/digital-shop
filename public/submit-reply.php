<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا ابتدا وارد شوید']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
    exit;
}


$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if ($product_id <= 0 || $parent_id <= 0 || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص']);
    exit;
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare("SELECT 1 FROM Products WHERE id = ?");
    $stmt->execute([$product_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'محصول یافت نشد']);
        exit;
    }

    $stmt = $conn->prepare("SELECT 1 FROM Reviews WHERE id = ? AND product_id = ?");
    $stmt->execute([$parent_id, $product_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'نظر اصلی یافت نشد']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO Reviews (user_id, product_id, parent_id, comment, created_at) 
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $product_id, $parent_id, $comment]);

    echo json_encode(['success' => true, 'message' => 'پاسخ با موفقیت ثبت شد']);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطای پایگاه داده: ' . $e->getMessage()]);
}
?>
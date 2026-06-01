<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_POST['product_id'])) {
    $response['message'] = 'شناسه محصول ارسال نشده است';
    echo json_encode($response);
    exit;
}

$product_id = (int)$_POST['product_id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$session_id = session_id();

try {
    $stmt = $pdo->prepare("SELECT id FROM Products WHERE id = ?");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        $response['message'] = 'محصول مورد نظر یافت نشد';
        echo json_encode($response);
        exit;
    }

    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM ProductComparison WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM ProductComparison WHERE session_id = ? AND product_id = ?");
        $stmt->execute([$session_id, $product_id]);
    }

    if ($stmt->fetch()) {
        $response['message'] = 'این محصول قبلاً به لیست مقایسه اضافه شده است';
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO ProductComparison (user_id, session_id, product_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user_id, $session_id, $product_id]);

    if ($user_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ProductComparison WHERE user_id = ?");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ProductComparison WHERE session_id = ?");
        $stmt->execute([$session_id]);
    }
    $compareCount = $stmt->fetchColumn();

    $response['success'] = true;
    $response['message'] = 'محصول با موفقیت به لیست مقایسه اضافه شد';
    $response['compareCount'] = $compareCount;
} catch (PDOException $e) {
    $response['message'] = 'خطا در پایگاه داده: ' . $e->getMessage();
}

echo json_encode($response);
?>
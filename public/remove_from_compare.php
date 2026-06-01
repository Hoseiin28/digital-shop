<?php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => '', 'count' => 0];

if (isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();

    try {
        $sql = "DELETE FROM ProductComparison WHERE product_id = ? AND " .
            ($user_id ? "user_id = ?" : "session_id = ?");
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$product_id, $user_id ?: $session_id]);

        $sql = "SELECT COUNT(*) as count FROM ProductComparison WHERE " .
            ($user_id ? "user_id = ?" : "session_id = ?");
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id ?: $session_id]);
        $count = $stmt->fetchColumn();

        $response['success'] = true;
        $response['count'] = $count;
    } catch (PDOException $e) {
        $response['message'] = 'خطا در حذف محصول از مقایسه';
        error_log("Error removing from compare: " . $e->getMessage());
    }
} else {
    $response['message'] = 'شناسه محصول ارسال نشده است';
}

header('Content-Type: application/json');
echo json_encode($response);

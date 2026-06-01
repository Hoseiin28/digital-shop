<?php
session_start();
require_once 'config.php';

$response = ['success' => false, 'message' => ''];

try {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $session_id = session_id();
    
    $sql = "DELETE FROM ProductComparison WHERE " . ($user_id ? "user_id = ?" : "session_id = ?");
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id ?: $session_id]);
    
    $response['success'] = true;
    $response['message'] = 'تمام محصولات با موفقیت حذف شدند';
} catch (PDOException $e) {
    $response['message'] = 'خطا در حذف محصولات: ' . $e->getMessage();
    error_log("Error clearing compare list: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);
?>
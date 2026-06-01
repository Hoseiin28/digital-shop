<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$session_id = session_id();

try {
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_url 
            FROM ProductComparison pc
            JOIN Products p ON pc.product_id = p.id
            WHERE pc.user_id = ?
            ORDER BY pc.added_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_url 
            FROM ProductComparison pc
            JOIN Products p ON pc.product_id = p.id
            WHERE pc.session_id = ?
            ORDER BY pc.added_at DESC
        ");
        $stmt->execute([$session_id]);
    }

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($products);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => $count
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت لیست مقایسه: ' . $e->getMessage()
    ]);
}
?>
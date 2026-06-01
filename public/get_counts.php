<?php
require_once 'config.php';

session_start();

header('Content-Type: application/json');

$response = [
    'cartCount' => 0,
    'compareCount' => 0
];

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT SUM(od.quantity) as total 
            FROM Orders o 
            JOIN OrderDetails od ON o.id = od.order_id 
            WHERE o.user_id = ? 
            AND o.status = 'pending' 
            AND o.payment_status = 'unpaid'
            AND o.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['cartCount'] = (int)($result['total'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error fetching cart count: " . $e->getMessage());
    }

    try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ProductComparison WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        if (!isset($_SESSION['compare_session_id'])) {
            $_SESSION['compare_session_id'] = session_id();
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ProductComparison WHERE session_id = ?");
        $stmt->execute([$_SESSION['compare_session_id']]);
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['compareCount'] = (int)($result['count'] ?? 0);
} catch (PDOException $e) {
    error_log("Error fetching compare count: " . $e->getMessage());
}

}

echo json_encode($response);
?>
<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا وارد حساب کاربری خود شوید']);
    exit;
}

if (!isset($_POST['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه آیتم ارسال نشده است']);
    exit;
}

$itemId = $_POST['item_id'];

try {
    $stmt = $pdo->prepare("
        SELECT od.id 
        FROM OrderDetails od
        JOIN Orders o ON od.order_id = o.id
        WHERE od.id = ? 
        AND o.user_id = ?
        AND o.status = 'pending'
        AND o.payment_status = 'unpaid'
    ");
    $stmt->execute([$itemId, $_SESSION['user_id']]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'آیتم مورد نظر یافت نشد یا شما مجاز به حذف آن نیستید']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM OrderDetails WHERE id = ?");
    $stmt->execute([$itemId]);

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM OrderDetails 
        WHERE order_id = (
            SELECT order_id FROM OrderDetails WHERE id = ?
        )
    ");
    $stmt->execute([$itemId]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        $stmt = $pdo->prepare("
            DELETE FROM Orders 
            WHERE id = (
                SELECT order_id FROM OrderDetails WHERE id = ? LIMIT 1
            )
        ");
        $stmt->execute([$itemId]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Error removing item from cart: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در حذف محصول از سبد خرید']);
}
?>
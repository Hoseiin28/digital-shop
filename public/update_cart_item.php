<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا وارد حساب کاربری خود شوید']);
    exit;
}

if (!isset($_POST['item_id']) || !isset($_POST['change'])) {
    echo json_encode(['success' => false, 'message' => 'پارامترهای لازم ارسال نشده است']);
    exit;
}

$itemId = $_POST['item_id'];
$change = (int)$_POST['change'];

try {
    $stmt = $pdo->prepare("
        SELECT o.id 
        FROM Orders o 
        WHERE o.user_id = ? 
        AND o.status = 'pending' 
        AND o.payment_status = 'unpaid'
        AND o.created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        ORDER BY o.created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'سبد خرید شما خالی است']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT od.id, od.quantity, p.stock 
        FROM OrderDetails od
        JOIN Products p ON od.product_id = p.id
        WHERE od.id = ? AND od.order_id = ?
    ");
    $stmt->execute([$itemId, $order['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'آیتم مورد نظر در سبد خرید یافت نشد']);
        exit;
    }

    $newQuantity = $item['quantity'] + $change;

    if ($newQuantity > $item['stock']) {
        echo json_encode(['success' => false, 'message' => 'تعداد درخواستی بیشتر از موجودی انبار است']);
        exit;
    }

    if ($newQuantity < 1) {
        $stmt = $pdo->prepare("DELETE FROM OrderDetails WHERE id = ?");
        $stmt->execute([$itemId]);
    } else {
        $stmt = $pdo->prepare("UPDATE OrderDetails SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $itemId]);
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Error updating cart item: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی سبد خرید']);
}
?>
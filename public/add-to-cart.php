<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفاً ابتدا وارد شوید']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر']);
    exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
$quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'محصول نامعتبر']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, price, stock FROM Products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'محصول یافت نشد']);
        exit;
    }
    

    $stmt = $pdo->prepare("SELECT id FROM Orders WHERE user_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        $stmt = $pdo->prepare("INSERT INTO Orders (user_id, total_price) VALUES (?, 0)");
        $stmt->execute([$_SESSION['user_id']]);
        $order_id = $pdo->lastInsertId();
    } else {
        $order_id = $order['id'];
    }

    $stmt = $pdo->prepare("SELECT id, quantity FROM OrderDetails WHERE order_id = ? AND product_id = ?");
    $stmt->execute([$order_id, $product_id]);
    $order_item = $stmt->fetch();

    if ($order_item) {
        $new_quantity = $order_item['quantity'] + $quantity;
        $subtotal = $new_quantity * $product['price'];
        
        $stmt = $pdo->prepare("UPDATE OrderDetails SET quantity = ?, subtotal = ? WHERE id = ?");
        $stmt->execute([$new_quantity, $subtotal, $order_item['id']]);
    } else {
        $subtotal = $quantity * $product['price'];
        $stmt = $pdo->prepare("INSERT INTO OrderDetails (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$order_id, $product_id, $quantity, $product['price'], $subtotal]);
    }


    $stmt = $pdo->prepare("SELECT SUM(subtotal) as total FROM OrderDetails WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $total = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("UPDATE Orders SET total_price = ? WHERE id = ?");
    $stmt->execute([$total, $order_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Cart Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور در پردازش درخواست']);
}
<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'لطفا وارد حساب کاربری خود شوید']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_price
        FROM Orders o 
        WHERE o.user_id = ? 
        AND o.status = 'pending' 
        AND o.payment_status = 'unpaid'
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo json_encode([
            'success' => true,
            'items' => [],
            'totals' => [
                'total' => 0,
                'discount' => 0,
                'final_price' => 0
            ]
        ]);
        exit;
    }

    $allItems = [];
    $total = 0;
    $discount = 0;
    $final_price = 0;

    foreach ($orders as $order) {
        $stmt = $pdo->prepare("
            SELECT 
                od.id,
                p.id as product_id,
                p.name,
                p.price,
                p.image_url as default_image,
                od.quantity,
                od.price as item_price,
                od.subtotal
            FROM OrderDetails od
            JOIN Products p ON od.product_id = p.id
            WHERE od.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {

            $stmt->execute([$item['product_id']]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT discount_type, discount_value 
                FROM Discounts 
                WHERE product_id = ? 
                AND (start_date IS NULL OR start_date <= NOW())
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1
            ");
            $stmt->execute([$item['product_id']]);
            $discountInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            $itemPrice = $item['item_price'];
            $itemQuantity = $item['quantity'];
            $itemSubtotal = $item['subtotal'];

            $discountedPrice = $itemPrice;
            $itemDiscount = 0;

            if ($discountInfo) {
                if ($discountInfo['discount_type'] == 'percentage') {
                    $itemDiscount = $itemPrice * ($discountInfo['discount_value'] / 100);
                    $discountedPrice = $itemPrice - $itemDiscount;
                } else {
                    $itemDiscount = $discountInfo['discount_value'];
                    $discountedPrice = $itemPrice - $itemDiscount;
                }

                $discountedPrice = max($discountedPrice, 0);
            }

            $imageUrl = $image['image_url'] ?? $item['default_image'] ?? '../image/default-product.jpg';

            $allItems[] = [
                'id' => $item['id'],
                'order_id' => $order['id'],
                'product_id' => $item['product_id'],
                'name' => $item['name'],
                'price' => $itemPrice,
                'discount_price' => $discountedPrice,
                'quantity' => $itemQuantity,
                'image_url' => $imageUrl,
                'has_discount' => ($discountInfo !== false),
                'discount_value' => $discountInfo['discount_value'] ?? 0,
                'discount_type' => $discountInfo['discount_type'] ?? null,
                'subtotal' => $itemSubtotal
            ];

            $total += ($itemPrice * $itemQuantity);
            $final_price += ($discountedPrice * $itemQuantity);
        }
    }

    $discount = $total - $final_price;

    echo json_encode([
        'success' => true,
        'items' => $allItems,
        'totals' => [
            'total' => $total,
            'discount' => $discount,
            'final_price' => $final_price
        ]
    ]);
} catch (PDOException $e) {
    error_log("Error fetching cart items: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات سبد خرید']);
}

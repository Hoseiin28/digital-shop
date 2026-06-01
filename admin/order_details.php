<?php
require_once 'config.php';

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    die('شناسه سفارش نامعتبر است');
}

$orderId = (int)$_GET['order_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            o.id, o.total_price, o.status, o.payment_status, o.created_at,
            u.name AS user_name, u.email, u.phone, u.address,
            p.amount AS payment_amount, p.payment_method, p.payment_date,
            IFNULL((
                SELECT SUM(od.price * od.quantity) - SUM(od.subtotal)
                FROM OrderDetails od
                WHERE od.order_id = o.id
            ), 0) AS total_discount
        FROM Orders o
        JOIN Users u ON o.user_id = u.id
        LEFT JOIN Payments p ON o.id = p.order_id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die('سفارش مورد نظر یافت نشد');
    }

    $stmt = $pdo->prepare("
        SELECT 
            od.id, od.product_id, od.quantity, od.price AS final_price,
            p.name AS product_name, p.price AS original_price,
            d.discount_type, d.discount_value,
            (SELECT image_url FROM ProductImages WHERE product_id = p.id LIMIT 1) AS image_url,
            (od.price * od.quantity) AS item_total,
            (p.price * od.quantity) - (od.price * od.quantity) AS item_discount
        FROM OrderDetails od
        JOIN Products p ON od.product_id = p.id
        LEFT JOIN Discounts d ON p.id = d.product_id
            AND (d.start_date IS NULL OR d.start_date <= NOW())
            AND (d.end_date IS NULL OR d.end_date >= NOW())
        WHERE od.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $originalTotal = array_reduce($orderItems, function ($carry, $item) {
        return $carry + ($item['original_price'] * $item['quantity']);
    }, 0);
} catch (PDOException $e) {
    die("خطا در دریافت اطلاعات سفارش: " . $e->getMessage());
}

$statusTranslations = [
    'pending' => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'shipped' => 'ارسال شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده'
];

$paymentTranslations = [
    'paid' => 'پرداخت شده',
    'unpaid' => 'پرداخت نشده'
];

$paymentMethodTranslations = [
    'online' => 'آنلاین',
    'cash_on_delivery' => 'پرداخت در محل'
];
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات سفارش #<?= $order['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style-order-details.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>

<body class="py-3">
    <div class="container">
        <div class="order-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">سفارش #<?= $order['id'] ?></h5>
                <span class="status-badge badge-<?= $order['status'] ?> text-white">
                    <?= $statusTranslations[$order['status']] ?>
                </span>
            </div>
            <hr class="my-2">
            <div class="row">
                <div class="col-6 col-md-3 mb-2">
                    <small class="text-muted d-block">تاریخ:</small>
                    <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <small class="text-muted d-block">پرداخت:</small>
                    <?= $paymentTranslations[$order['payment_status']] ?>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <small class="text-muted d-block">روش پرداخت:</small>
                    <?= $paymentMethodTranslations[$order['payment_method']] ?? 'روش پرداخت نامشخص' ?>
                </div>
                <?php if ($order['payment_status'] == 'paid' && !empty($order['payment_date'])): ?>
                    <div class="col-6 col-md-3 mb-2">
                        <small class="text-muted d-block">تاریخ پرداخت:</small>
                        <?= date('Y/m/d H:i', strtotime($order['payment_date'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="order-card">
            <h6 class="mb-3"><i class="fas fa-box-open text-primary me-2"></i>محصولات</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="70">محصول</th>
                            <th>قیمت</th>
                            <th width="80">تعداد</th>
                            <th width="100">جمع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($item['image_url'] ?? '../image/no-image.jpg') ?>"
                                            class="product-img me-2">
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <?php if ($item['discount_value'] > 0): ?>
                                                <span class="discount-badge">
                                                    <?= $item['discount_type'] == 'percentage' ?
                                                        $item['discount_value'] . '%' :
                                                        number_format($item['discount_value']) . ' تومان' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($item['discount_value'] > 0): ?>
                                        <div class="text-decoration-line-through text-muted small">
                                            <?= number_format($item['original_price']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div><?= number_format($item['final_price']) ?> تومان</div>
                                </td>
                                <td><?= $item['quantity'] ?></td>
                                <td class="fw-bold"><?= number_format($item['item_total']) ?> تومان</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="order-card">
                    <h6><i class="fas fa-user text-primary me-2"></i>مشتری</h6>
                    <hr class="my-2">
                    <div class="mb-2">
                        <small class="text-muted d-block">نام:</small>
                        <?= htmlspecialchars($order['user_name']) ?>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">ایمیل:</small>
                        <?= htmlspecialchars($order['email']) ?>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">تلفن:</small>
                        <?= htmlspecialchars($order['phone']) ?>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted d-block">آدرس:</small>
                        <?= htmlspecialchars($order['address']) ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="order-card">
                    <h6><i class="fas fa-receipt text-primary me-2"></i>خلاصه پرداخت</h6>
                    <hr class="my-2">
                    <div class="summary-item">
                        <small class="text-muted d-block">جمع کل:</small>
                        <div class="fw-bold"><?= number_format($originalTotal) ?> تومان</div>
                    </div>
                    <?php if ($order['total_discount'] > 0): ?>
                        <div class="summary-item">
                            <small class="text-muted d-block">تخفیف:</small>
                            <div class="fw-bold text-danger">-<?= number_format($order['total_discount']) ?> تومان</div>
                        </div>
                    <?php endif; ?>
                    <div class="summary-item bg-light">
                        <small class="text-muted d-block">قابل پرداخت:</small>
                        <div class="fw-bold text-success"><?= number_format($order['total_price']) ?> تومان</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
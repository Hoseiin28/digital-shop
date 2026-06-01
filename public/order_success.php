<?php
session_start();
require_once 'config.php';

if (!isset($_GET['order_id'])) {
    header("Location: ../index.php");
    exit();
}

$order_id = $_GET['order_id'];
$payment_method = $_GET['payment_method'] ?? 'online';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=order_success&order_id=" . $order_id);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(od.id) as items_count 
        FROM orders o
        LEFT JOIN orderdetails od ON o.id = od.order_id
        WHERE o.id = ? AND o.user_id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: /digital-shop/public/profile.php#orders");
        exit();
    }
} catch (PDOException $e) {
    die("خطا در ارتباط با پایگاه داده");
}

if ($payment_method === 'online') {
    $message = "پرداخت شما با موفقیت انجام شد. سفارش شما در حال پردازش است.";
    $icon = "bi-check-circle-fill text-success";
} else {
    $message = "سفارش شما با موفقیت ثبت شد. پرداخت در زمان تحویل انجام خواهد شد.";
    $icon = "bi-info-circle-fill text-primary";
}

$shop_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $shop_settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'shop_name' => 'فروشگاه دیجیتال',
        'logo_url' => ''
    ];
} catch (PDOException $e) {
    $shop_settings = [
        'shop_name' => 'فروشگاه دیجیتال',
        'logo_url' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تکمیل سفارش | <?= htmlspecialchars($shop_settings['shop_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-order_success.css">
</head>
<body>
    <div class="container">
        <div class="success-container">
            <?php if ($shop_settings['logo_url']): ?>
                <img src="<?= htmlspecialchars($shop_settings['logo_url']) ?>" alt="لوگوی فروشگاه" style="height: 60px; margin-bottom: 20px;">
            <?php endif; ?>
            
            <div class="success-icon">
                <i class="bi <?= $icon ?>"></i>
            </div>
            
            <h2 class="mb-3">سفارش شما ثبت شد</h2>
            <p class="lead"><?= $message ?></p>
            
            <div class="order-details">
                <h5>مشخصات سفارش</h5>
                <div class="row mt-3">
                    <div class="col-6 text-start">شماره سفارش:</div>
                    <div class="col-6 text-end fw-bold"><?= $order['id'] ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-6 text-start">تعداد آیتم‌ها:</div>
                    <div class="col-6 text-end fw-bold"><?= $order['items_count'] ?></div>
                </div>
                <div class="row mt-2">
                    <div class="col-6 text-start">مبلغ کل:</div>
                    <div class="col-6 text-end fw-bold"><?= number_format($order['total_price']) ?> تومان</div>
                </div>
                <div class="row mt-2">
                    <div class="col-6 text-start">روش پرداخت:</div>
                    <div class="col-6 text-end fw-bold">
                        <?= ($payment_method === 'online') ? 'آنلاین' : 'پرداخت در محل' ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-6 text-start">تاریخ ثبت:</div>
                    <div class="col-6 text-end fw-bold"><?= date('Y/m-d H:i', strtotime($order['created_at'])) ?></div>
                </div>
            </div>
            
            <div class="countdown">
                <p>این صفحه در <span id="countdown">10</span> ثانیه به صفحه اصلی منتقل می‌شود...</p>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="/digital-shop/public/profile.php#orders" class="btn btn-outline-primary">مشاهده سفارشات</a>
                <a href="/digital-shop/index.php" class="btn btn-primary">بازگشت به فروشگاه</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/script-order_success.js"></script>
</body>
</html>
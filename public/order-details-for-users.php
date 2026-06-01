<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

if (!isset($_GET['id'])) {
    header("Location: profile.php");
    exit();
}

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$orderStatusTranslations = [
    'pending' => 'در انتظار بررسی',
    'processing' => 'در حال پردازش',
    'shipped' => 'ارسال شده',
    'completed' => 'تکمیل شده',
    'cancelled' => 'لغو شده'
];

$paymentStatusTranslations = [
    'successful' => 'پرداخت موفق',
    'failed' => 'پرداخت ناموفق',
    'pending' => 'در انتظار پرداخت'
];

$orderStmt = $conn->prepare("
    SELECT o.*, p.status as payment_status, p.payment_method, p.payment_date 
    FROM Orders o
    LEFT JOIN Payments p ON o.id = p.order_id
    WHERE o.id = ? AND o.user_id = ?
");
$orderStmt->bind_param("ii", $order_id, $user_id);
$orderStmt->execute();
$orderResult = $orderStmt->get_result();

if ($orderResult->num_rows === 0) {
    header("Location: profile.php");
    exit();
}

$order = $orderResult->fetch_assoc();

$detailsStmt = $conn->prepare("
    SELECT 
        od.*, 
        p.name as product_name, 
        p.image_url, 
        p.description as product_description,
        p.price as original_price,
        IFNULL(d.discount_type, 'none') as discount_type,
        IFNULL(d.discount_value, 0) as discount_value,
        CASE 
            WHEN d.discount_type = 'percentage' THEN p.price * (1 - (d.discount_value / 100))
            WHEN d.discount_type = 'fixed' THEN p.price - d.discount_value
            ELSE p.price
        END as final_price
    FROM OrderDetails od
    JOIN Products p ON od.product_id = p.id
    LEFT JOIN Discounts d ON p.id = d.product_id 
        AND (d.start_date IS NULL OR d.start_date <= NOW()) 
        AND (d.end_date IS NULL OR d.end_date >= NOW())
    WHERE od.order_id = ?
");
$detailsStmt->bind_param("i", $order_id);
$detailsStmt->execute();
$orderDetails = $detailsStmt->get_result();

$totalDiscount = 0;
$subtotalWithoutDiscount = 0;
$orderItems = [];
while ($item = $orderDetails->fetch_assoc()) {
    $discountAmount = 0;
    
    if ($item['discount_type'] === 'percentage') {
        $discountAmount = ($item['original_price'] * $item['discount_value'] / 100) * $item['quantity'];
    } elseif ($item['discount_type'] === 'fixed') {
        $discountAmount = $item['discount_value'] * $item['quantity'];
    }
    
    $totalDiscount += $discountAmount;
    $subtotalWithoutDiscount += $item['original_price'] * $item['quantity'];
    $orderItems[] = $item;
}


$userStmt = $conn->prepare("SELECT * FROM Users WHERE id = ?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

$orderStmt->close();
$detailsStmt->close();
$userStmt->close();
$conn->close();

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جزئیات سفارش #<?php echo $order_id; ?> | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-order-details-for-users.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>
<body>
    <div class="order-container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0 fw-bold">جزئیات سفارش #<?php echo $order_id; ?></h2>
            <a href="profile.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-right me-1"></i> بازگشت به پروفایل
            </a>
        </div>
        
        <div class="order-header">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5 class="fw-bold"><i class="bi bi-calendar me-2"></i> تاریخ سفارش</h5>
                    <p class="mb-0"><?php echo date('Y/m/d H:i', strtotime($order['created_at'])); ?></p>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5 class="fw-bold"><i class="bi bi-truck me-2"></i> وضعیت سفارش</h5>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo $orderStatusTranslations[$order['status']]; ?>
                    </span>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold"><i class="bi bi-credit-card me-2"></i> وضعیت پرداخت</h5>
                    <span class="status-badge <?php echo $order['payment_status'] === 'successful' ? 'status-completed' : ($order['payment_status'] === 'failed' ? 'status-cancelled' : 'status-pending'); ?>">
                        <?php echo $paymentStatusTranslations[$order['payment_status']] ?? 'نامشخص'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-4">
                <div class="profile-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-list-check"></i> روند سفارش
                    </div>
                    <div class="card-body">
                        <div class="order-timeline">
                            <div class="timeline-step <?php echo in_array($order['status'], ['completed', 'shipped', 'processing', 'pending']) ? 'active' : ''; ?> <?php echo $order['status'] === 'pending' ? 'current' : ''; ?>">
                                <h6 class="fw-bold">سفارش ثبت شد</h6>
                                <p class="text-muted small mb-0"><?php echo date('Y/m/d H:i', strtotime($order['created_at'])); ?></p>
                            </div>
                            
                            <div class="timeline-step <?php echo in_array($order['status'], ['completed', 'shipped', 'processing']) ? 'active' : ''; ?> <?php echo $order['status'] === 'processing' ? 'current' : ''; ?>">
                                <h6 class="fw-bold">در حال پردازش</h6>
                                <?php if (in_array($order['status'], ['completed', 'shipped', 'processing'])): ?>
                                    <p class="text-muted small mb-0"><?php echo date('Y/m/d H:i', strtotime($order['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-step <?php echo in_array($order['status'], ['completed', 'shipped']) ? 'active' : ''; ?> <?php echo $order['status'] === 'shipped' ? 'current' : ''; ?>">
                                <h6 class="fw-bold">ارسال شد</h6>
                                <?php if (in_array($order['status'], ['completed', 'shipped'])): ?>
                                    <p class="text-muted small mb-0"><?php echo date('Y/m/d H:i', strtotime($order['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-step <?php echo $order['status'] === 'completed' ? 'active' : ''; ?> <?php echo $order['status'] === 'completed' ? 'current' : ''; ?>">
                                <h6 class="fw-bold">تکمیل شده</h6>
                                <?php if ($order['status'] === 'completed'): ?>
                                    <p class="text-muted small mb-0"><?php echo date('Y/m/d H:i', strtotime($order['updated_at'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
    
                <div class="profile-card">
                    <div class="card-header">
                        <i class="bi bi-truck"></i> اطلاعات ارسال
                    </div>
                    <div class="card-body">
                        <div class="info-item">
                            <i class="bi bi-person"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">تحویل گیرنده</h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="bi bi-geo-alt"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">آدرس ارسال</h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['address'] ?? 'ثبت نشده'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="bi bi-telephone"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">تلفن تماس</h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['phone'] ?? 'ثبت نشده'); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <i class="bi bi-envelope"></i>
                            <div>
                                <h6 class="mb-1 fw-bold">ایمیل</h6>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="profile-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-box-seam"></i> محصولات سفارش
                    </div>
                    <div class="card-body">
                        <?php if (count($orderItems) > 0): ?>
                            <?php foreach ($orderItems as $item): ?>
                                <div class="product-card">
                                    <div class="row g-0">
                                        <div class="col-md-3">
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="img-fluid product-img" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3">
                                                <h5 class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></h5>
                                                <p class="text-muted small mb-2"><?php echo substr(htmlspecialchars($item['product_description']), 0, 100); ?>...</p>
                                                
                                                <?php if ($item['discount_type'] !== 'none'): ?>
                                                    <div class="mt-2">
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-tag"></i> 
                                                            <?php 
                                                            if ($item['discount_type'] === 'percentage') {
                                                                echo $item['discount_value'] . '% تخفیف';
                                                            } else {
                                                                echo number_format($item['discount_value']) . ' تومان تخفیف';
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="p-3 text-end">
                                                <p class="mb-1">تعداد: <?php echo $item['quantity']; ?></p>
                                                
                                                <?php if ($item['discount_type'] !== 'none'): ?>
                                                    <p class="mb-1 text-muted">
                                                        <del><?php echo number_format($item['original_price']); ?></del> 
                                                        <span class="text-danger"><?php echo number_format($item['final_price']); ?></span> تومان
                                                    </p>
                                                    <p class="mb-1">قیمت با تخفیف: <?php echo number_format($item['final_price']); ?> تومان</p>
                                                <?php else: ?>
                                                    <p class="mb-1">قیمت واحد: <?php echo number_format($item['original_price']); ?> تومان</p>
                                                <?php endif; ?>
                                                
                                                <p class="fw-bold">جمع: <?php echo number_format($item['subtotal']); ?> تومان</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-cart-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">محصولی یافت نشد</h5>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-card">
                    <div class="card-header">
                        <i class="bi bi-receipt"></i> خلاصه پرداخت
                    </div>
                    <div class="card-body">
                        <div class="order-summary">
                            <div class="d-flex justify-content-between mb-2">
                                <span>جمع کل محصولات:</span>
                                <span><?php echo number_format($subtotalWithoutDiscount); ?> تومان</span>
                            </div>
                            
                            <?php if ($totalDiscount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>تخفیف محصولات:</span>
                                    <span>- <?php echo number_format($totalDiscount); ?> تومان</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-3 fw-bold fs-5">
                                <span>مبلغ قابل پرداخت:</span>
                                <span><?php echo number_format($order['total_price']); ?> تومان</span>
                            </div>
                            
                            <?php if ($order['payment_status'] === 'successful'): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>روش پرداخت:</span>
                                    <span><?php echo $order['payment_method'] === 'online' ? 'آنلاین' : 'پرداخت در محل'; ?></span>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-3">
                                    <span>تاریخ پرداخت:</span>
                                    <span><?php echo date('Y/m/d H:i', strtotime($order['payment_date'])); ?></span>
                                </div>
                                
                                <div class="alert alert-success d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <span>پرداخت شما با موفقیت انجام شده است.</span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <span>پرداخت شما <?php echo $order['payment_status'] === 'failed' ? 'ناموفق بوده' : 'هنوز انجام نشده'; ?> است.</span>
                                </div>
                                
                                <?php if ($order['payment_status'] !== 'failed'): ?>
                                    <a href="#" class="btn btn-primary w-100">
                                        <i class="bi bi-credit-card me-1"></i> پرداخت آنلاین
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
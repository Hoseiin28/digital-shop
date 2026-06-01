<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}


$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM Users")->fetch_assoc()['total'] ?? 0;

$totalProducts = $conn->query("SELECT COUNT(*) AS total FROM Products")->fetch_assoc()['total'] ?? 0;

$totalOrders = $conn->query("SELECT COUNT(*) AS total FROM Orders")->fetch_assoc()['total'] ?? 0;

$totalRevenue = $conn->query("SELECT SUM(total_price) AS total FROM Orders WHERE payment_status = 'paid'")->fetch_assoc()['total'] ?? 0;

$recentOrders = $conn->query("SELECT o.id, u.name as user_name, o.total_price, o.status, o.created_at 
                             FROM Orders o JOIN Users u ON o.user_id = u.id 
                             ORDER BY o.created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$recentMessages = $conn->query("SELECT id, name, email, SUBSTRING(message, 1, 50) as preview, created_at 
                               FROM ContactMessages 
                               ORDER BY created_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);


$onlineThreshold = time() - 300;
$onlineUsers = $conn->query("SELECT id, name, email, last_activity FROM Users WHERE last_activity > $onlineThreshold ORDER BY last_activity DESC")->fetch_all(MYSQLI_ASSOC);

$recentlyActiveThreshold = time() - 3600;
$recentlyActiveUsers = $conn->query("SELECT id, name, email, last_activity FROM Users WHERE last_activity > $recentlyActiveThreshold AND last_activity <= $onlineThreshold ORDER BY last_activity DESC")->fetch_all(MYSQLI_ASSOC);


$dailySales = $conn->query("
    SELECT DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count 
    FROM Orders 
    WHERE payment_status = 'paid' AND created_at >= CURDATE()
    GROUP BY DATE(created_at)
")->fetch_assoc();

$weeklySales = $conn->query("
    SELECT YEARWEEK(created_at) as week, SUM(total_price) as total, COUNT(*) as count 
    FROM Orders 
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY YEARWEEK(created_at)
")->fetch_all(MYSQLI_ASSOC);

$monthlySales = $conn->query("
    SELECT YEAR(created_at) as year, MONTH(created_at) as month, 
           SUM(total_price) as total, COUNT(*) as count 
    FROM Orders 
    WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY YEAR(created_at), MONTH(created_at)
")->fetch_all(MYSQLI_ASSOC);

$bestSellingProducts = $conn->query("
    SELECT p.id, p.name, SUM(od.quantity) as total_quantity, 
           SUM(od.subtotal) as total_revenue 
    FROM OrderDetails od
    JOIN Products p ON od.product_id = p.id
    JOIN Orders o ON od.order_id = o.id
    WHERE o.payment_status = 'paid' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY p.id, p.name
    ORDER BY total_quantity DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);


$lowStockProducts = $conn->query("
    SELECT id, name, stock, price 
    FROM Products 
    WHERE stock <= 5 AND stock > 0
    ORDER BY stock ASC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$highStockProducts = $conn->query("
    SELECT p.id, p.name, p.stock, p.price, 
           IFNULL(SUM(od.quantity), 0) as total_sold
    FROM Products p
    LEFT JOIN OrderDetails od ON p.id = od.product_id
    LEFT JOIN Orders o ON od.order_id = o.id AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    WHERE p.stock > 50
    GROUP BY p.id, p.name, p.stock, p.price
    HAVING total_sold < 5 OR total_sold IS NULL
    ORDER BY p.stock DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM Users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) as current_month_users,
        (SELECT COUNT(*) FROM Users WHERE created_at BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH))) as last_month_users,
        
        (SELECT COUNT(*) FROM Products WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) as current_month_products,
        (SELECT COUNT(*) FROM Products WHERE created_at BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH))) as last_month_products,
        
        (SELECT COUNT(*) FROM Orders WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) as current_month_orders,
        (SELECT COUNT(*) FROM Orders WHERE created_at BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH))) as last_month_orders,
        
        (SELECT SUM(total_price) FROM Orders WHERE payment_status = 'paid' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) as current_month_revenue,
        (SELECT SUM(total_price) FROM Orders WHERE payment_status = 'paid' AND created_at BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m-01') AND LAST_DAY(DATE_SUB(NOW(), INTERVAL 1 MONTH))) as last_month_revenue
";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

$usersChangePercent = $stats['last_month_users'] > 0 ?
    round((($stats['current_month_users'] - $stats['last_month_users']) / $stats['last_month_users'] * 100)) : 0;

$productsChangePercent = $stats['last_month_products'] > 0 ?
    round((($stats['current_month_products'] - $stats['last_month_products']) / $stats['last_month_products'] * 100)) : 0;

$ordersChangePercent = $stats['last_month_orders'] > 0 ?
    round((($stats['current_month_orders'] - $stats['last_month_orders']) / $stats['last_month_orders'] * 100)) : 0;

$revenueChangePercent = $stats['last_month_revenue'] > 0 ?
    round((($stats['current_month_revenue'] - $stats['last_month_revenue']) / $stats['last_month_revenue'] * 100)) : 0;

$conn->close();
?>

<?php
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}
require_once 'config.php';

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$admin_id = $_SESSION['user_id'] ?? null;
$admin_info = [];

if ($admin_id) {
    try {
        $stmt = $pdo->prepare("SELECT name, avatar, role FROM Users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "خطا در دریافت اطلاعات مدیر: " . $e->getMessage();
    }
}

$admin_name = $admin_info['name'] ?? 'مدیر سیستم';
$admin_avatar = $admin_info['avatar'] ?? 'static/img/avatars/default-avatar.jpg';
$admin_role = $admin_info['role'] ?? 'مدیر کل';
?>


<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style-admin-panel.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
</head>

<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="admin-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <span><?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?></span>
                </div>
                <button class="toggle-btn" id="toggleSidebar">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-section-title">مدیریت اصلی</div>
                    <a href="admin-panel.php" class="menu-item active">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="menu-item-text">پیشخوان</span>
                    </a>
                    <a href="backup.php" class="menu-item">
                        <i class="fas fa-database"></i>
                        <span class="menu-item-text">پشتیبان‌گیری</span>
                    </a>
                    <a href="settings.php" class="menu-item">
                        <i class="fas fa-cog"></i>
                        <span class="menu-item-text">تنظیمات</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-section-title">مدیریت اعلان</div>
                    <a href="notifications.php" class="menu-item">
                        <i class="fas fa-bell"></i>
                        <span class="menu-item-text">اعلان ها</span>
                    </a>
                    <a href="SMS-System.php" class="menu-item">
                        <i class="fas fa-comments"></i>
                        <span class="menu-item-text">سیستم پیامکی</span>
                    </a>
                    <a href="Email-system.php" class="menu-item">
                        <i class="fas fa-envelope"></i>
                        <span class="menu-item-text">سیستم ایمیل</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-section-title">مدیریت محتوا</div>
                    <a href="list-products.php" class="menu-item">
                        <i class="fas fa-box-open"></i>
                        <span class="menu-item-text">محصولات</span>
                    </a>
                    <a href="list-ProductAttributes.php" class="menu-item">
                        <i class="fas fa-list"></i>
                        <span class="menu-item-text">ویژگی محصولات</span>
                    </a>
                    <a href="manage-product-images.php" class="menu-item">
                        <i class="fas fa-image"></i>
                        <span class="menu-item-text">تصاویر محصولات</span>
                    </a>
                    <a href="list-categories.php" class="menu-item">
                        <i class="fas fa-tags"></i>
                        <span class="menu-item-text">دسته‌بندی‌ها</span>
                    </a>
                    <a href="list-articles.php" class="menu-item">
                        <i class="fas fa-newspaper"></i>
                        <span class="menu-item-text">مقالات</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-section-title">مدیریت فروش</div>
                    <a href="list-orders.php" class="menu-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="menu-item-text">سفارش‌ها</span>
                    </a>
                    <a href="list-discounts.php" class="menu-item">
                        <i class="fas fa-percentage"></i>
                        <span class="menu-item-text">تخفیف‌ها</span>
                    </a>
                    <a href="financial-reports.php" class="menu-item">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-item-text">گزارشات مالی</span>
                    </a>
                    <a href="payment-management.php" class="menu-item">
                        <i class="fas fa-credit-card"></i>
                        <span class="menu-item-text">مدیریت پرداخت‌ها</span>
                    </a>
                </div>

                <div class="menu-section">
                    <div class="menu-section-title">مدیریت کاربران</div>
                    <a href="list-users.php" class="menu-item">
                        <i class="fas fa-users"></i>
                        <span class="menu-item-text">کاربران</span>
                    </a>
                    <a href="list-messages.php" class="menu-item">
                        <i class="fas fa-envelope"></i>
                        <span class="menu-item-text">پیام‌ها</span>
                    </a>
                    <a href="list-reviews.php" class="menu-item">
                        <i class="fas fa-comments"></i>
                        <span class="menu-item-text">نظرات</span>
                    </a>
                    <a href="list-consultations.php" class="menu-item">
                        <i class="fas fa-headset"></i>
                        <span class="menu-item-text">مشاوره‌ها</span>
                    </a>
                    <a href="list-faq.php" class="menu-item">
                        <i class="fas fa-question-circle"></i>
                        <span class="menu-item-text">سوالات متداول</span>
                    </a>
                    <a href="manage-about-us.php" class="menu-item">
                        <i class="fas fa-info-circle me-2"></i>
                        <span class="menu-item-text">درباره ما</span>
                    </a>
                </div>

                <a href="/digital-shop/index.php" class="menu-item">
                    <i class="fas fa-store"></i>
                    <span class="menu-item-text">بازگشت به فروشگاه</span>
                </a>

                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="logout-text">خروج از سیستم</span>
                </a>
            </nav>
        </aside>

        <main class="main-content" id="mainContent">
            <div class="header">
                <h1 class="page-title">
                    <i class="fas fa-tachometer-alt"></i>
                    پیشخوان مدیریت
                </h1>

                <div class="header-actions">
                    <button class="refresh-btn" id="refreshBtn" title="بروزرسانی صفحه">
                        <i class="fas fa-sync-alt"></i>
                    </button>

                    <div class="notification-container">
                        <button class="notification-btn" id="notificationBtn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-count" id="notificationCount"></span>
                        </button>
                        <div class="notification-dropdown" id="notificationDropdown">
                            <div class="notification-header">
                                <h4>اعلان‌ها</h4>
                                <button class="mark-all-read" id="markAllRead">علامت‌گذاری همه به عنوان خوانده شده</button>
                            </div>
                            <div class="notification-list" id="notificationList"></div>
                            <div class="notification-footer">
                                <a href="notifications.php">مشاهده همه اعلان‌ها</a>
                            </div>
                        </div>
                    </div>

                    <div class="user-profile">
                        <img src="<?= htmlspecialchars($admin_avatar) ?>" alt="<?= htmlspecialchars($admin_name) ?>" class="user-avatar">
                        <div>
                            <div class="user-name"><?= htmlspecialchars($admin_name) ?></div>
                            <div class="user-role">
                                <?= $admin_role === 'admin' ? 'مدیر کل' : 'کاربر سیستم' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">کاربران ثبت‌شده</h3>
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($totalUsers) ?></div>
                        <div class="stat-change <?= $usersChangePercent >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $usersChangePercent >= 0 ? 'up' : 'down' ?>"></i>
                            <span><?= abs($usersChangePercent) ?>% نسبت به ماه گذشته</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">محصولات فعال</h3>
                            <div class="stat-icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($totalProducts) ?></div>
                        <div class="stat-change <?= $productsChangePercent >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $productsChangePercent >= 0 ? 'up' : 'down' ?>"></i>
                            <span><?= abs($productsChangePercent) ?>% نسبت به ماه گذشته</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">سفارشات</h3>
                            <div class="stat-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($totalOrders) ?></div>
                        <div class="stat-change <?= $ordersChangePercent >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $ordersChangePercent >= 0 ? 'up' : 'down' ?>"></i>
                            <span><?= abs($ordersChangePercent) ?>% نسبت به ماه گذشته</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-header">
                            <h3 class="stat-title">درآمد کل</h3>
                            <div class="stat-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($totalRevenue) ?> <small>تومان</small></div>
                        <div class="stat-change <?= $revenueChangePercent >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-arrow-<?= $revenueChangePercent >= 0 ? 'up' : 'down' ?>"></i>
                            <span><?= abs($revenueChangePercent) ?>% نسبت به ماه گذشته</span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-sections fade-in">
                    <section class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-clock"></i>
                                سفارشات اخیر
                            </h3>
                            <a href="list-orders.php" class="section-link">
                                مشاهده همه
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>شماره سفارش</th>
                                        <th>مشتری</th>
                                        <th>مبلغ</th>
                                        <th>وضعیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recentOrders) > 0): ?>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($recentOrders as $order): ?>
                                            <tr>
                                                <td><?= $counter ?></td>
                                                <td><?= htmlspecialchars($order['user_name']) ?></td>
                                                <td><?= number_format($order['total_price']) ?> تومان</td>
                                                <td>
                                                    <span class="status-badge status-<?= $order['status'] ?>">
                                                        <?php
                                                        $statusLabels = [
                                                            'pending' => '<i class="fas fa-clock"></i> در انتظار',
                                                            'processing' => '<i class="fas fa-cog fa-spin"></i> در حال پردازش',
                                                            'shipped' => '<i class="fas fa-truck"></i> ارسال شده',
                                                            'completed' => '<i class="fas fa-check-circle"></i> تکمیل شده',
                                                            'cancelled' => '<i class="fas fa-times-circle"></i> لغو شده'
                                                        ];
                                                        echo $statusLabels[$order['status']] ?? $order['status'];
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php $counter++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="empty-state">
                                                    <i class="fas fa-shopping-cart"></i>
                                                    <p>هیچ سفارشی یافت نشد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-envelope"></i>
                                پیام‌های اخیر
                            </h3>
                            <a href="list-messages.php" class="section-link">
                                مشاهده همه
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>فرستنده</th>
                                        <th>ایمیل</th>
                                        <th>پیام</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($recentMessages) > 0): ?>
                                        <?php foreach ($recentMessages as $message): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($message['name']) ?></td>
                                                <td><?= htmlspecialchars($message['email']) ?></td>
                                                <td><?= htmlspecialchars($message['preview']) ?>...</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3">
                                                <div class="empty-state">
                                                    <i class="fas fa-envelope-open"></i>
                                                    <p>هیچ پیامی یافت نشد</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <section class="section-card fade-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            محصولات پرفروش
                        </h3>
                        <a href="list-products.php" class="section-link">
                            مشاهده همه
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>نام محصول</th>
                                    <th>تعداد فروش</th>
                                    <th>درآمد کل</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($bestSellingProducts) > 0): ?>
                                    <?php foreach ($bestSellingProducts as $product): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($product['name']) ?></td>
                                            <td><?= number_format($product['total_quantity']) ?></td>
                                            <td><?= number_format($product['total_revenue']) ?> تومان</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="empty-state">
                                                <i class="fas fa-box-open"></i>
                                                <p>هیچ محصول فروخته شده‌ای یافت نشد</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <br>
                <section class="section-card fade-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line"></i>
                            گزارشات فروش
                        </h3>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <h3 class="stat-title">فروش امروز</h3>
                                    <div class="stat-icon">
                                        <i class="fas fa-sun"></i>
                                    </div>
                                </div>
                                <div class="stat-value"><?= number_format($dailySales['total'] ?? 0) ?> <small>تومان</small></div>
                                <div class="stat-change up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span><?= $dailySales['count'] ?? 0 ?> سفارش</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <h3 class="stat-title">فروش هفتگی</h3>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                </div>
                                <div class="stat-value"><?= number_format(array_sum(array_column($weeklySales, 'total'))) ?> <small>تومان</small></div>
                                <div class="stat-change up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span><?= array_sum(array_column($weeklySales, 'count')) ?> سفارش</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-header">
                                    <h3 class="stat-title">فروش ماهانه</h3>
                                    <div class="stat-icon">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                </div>
                                <div class="stat-value"><?= number_format(array_sum(array_column($monthlySales, 'total'))) ?> <small>تومان</small></div>
                                <div class="stat-change up">
                                    <i class="fas fa-arrow-up"></i>
                                    <span><?= array_sum(array_column($monthlySales, 'count')) ?> سفارش</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </section>
                <br>
                <section class="section-card fade-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-boxes"></i>
                            مدیریت موجودی کالا
                        </h3>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        محصولات کم‌موجود
                                    </h3>
                                    <span class="badge bg-danger"><?= count($lowStockProducts) ?></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>نام محصول</th>
                                                <th>موجودی</th>
                                                <th>قیمت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($lowStockProducts) > 0): ?>
                                                <?php foreach ($lowStockProducts as $product): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-danger">
                                                                <?= $product['stock'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= number_format($product['price']) ?> تومان</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3">
                                                        <div class="empty-state">
                                                            <i class="fas fa-check-circle"></i>
                                                            <p>هیچ محصول کم‌موجودی وجود ندارد</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="fas fa-box-open"></i>
                                        محصولات با موجودی زیاد
                                    </h3>
                                    <span class="badge bg-warning"><?= count($highStockProducts) ?></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>نام محصول</th>
                                                <th>موجودی</th>
                                                <th>فروش 3 ماهه</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($highStockProducts) > 0): ?>
                                                <?php foreach ($highStockProducts as $product): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                                        <td>
                                                            <span class="badge bg-warning">
                                                                <?= $product['stock'] ?>
                                                            </span>
                                                        </td>
                                                        <td><?= $product['total_sold'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3">
                                                        <div class="empty-state">
                                                            <i class="fas fa-check-circle"></i>
                                                            <p>هیچ محصول با موجودی زیاد وجود ندارد</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <br>
                <section class="section-card fade-in">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user-clock"></i>
                            وضعیت کاربران
                        </h3>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="fas fa-circle text-success"></i>
                                        کاربران آنلاین
                                    </h3>
                                    <span class="badge bg-success"><?= count($onlineUsers) ?></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>نام کاربر</th>
                                                <th>ایمیل</th>
                                                <th>وضعیت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($onlineUsers) > 0): ?>
                                                <?php foreach ($onlineUsers as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td>
                                                            <span class="badge bg-success">آنلاین</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3">
                                                        <div class="empty-state">
                                                            <i class="fas fa-user-slash"></i>
                                                            <p>هیچ کاربر آنلاینی وجود ندارد</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i class="fas fa-history text-warning"></i>
                                        اخیراً فعال
                                    </h3>
                                    <span class="badge bg-warning"><?= count($recentlyActiveUsers) ?></span>
                                </div>

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>نام کاربر</th>
                                                <th>ایمیل</th>
                                                <th>وضعیت</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($recentlyActiveUsers) > 0): ?>
                                                <?php foreach ($recentlyActiveUsers as $user): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                        <td>
                                                            <span class="badge bg-warning">اخیراً فعال</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3">
                                                        <div class="empty-state">
                                                            <i class="fas fa-user-clock"></i>
                                                            <p>هیچ کاربری اخیراً فعال نبوده است</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <a href="../index.php" class="floating-shop-btn" title="بازگشت به فروشگاه">
        <i class="fas fa-store"></i>
    </a>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationCount = document.getElementById('notificationCount');
            const notificationList = document.getElementById('notificationList');
            const markAllReadBtn = document.getElementById('markAllRead');

            let notifications = [];
            let unreadCount = 0;
            let notificationCheckInterval;

            function initNotificationSystem() {
                setupEventListeners();
                loadNotifications();
                startNotificationPolling();
            }

            function setupEventListeners() {
                notificationBtn.addEventListener('click', toggleNotificationDropdown);

                document.addEventListener('click', handleOutsideClick);

                markAllReadBtn.addEventListener('click', markAllNotificationsAsRead);
            }

            function toggleNotificationDropdown(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('active');
                if (notificationDropdown.classList.contains('active')) {
                    loadNotifications();
                }
            }

            function handleOutsideClick(e) {
                if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
                    notificationDropdown.classList.remove('active');
                }
            }

            async function loadNotifications() {
                try {
                    showLoadingIndicator();

                    const response = await fetch('get-notifications.php');
                    const data = await response.json();

                    if (data.success) {
                        notifications = data.notifications;
                        unreadCount = data.unreadCount;

                        updateNotificationCount();
                        renderNotifications();
                    } else {
                        showErrorState();
                    }
                } catch (error) {
                    console.error('Error loading notifications:', error);
                    showErrorState();
                }
            }

            function showLoadingIndicator() {
                notificationList.innerHTML = `
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>در حال بارگذاری اعلان‌ها...</p>
            </div>
        `;
            }

            function showErrorState() {
                notificationList.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-circle"></i>
                <p>خطا در بارگذاری اعلان‌ها</p>
                <button class="retry-btn" id="retryBtn">تلاش مجدد</button>
            </div>
        `;

                document.getElementById('retryBtn').addEventListener('click', loadNotifications);
            }

            function renderNotifications() {
                notificationList.innerHTML = '';

                if (notifications.length === 0) {
                    showEmptyState();
                    return;
                }

                notifications.forEach(notification => {
                    const notificationItem = createNotificationItem(notification);
                    notificationList.appendChild(notificationItem);
                });
            }

            function showEmptyState() {
                notificationList.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <p>هیچ اعلانی وجود ندارد</p>
            </div>
        `;
            }

            function createNotificationItem(notification) {
                const notificationItem = document.createElement('div');
                notificationItem.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
                notificationItem.dataset.id = notification.id;

                const {
                    iconClass,
                    iconName
                } = getNotificationIcon(notification.type);
                const timeAgo = formatTimeAgo(notification.created_at);

                notificationItem.innerHTML = `
            <div class="notification-icon ${iconClass}">
                <i class="${iconName}"></i>
            </div>
            <div class="notification-content">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
                <div class="notification-time">
                    <i class="fas fa-clock"></i>
                    ${timeAgo}
                </div>
            </div>
            <button class="notification-dismiss" data-id="${notification.id}">
                <i class="fas fa-times"></i>
            </button>
        `;

                addNotificationEventListeners(notificationItem, notification);

                return notificationItem;
            }

            function getNotificationIcon(type) {
                const icons = {
                    'new_user': {
                        class: 'user',
                        icon: 'fas fa-user-plus'
                    },
                    'new_order': {
                        class: 'order',
                        icon: 'fas fa-shopping-cart'
                    },
                    'new_message': {
                        class: 'message',
                        icon: 'fas fa-envelope'
                    },
                    'new_review': {
                        class: 'review',
                        icon: 'fas fa-comment'
                    },
                    'new_consultation': {
                        class: 'consultation',
                        icon: 'fas fa-headset'
                    },
                    'order_status_change': {
                        class: 'status',
                        icon: 'fas fa-exchange-alt'
                    },
                    'payment_success': {
                        class: 'payment',
                        icon: 'fas fa-credit-card'
                    },
                    'low_stock': {
                        class: 'warning',
                        icon: 'fas fa-exclamation-triangle'
                    },
                    'out_of_stock': {
                        class: 'danger',
                        icon: 'fas fa-times-circle'
                    },
                    'discount': {
                        class: 'discount',
                        icon: 'fas fa-percentage'
                    },
                    'product_view': {
                        class: 'views',
                        icon: 'fas fa-eye'
                    },
                    'default': {
                        class: 'other',
                        icon: 'fas fa-bell'
                    }
                };

                return icons[type] || icons['default'];
            }

            function addNotificationEventListeners(item, notification) {

                item.addEventListener('click', async function(e) {
                    if (e.target.closest('.notification-dismiss')) return;

                    if (!notification.is_read) {
                        await markNotificationAsRead(notification.id);
                        item.classList.remove('unread');
                        unreadCount--;
                        updateNotificationCount();
                    }

                    redirectBasedOnNotification(notification);
                });

                const dismissBtn = item.querySelector('.notification-dismiss');
                dismissBtn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    await dismissNotification(this.dataset.id);
                    item.remove();

                    if (!notification.is_read) {
                        unreadCount--;
                        updateNotificationCount();
                    }
                });
            }

            async function markNotificationAsRead(id) {
                try {
                    await fetch('mark-notification-read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id
                        })
                    });
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                }
            }

            async function dismissNotification(id) {
                try {
                    await fetch('dismiss-notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id
                        })
                    });
                } catch (error) {
                    console.error('Error dismissing notification:', error);
                }
            }

            async function markAllNotificationsAsRead() {
                try {
                    const response = await fetch('mark-all-notifications-read.php', {
                        method: 'POST'
                    });

                    const data = await response.json();

                    if (data.success) {
                        unreadCount = 0;
                        updateNotificationCount();

                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                    }
                } catch (error) {
                    console.error('Error marking all notifications as read:', error);
                }
            }

            function updateNotificationCount() {
                notificationCount.textContent = unreadCount > 99 ? '99+' : unreadCount;
                if (unreadCount === 0) {
                    notificationCount.style.display = 'none';
                } else {
                    notificationCount.style.display = 'flex';

                    if (unreadCount > 0) {
                        notificationCount.classList.add('pulse');
                        setTimeout(() => {
                            notificationCount.classList.remove('pulse');
                        }, 1000);
                    }
                }
            }

            function formatTimeAgo(timestamp) {
                const now = new Date();
                const notificationDate = new Date(timestamp);
                const seconds = Math.floor((now - notificationDate) / 1000);

                if (seconds < 60) return 'همین الان';
                if (seconds < 3600) return `${Math.floor(seconds / 60)} دقیقه پیش`;
                if (seconds < 86400) return `${Math.floor(seconds / 3600)} ساعت پیش`;
                if (seconds < 604800) return `${Math.floor(seconds / 86400)} روز پیش`;

                return notificationDate.toLocaleDateString('fa-IR');
            }

            function redirectBasedOnNotification(notification) {
                const routes = {
                    'new_user': 'list-users.php',
                    'new_order': 'list-orders.php',
                    'order_status_change': 'list-orders.php',
                    'payment_success': 'list-orders.php',
                    'new_message': 'list-consultations.php',
                    'new_review': 'list-reviews.php',
                    'new_consultation': 'list-consultations.php',
                    'low_stock': 'list-products.php',
                    'out_of_stock': 'list-products.php',
                    'product_view': 'list-products.php',
                    'discount': 'list-discounts.php'
                };

                const baseRoute = routes[notification.type] || '#';
                const url = notification.related_id ? `${baseRoute}?id=${notification.related_id}` : baseRoute;

                window.location.href = url;
            }

            function startNotificationPolling() {

                if (notificationCheckInterval) {
                    clearInterval(notificationCheckInterval);
                }

                notificationCheckInterval = setInterval(() => {
                    if (!notificationDropdown.classList.contains('active')) {
                        checkForNewNotifications();
                    }
                }, 3000);
            }

            async function checkForNewNotifications() {
                try {
                    const response = await fetch('check-new-notifications.php');
                    const data = await response.json();

                    if (data.success && data.newNotifications > 0) {

                        unreadCount += data.newNotifications;
                        updateNotificationCount();


                        if (data.newNotifications === 1) {
                            showNewNotificationAlert(data.lastNotification);
                        }
                    }
                } catch (error) {
                    console.error('Error checking for new notifications:', error);
                }
            }

            function showNewNotificationAlert(notification) {
                const alert = document.createElement('div');
                alert.className = 'notification-alert';

                const {
                    iconClass,
                    iconName
                } = getNotificationIcon(notification.type);

                alert.innerHTML = `
            <div class="notification-alert-icon ${iconClass}">
                <i class="${iconName}"></i>
            </div>
            <div class="notification-alert-content">
                <div class="notification-alert-title">${notification.title}</div>
                <div class="notification-alert-message">${notification.message}</div>
            </div>
            <button class="notification-alert-close">
                <i class="fas fa-times"></i>
            </button>
        `;

                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 300);
                }, 5000);

                alert.querySelector('.notification-alert-close').addEventListener('click', () => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 300);
                });

                alert.addEventListener('click', () => {
                    notificationDropdown.classList.add('active');
                    loadNotifications();
                    alert.remove();
                });
            }

            initNotificationSystem();
        });

        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                location.reload();
            }, 300);
        });

        document.getElementById('menuToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menuToggle');

            if (window.innerWidth <= 992 &&
                !sidebar.contains(event.target) &&
                event.target !== menuToggle &&
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('sidebar-collapsed');
            document.getElementById('mainContent').classList.toggle('main-content-expanded');

            const icon = this.querySelector('i');
            if (document.getElementById('sidebar').classList.contains('sidebar-collapsed')) {
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-left');
            } else {
                icon.classList.remove('fa-chevron-left');
                icon.classList.add('fa-chevron-right');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');

            const salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['امروز', 'هفته جاری', 'ماه جاری'],
                    datasets: [{
                        label: 'تعداد سفارشات',
                        data: [
                            <?= $dailySales['count'] ?? 0 ?>,
                            <?= array_sum(array_column($weeklySales, 'count')) ?>,
                            <?= array_sum(array_column($monthlySales, 'count')) ?>
                        ],
                        backgroundColor: 'rgba(67, 97, 238, 0.7)',
                        borderColor: 'rgba(67, 97, 238, 1)',
                        borderWidth: 1
                    }, {
                        label: 'درآمد (تومان)',
                        data: [
                            <?= $dailySales['total'] ?? 0 ?>,
                            <?= array_sum(array_column($weeklySales, 'total')) ?>,
                            <?= array_sum(array_column($monthlySales, 'total')) ?>
                        ],
                        backgroundColor: 'rgba(114, 9, 183, 0.7)',
                        borderColor: 'rgba(114, 9, 183, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            rtl: true
                        },
                        title: {
                            display: true,
                            text: 'گزارش فروش بر اساس زمان',
                            rtl: true,
                            font: {
                                size: 16
                            }
                        }
                    }
                }
            });
        });

        document.getElementById('refreshBtn').addEventListener('click', function() {
            const btn = this;
            const icon = btn.querySelector('i');

            btn.disabled = true;

            icon.classList.add('fa-spin');

            setTimeout(function() {
                location.reload();
            }, 1000);

            setTimeout(function() {
                btn.disabled = false;
                icon.classList.remove('fa-spin');
            }, 3000);
        });
    </script>
</body>

</html>
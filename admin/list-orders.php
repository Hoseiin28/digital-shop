<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch();
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

if (isset($_POST['delete_order'])) {
    $orderId = (int)$_POST['delete_order'];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM OrderDetails WHERE order_id = ?");
        $stmt->execute([$orderId]);

        $stmt = $pdo->prepare("DELETE FROM Orders WHERE id = ?");
        $stmt->execute([$orderId]);

        $pdo->commit();
        $_SESSION['success'] = "سفارش با موفقیت حذف شد.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در حذف سفارش: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['update_status'])) {
    $orderId = (int)$_POST['order_id'];
    $currentStatus = $_POST['current_status'];

    $nextStatus = '';
    switch ($currentStatus) {
        case 'pending':
            $nextStatus = 'processing';
            break;
        case 'processing':
            $nextStatus = 'shipped';
            break;
        case 'shipped':
            $nextStatus = 'completed';
            break;
        default:
            $nextStatus = $currentStatus;
    }

    if ($nextStatus !== $currentStatus) {
        try {
            $stmt = $pdo->prepare("UPDATE Orders SET status = ? WHERE id = ?");
            $stmt->execute([$nextStatus, $orderId]);

            $statusMessages = [
                'processing' => 'سفارش به وضعیت "در حال پردازش" تغییر یافت.',
                'shipped' => 'سفارش به وضعیت "ارسال شده" تغییر یافت.',
                'completed' => 'سفارش به وضعیت "تکمیل شده" تغییر یافت.'
            ];

            $_SESSION['success'] = $statusMessages[$nextStatus] ?? 'وضعیت سفارش به روز شد.';
        } catch (PDOException $e) {
            $_SESSION['error'] = "خطا در به‌روزرسانی سفارش: " . $e->getMessage();
        }
    } else {
        $_SESSION['warning'] = "سفارش در حال حاضر در آخرین وضعیت ممکن است.";
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

try {
    $totalOrders = $pdo->query("SELECT COUNT(*) AS total FROM Orders")->fetch(PDO::FETCH_ASSOC)['total'];
    $totalSales = $pdo->query("SELECT SUM(total_price) AS total FROM Orders")->fetch(PDO::FETCH_ASSOC)['total'];

    $paidOrders = $pdo->query("SELECT COUNT(*) AS total_paid, SUM(total_price) AS total_paid_sales FROM Orders WHERE payment_status = 'paid'")->fetch(PDO::FETCH_ASSOC);
    $totalPaidOrders = $paidOrders['total_paid'] ?? 0;
    $totalPaidSales = $paidOrders['total_paid_sales'] ?? 0;
} catch (PDOException $e) {
    $error = "خطا در دریافت آمار سفارش‌ها: " . $e->getMessage();
}

$ordersPerPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $ordersPerPage;

$search = isset($_GET['search']) ? trim($_GET['search']) : "";
$searchTerm = "%$search%";

$sqlSearchWhere = "WHERE (Users.name LIKE :search OR Orders.status LIKE :search)";
$params = ['search' => $searchTerm];

if (is_numeric($search)) {
    $sqlSearchWhere = "WHERE (Orders.id = :id OR Users.name LIKE :search OR Orders.status LIKE :search)";
    $params = ['id' => (int)$search, 'search' => $searchTerm];
}

$sql = "SELECT 
            Orders.id AS order_id, 
            Users.name AS user_name, 
            Orders.total_price, 
            Orders.status, 
            Orders.payment_status, 
            Orders.created_at,
            (
                SELECT IFNULL(SUM(
                    CASE 
                        WHEN d.discount_type = 'percentage' THEN 
                            od.quantity * (od.price * d.discount_value / 100)
                        WHEN d.discount_type = 'fixed' THEN 
                            od.quantity * d.discount_value
                        ELSE 0
                    END
                ), 0)
                FROM OrderDetails od
                JOIN Products p ON od.product_id = p.id
                LEFT JOIN Discounts d ON p.id = d.product_id 
                    AND (d.start_date IS NULL OR d.start_date <= NOW())
                    AND (d.end_date IS NULL OR d.end_date >= NOW())
                WHERE od.order_id = Orders.id
            ) AS total_discount
        FROM Orders
        JOIN Users ON Orders.user_id = Users.id
        $sqlSearchWhere
        ORDER BY Orders.created_at DESC
        LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }

    $stmt->bindValue(':limit', $ordersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sqlCount = "SELECT COUNT(*) AS total FROM Orders JOIN Users ON Orders.user_id = Users.id $sqlSearchWhere";
    $stmtCount = $pdo->prepare($sqlCount);

    foreach ($params as $key => $value) {
        $stmtCount->bindValue(':' . $key, $value);
    }

    $stmtCount->execute();
    $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $ordersPerPage);
} catch (PDOException $e) {
    $error = "خطا در دریافت لیست سفارش‌ها: " . $e->getMessage();
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

$statusColors = [
    'pending' => 'text-warning',
    'processing' => 'text-info',
    'shipped' => 'text-primary',
    'completed' => 'text-success',
    'cancelled' => 'text-danger'
];

?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت سفارش‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=IRANSans:wght@300;400;500;700&family=Lalezar&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-orders.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-shopping-cart me-2"></i>
                    مدیریت سفارش‌ها
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه من') ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <a href="admin-panel.php" class="back-to-panel" title="بازگشت به پنل مدیریت">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalOrders) ?></div>
                    <div class="stat-label">تعداد کل سفارش‌ها</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalPaidOrders) ?></div>
                    <div class="stat-label">سفارش‌های پرداخت شده</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalSales) ?></div>
                    <div class="stat-label">جمع کل فروش</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($totalPaidSales) ?></div>
                    <div class="stat-label">جمع پرداخت‌ها</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">لیست سفارش‌ها</h5>
                <form method="GET" class="d-flex" style="width: 50%;">
                    <input type="text" name="search" class="form-control"
                        placeholder="جستجو بر اساس شماره سفارش، نام کاربر یا وضعیت سفارش"
                        value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-outline-secondary me-2" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div class="card-body">
                <?php if (!empty($orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">ردیف</th>
                                    <th>شماره سفارش</th>
                                    <th>نام کاربر</th>
                                    <th>تخفیف کل</th>
                                    <th>قیمت نهایی</th>
                                    <th>وضعیت سفارش</th>
                                    <th>وضعیت پرداخت</th>
                                    <th>تاریخ سفارش</th>
                                    <th width="200">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $index => $order): ?>
                                    <tr>
                                        <td><?= $offset + $index + 1 ?></td>
                                        <td>#<?= $order['order_id'] ?></td>
                                        <td><?= htmlspecialchars($order['user_name']) ?></td>
                                        <td>
                                            <?php if ($order['total_discount'] > 0): ?>
                                                <span class="discount-badge">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?= number_format($order['total_discount']) ?> تومان
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">بدون تخفیف</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($order['total_price']) ?> تومان</td>
                                        <td class="<?= $statusColors[$order['status']] ?>">
                                            <?= $statusTranslations[$order['status']] ?>
                                        </td>
                                        <td><?= $paymentTranslations[$order['payment_status']] ?></td>
                                        <td><?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-info me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#orderDetailsModal"
                                                onclick="loadOrderDetails(<?= $order['order_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <form method="POST" action="export-order-details.php" class="d-inline">
                                                <button class="btn btn-sm btn-warning me-1" name="order_id" value="<?= $order['order_id'] ?>">
                                                    <i class="fas fa-file-excel"></i>
                                                </button>
                                            </form>

                                            <?php if ($order['status'] !== 'completed'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                                    <input type="hidden" name="current_status" value="<?= $order['status'] ?>">
                                                    <button class="btn btn-sm btn-primary me-1" name="update_status"
                                                        title="تغییر وضعیت به مرحله بعدی">
                                                        <i class="fas fa-arrow-up"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" class="d-inline">
                                                <button class="btn btn-sm btn-danger" name="delete_order" value="<?= $order['order_id'] ?>"
                                                    onclick="return confirm('آیا از حذف سفارش #<?= $order['order_id'] ?> مطمئن هستید؟')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="صفحه‌بندی" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= htmlspecialchars($search) ?>" aria-label="قبلی">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&search=<?= htmlspecialchars($search) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= htmlspecialchars($search) ?>" aria-label="بعدی">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">هیچ سفارشی یافت نشد</h5>
                        <p class="text-muted">برای مشاهده سفارش‌ها، معیارهای جستجو را تغییر دهید</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">جزئیات سفارش #<span id="modalOrderId"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                        <p class="mt-2">در حال دریافت اطلاعات سفارش</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadOrderDetails(orderId) {
            document.getElementById('modalOrderId').textContent = orderId;

            fetch(`order_details.php?order_id=${orderId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            خطا در دریافت اطلاعات سفارش
                        </div>
                    `;
                });
        }
    </script>
</body>

</html>
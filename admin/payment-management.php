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
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات فروشگاه: " . $e->getMessage();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$items_per_page = 15;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

$base_query = "FROM Payments p
              JOIN Users u ON p.user_id = u.id
              JOIN Orders o ON p.order_id = o.id
              WHERE 1=1";

$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(u.name LIKE ? OR u.email LIKE ? OR p.id LIKE ? OR o.id LIKE ?)";
    $search_term = "%$search%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
}

if (!empty($status)) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status;
}

if (!empty($payment_method)) {
    $where_clauses[] = "p.payment_method = ?";
    $params[] = $payment_method;
}

if (!empty($start_date)) {
    $where_clauses[] = "p.payment_date >= ?";
    $params[] = $start_date . ' 00:00:00';
}

if (!empty($end_date)) {
    $where_clauses[] = "p.payment_date <= ?";
    $params[] = $end_date . ' 23:59:59';
}

$where = $where_clauses ? ' AND ' . implode(' AND ', $where_clauses) : '';

$total_query = "SELECT COUNT(*) " . $base_query . $where;
$stmt = $pdo->prepare($total_query);
$stmt->execute($params);
$total_payments = $stmt->fetchColumn();
$total_pages = ceil($total_payments / $items_per_page);

$query = "SELECT 
            p.*,
            u.name as user_name,
            u.email as user_email,
            o.id as order_id,
            o.total_price as order_amount,
            o.status as order_status,
            o.payment_status as order_payment_status
          " . $base_query . $where . "
          ORDER BY p.payment_date DESC
          LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($query);

foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}

$stmt->bindValue(count($params) + 1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_payment_status'])) {
        $order_id = $_POST['order_id'];
        $new_payment_status = $_POST['new_payment_status'];

        try {
            $stmt = $pdo->prepare("UPDATE Orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$new_payment_status, $order_id]);

            if ($new_payment_status === 'paid') {
                $stmt = $pdo->prepare("UPDATE Payments SET status = 'successful' WHERE order_id = ?");
                $stmt->execute([$order_id]);
            }

            $_SESSION['success'] = "وضعیت پرداخت سفارش با موفقیت به‌روزرسانی شد";
            header("Location: payment-management.php?" . http_build_query($_GET));
            exit();
        } catch (PDOException $e) {
            $error = "خطا در به‌روزرسانی وضعیت پرداخت سفارش: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداخت‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-payment-management.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= htmlspecialchars($settings['button_color'] ?? '#4e73df') ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: <?= htmlspecialchars($settings['font_family'] ?? 'Vazir, sans-serif') ?>;
            background-color: #f8f9fc;
            color: #333;
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-credit-card me-2"></i>
                    مدیریت پرداخت‌ها
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($settings['shop_name'] ?? 'فروشگاه') ?>
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

        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card filter-section">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>فیلترها</h5>
            </div>
            <div class="card-body">
                <form method="get" action="payment-management.php">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">جستجو:</label>
                            <input type="text" name="search" class="form-control"
                                value="<?= htmlspecialchars($search) ?>"
                                placeholder="جستجو بر اساس نام، ایمیل یا شماره پرداخت">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">وضعیت:</label>
                            <select name="status" class="form-select">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="successful" <?= $status === 'successful' ? 'selected' : '' ?>>موفق</option>
                                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>ناموفق</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">روش پرداخت:</label>
                            <select name="payment_method" class="form-select">
                                <option value="">همه روش‌ها</option>
                                <option value="online" <?= $payment_method === 'online' ? 'selected' : '' ?>>آنلاین</option>
                                <option value="cash_on_delivery" <?= $payment_method === 'cash_on_delivery' ? 'selected' : '' ?>>پرداخت در محل</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">از تاریخ:</label>
                            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label">تا تاریخ:</label>
                            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-2"></i> اعمال فیلتر
                        </button>
                        <a href="payment-management.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> حذف فیلترها
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>لیست پرداخت‌ها</h5>
                    <span class="badge bg-light text-dark">
                        تعداد کل: <?= number_format($total_payments) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($payments)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-credit-card text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">هیچ پرداختی یافت نشد</h5>
                        <p class="text-muted">با تغییر فیلترها دوباره امتحان کنید</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>شماره پرداخت</th>
                                    <th>مشتری</th>
                                    <th>شماره سفارش</th>
                                    <th>مبلغ</th>
                                    <th>روش پرداخت</th>
                                    <th>تاریخ پرداخت</th>
                                    <th>وضعیت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td data-label="شماره پرداخت"><?= $payment['id'] ?></td>
                                        <td data-label="مشتری">
                                            <div><?= htmlspecialchars($payment['user_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($payment['user_email']) ?></small>
                                        </td>
                                        <td data-label="شماره سفارش"><?= $payment['order_id'] ?></td>
                                        <td data-label="مبلغ"><?= number_format($payment['amount']) ?> تومان</td>
                                        <td data-label="روش پرداخت">
                                            <span class="payment-method <?= $payment['payment_method'] === 'online' ? 'payment-online' : 'payment-cod' ?>">
                                                <?= $payment['payment_method'] === 'online' ? 'آنلاین' : 'پرداخت در محل' ?>
                                            </span>
                                        </td>
                                        <td data-label="تاریخ پرداخت"><?= date('Y/m/d H:i', strtotime($payment['payment_date'])) ?></td>
                                        <td data-label="وضعیت پرداخت">
                                            <span class="status-badge <?= $payment['order_payment_status'] === 'paid' ? 'status-successful' : 'status-failed' ?>">
                                                <?= $payment['order_payment_status'] === 'paid' ? 'پرداخت شده' : 'پرداخت نشده' ?>
                                            </span>
                                        </td>
                                        <td data-label="عملیات">
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#paymentModal<?= $payment['id'] ?>">
                                                <i class="fas fa-eye me-1"></i> مشاهده
                                            </button>
                                        </td>
                                    </tr>

                                    <div class="modal fade" id="paymentModal<?= $payment['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="margin-left: auto;"></button>
                                                    <h5 class="modal-title mx-auto">جزئیات پرداخت #<?= $payment['id'] ?></h5>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <p><strong>مشتری:</strong> <?= htmlspecialchars($payment['user_name']) ?></p>
                                                            <p><strong>ایمیل:</strong> <?= htmlspecialchars($payment['user_email']) ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>شماره سفارش:</strong> <?= $payment['order_id'] ?></p>
                                                            <p><strong>مبلغ سفارش:</strong> <?= number_format($payment['order_amount']) ?> تومان</p>
                                                        </div>
                                                    </div>

                                                    <div class="row mb-3">
                                                        <div class="col-md-6">
                                                            <p><strong>مبلغ پرداخت:</strong> <?= number_format($payment['amount']) ?> تومان</p>
                                                            <p><strong>روش پرداخت:</strong>
                                                                <span class="payment-method <?= $payment['payment_method'] === 'online' ? 'payment-online' : 'payment-cod' ?>">
                                                                    <?= $payment['payment_method'] === 'online' ? 'آنلاین' : 'پرداخت در محل' ?>
                                                                </span>
                                                            </p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>تاریخ پرداخت:</strong> <?= date('Y/m/d H:i', strtotime($payment['payment_date'])) ?></p>
                                                            <p><strong>وضعیت سفارش:</strong>
                                                                <?php
                                                                $order_status = [
                                                                    'pending' => 'در انتظار پرداخت',
                                                                    'processing' => 'در حال پردازش',
                                                                    'shipped' => 'ارسال شده',
                                                                    'completed' => 'تکمیل شده',
                                                                    'cancelled' => 'لغو شده'
                                                                ];
                                                                echo $order_status[$payment['order_status']] ?? $payment['order_status'];
                                                                ?>
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <form method="post" class="mt-4">
                                                        <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label for="new_status" class="form-label">تغییر وضعیت پرداخت:</label>
                                                                <select name="new_status" id="new_status" class="form-select">
                                                                    <option value="successful" <?= $payment['status'] === 'successful' ? 'selected' : '' ?>>موفق</option>
                                                                    <option value="failed" <?= $payment['status'] === 'failed' ? 'selected' : '' ?>>ناموفق</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 d-flex align-items-end">
                                                                <button type="submit" name="update_status" class="btn btn-primary w-100">
                                                                    <i class="fas fa-save me-2"></i> ذخیره وضعیت
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    <form method="post" class="mt-3">
                                                        <input type="hidden" name="order_id" value="<?= $payment['order_id'] ?>">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <label for="new_payment_status" class="form-label">تغییر وضعیت پرداخت سفارش:</label>
                                                                <select name="new_payment_status" id="new_payment_status" class="form-select">
                                                                    <option value="paid" <?= $payment['order_payment_status'] === 'paid' ? 'selected' : '' ?>>پرداخت شده</option>
                                                                    <option value="unpaid" <?= $payment['order_payment_status'] === 'unpaid' ? 'selected' : '' ?>>پرداخت نشده</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6 d-flex align-items-end">
                                                                <button type="submit" name="update_payment_status" class="btn btn-primary w-100">
                                                                    <i class="fas fa-save me-2"></i> ذخیره وضعیت پرداخت
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>" aria-label="قبلی">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);

                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>" aria-label="بعدی">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>

</html>
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

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$total_sales_query = "SELECT 
    SUM(total_price) as total_sales,
    COUNT(*) as total_orders,
    AVG(total_price) as avg_order_value
FROM Orders
WHERE created_at BETWEEN ? AND ?
AND status = 'completed'";
$stmt = $pdo->prepare($total_sales_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$payment_stats_query = "SELECT 
    payment_method,
    COUNT(*) as count,
    SUM(amount) as total_amount
FROM Payments
WHERE payment_date BETWEEN ? AND ?
AND status = 'successful'
GROUP BY payment_method";
$stmt = $pdo->prepare($payment_stats_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$payment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$top_products_query = "SELECT 
    p.name as product_name,
    SUM(od.quantity) as total_quantity,
    SUM(od.subtotal) as total_revenue
FROM OrderDetails od
JOIN Products p ON od.product_id = p.id
JOIN Orders o ON od.order_id = o.id
WHERE o.created_at BETWEEN ? AND ?
AND o.status = 'completed'
GROUP BY od.product_id
ORDER BY total_revenue DESC
LIMIT 10";
$stmt = $pdo->prepare($top_products_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$daily_sales_query = "SELECT 
    DATE(created_at) as date,
    SUM(total_price) as daily_sales,
    COUNT(*) as daily_orders
FROM Orders
WHERE created_at BETWEEN ? AND ?
AND status = 'completed'
GROUP BY DATE(created_at)
ORDER BY DATE(created_at)";
$stmt = $pdo->prepare($daily_sales_query);
$stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_sales = [];
$chart_orders = [];

foreach ($daily_sales as $day) {
    $chart_labels[] = date('j M', strtotime($day['date']));
    $chart_sales[] = $day['daily_sales'];
    $chart_orders[] = $day['daily_orders'];
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات مالی</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style-financial-reports.css">
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
                    <i class="fas fa-chart-line me-2"></i>
                    گزارشات مالی
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
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>فیلتر تاریخ</h5>
            </div>
            <div class="card-body">
                <form method="get" action="financial-reports.php">
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label for="start_date" class="form-label">از تاریخ:</label>
                            <input type="date" id="start_date" name="start_date" class="form-control" 
                                   value="<?= htmlspecialchars($start_date) ?>" required>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="end_date" class="form-label">تا تاریخ:</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" 
                                   value="<?= htmlspecialchars($end_date) ?>" required>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> اعمال
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($sales_stats['total_sales'] ?? 0) ?> تومان</div>
                    <div class="stat-label">فروش کل</div>
                    <small class="text-muted"><?= htmlspecialchars($start_date) ?> تا <?= htmlspecialchars($end_date) ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($sales_stats['total_orders'] ?? 0) ?></div>
                    <div class="stat-label">تعداد سفارشات</div>
                    <small class="text-muted">سفارشات تکمیل شده</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($sales_stats['avg_order_value'] ?? 0) ?> تومان</div>
                    <div class="stat-label">میانگین ارزش سفارش</div>
                    <small class="text-muted">میانگین هر سفارش</small>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>نمودار فروش روزانه</h5>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>روش‌های پرداخت</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>روش پرداخت</th>
                                <th>تعداد پرداخت‌ها</th>
                                <th>مبلغ کل</th>
                                <th>درصد از کل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_payments = array_sum(array_column($payment_stats, 'total_amount'));
                            foreach ($payment_stats as $payment): 
                                $percentage = $total_payments > 0 ? round(($payment['total_amount'] / $total_payments) * 100, 2) : 0;
                            ?>
                                <tr>
                                    <td><?= $payment['payment_method'] == 'online' ? 'آنلاین' : 'پرداخت در محل' ?></td>
                                    <td><?= number_format($payment['count']) ?></td>
                                    <td><?= number_format($payment['total_amount']) ?> تومان</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= $percentage ?>%;" 
                                                 aria-valuenow="<?= $percentage ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= $percentage ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($payment_stats)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">هیچ پرداختی در این بازه زمانی ثبت نشده است</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-star me-2"></i>پرفروش‌ترین محصولات</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>محصول</th>
                                <th>تعداد فروش</th>
                                <th>درآمد کل</th>
                                <th>درصد از کل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_revenue = array_sum(array_column($top_products, 'total_revenue'));
                            foreach ($top_products as $product): 
                                $percentage = $total_revenue > 0 ? round(($product['total_revenue'] / $total_revenue) * 100, 2) : 0;
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= number_format($product['total_quantity']) ?></td>
                                    <td><?= number_format($product['total_revenue']) ?> تومان</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?= $percentage ?>%;" 
                                                 aria-valuenow="<?= $percentage ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= $percentage ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($top_products)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">هیچ فروشی در این بازه زمانی ثبت نشده است</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mb-4">
            <a href="generate-report.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
               class="btn btn-primary btn-lg" target="_self">
                <i class="fas fa-file-pdf me-2"></i> دانلود گزارش PDF
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [
                        {
                            label: 'فروش روزانه (تومان)',
                            data: <?= json_encode($chart_sales) ?>,
                            backgroundColor: 'rgba(78, 115, 223, 0.5)',
                            borderColor: 'rgba(78, 115, 223, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'تعداد سفارشات',
                            data: <?= json_encode($chart_orders) ?>,
                            backgroundColor: 'rgba(28, 200, 138, 0.5)',
                            borderColor: 'rgba(28, 200, 138, 1)',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'مبلغ فروش (تومان)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'تعداد سفارشات'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            rtl: true
                        },
                        tooltip: {
                            rtl: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 0) {
                                        label += new Intl.NumberFormat('fa-IR').format(context.raw) + ' تومان';
                                    } else {
                                        label += new Intl.NumberFormat('fa-IR').format(context.raw) + ' سفارش';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: forbidden.php");
    exit();
}


$shop_name = '';
try {
    $stmt = $pdo->query("SELECT * FROM ShopSettings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $shop_name = $settings['shop_name'];
    }
} catch (PDOException $e) {
    $error = "خطا در دریافت تنظیمات فروشگاه: " . $e->getMessage();
}

$items_per_page = 20;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $items_per_page;

if (isset($_POST['add_discount'])) {
    $productId = (int)$_POST['product_id'];
    $discountType = $_POST['discount_type'];
    $discountValue = (float)$_POST['discount_value'];
    $startDate = $_POST['start_date'] ? $_POST['start_date'] : null;
    $endDate = $_POST['end_date'] ? $_POST['end_date'] : null;

    $stmt = $pdo->prepare("INSERT INTO Discounts (product_id, discount_type, discount_value, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$productId, $discountType, $discountValue, $startDate, $endDate]);
}

if (isset($_POST['delete_discount'])) {
    $discountId = (int)$_POST['delete_discount'];
    $stmt = $pdo->prepare("DELETE FROM Discounts WHERE id = ?");
    $stmt->execute([$discountId]);
}

if (isset($_POST['bulk_delete'])) {
    if (!empty($_POST['selected_discounts'])) {
        $placeholders = rtrim(str_repeat('?,', count($_POST['selected_discounts']), ','));
        $stmt = $pdo->prepare("DELETE FROM Discounts WHERE id IN ($placeholders)");
        $stmt->execute($_POST['selected_discounts']);
        $_SESSION['success'] = "تخفیف‌های انتخاب شده با موفقیت حذف شدند";
    }
}

if (isset($_POST['edit_discount'])) {
    $discountId = (int)$_POST['discount_id'];
    $productId = (int)$_POST['product_id'];
    $discountType = $_POST['discount_type'];
    $discountValue = (float)$_POST['discount_value'];
    $startDate = $_POST['start_date'] ? $_POST['start_date'] : null;
    $endDate = $_POST['end_date'] ? $_POST['end_date'] : null;

    $stmt = $pdo->prepare("UPDATE Discounts SET product_id = ?, discount_type = ?, discount_value = ?, start_date = ?, end_date = ? WHERE id = ?");
    $stmt->execute([$productId, $discountType, $discountValue, $startDate, $endDate, $discountId]);
}

$total_rows = $pdo->query("SELECT COUNT(*) FROM Discounts")->fetchColumn();
$total_pages = ceil($total_rows / $items_per_page);

$stmt = $pdo->prepare("
    SELECT Discounts.*, Products.name AS product_name, Products.image_url AS product_image
    FROM Discounts 
    JOIN Products ON Discounts.product_id = Products.id 
    ORDER BY Discounts.id DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query("SELECT id, name, image_url FROM Products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تخفیف‌ها</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-discounts.css">
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
                    <i class="fas fa-tag me-2"></i>
                    مدیریت تخفیف‌ها
                </h1>
                <div>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-store me-1"></i>
                        <?= htmlspecialchars($shop_name) ?>
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
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>افزودن تخفیف جدید</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="product_id" class="form-label fw-bold">محصول</label>
                            <div class="product-select-container">
                                <select id="product_id" name="product_id" class="form-select" required>
                                    <option value="">انتخاب محصول...</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" data-image="<?= htmlspecialchars($product['image_url']) ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="discount_type" class="form-label fw-bold">نوع تخفیف</label>
                            <select id="discount_type" name="discount_type" class="form-select" required>
                                <option value="percentage">درصدی</option>
                                <option value="fixed">مبلغ ثابت</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="discount_value" class="form-label fw-bold">مقدار تخفیف</label>
                            <div class="input-group">
                                <input type="number" id="discount_value" name="discount_value" class="form-control" required step="0.01" placeholder="مقدار">
                                <span class="input-group-text" id="discount-addon">%</span>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label fw-bold">تاریخ شروع</label>
                            <input type="datetime-local" id="start_date" name="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label fw-bold">تاریخ پایان</label>
                            <input type="datetime-local" id="end_date" name="end_date" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="add_discount" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i> ذخیره تخفیف
                    </button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>لیست تخفیف‌ها</h5>
                    <span class="badge bg-light text-dark">
                        تعداد کل: <?= $total_rows ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="bulkForm">
                    <div class="bulk-actions">
                        <button type="submit" name="bulk_delete" class="btn btn-danger" onclick="return confirm('آیا از حذف تخفیف‌های انتخاب شده مطمئن هستید؟')">
                            <i class="fas fa-trash me-2"></i> حذف انتخاب شده‌ها
                        </button>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">انتخاب همه</label>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th width="40px">#</th>
                                    <th>محصول</th>
                                    <th>نوع تخفیف</th>
                                    <th>مقدار</th>
                                    <th>تاریخ شروع</th>
                                    <th>تاریخ پایان</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($discounts) > 0): ?>
                                    <?php foreach ($discounts as $index => $discount): ?>
                                        <tr>
                                            <td data-label="#">
                                                <input type="checkbox" name="selected_discounts[]" value="<?= $discount['id'] ?>" class="form-check-input discount-checkbox">
                                                <?= ($current_page - 1) * $items_per_page + $index + 1 ?>
                                            </td>
                                            <td data-label="محصول">
                                                <div class="product-with-image">
                                                    <?php if (!empty($discount['product_image'])): ?>
                                                        <img src="<?= htmlspecialchars($discount['product_image']) ?>" alt="<?= htmlspecialchars($discount['product_name']) ?>" class="product-image">
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($discount['product_name']) ?>
                                                </div>
                                            </td>
                                            <td data-label="نوع تخفیف"><?= $discount['discount_type'] === 'percentage' ? 'درصدی' : 'ثابت' ?></td>
                                            <td data-label="مقدار">
                                                <?= $discount['discount_type'] === 'percentage' ?
                                                    $discount['discount_value'] . '%' :
                                                    number_format($discount['discount_value']) . ' تومان' ?>
                                            </td>
                                            <td data-label="تاریخ شروع"><?= $discount['start_date'] ?: '---' ?></td>
                                            <td data-label="تاریخ پایان"><?= $discount['end_date'] ?: '---' ?></td>
                                            <td data-label="عملیات">
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $discount['id'] ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <button type="submit" name="delete_discount" value="<?= $discount['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این تخفیف مطمئن هستید؟')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <div class="modal fade" id="editModal<?= $discount['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header d-flex align-items-center">
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        <h5 class="modal-title ms-2" id="orderDetailsModalLabel">ویرایش تخفیف</h5>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="discount_id" value="<?= $discount['id'] ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label for="edit_discount_type_<?= $discount['id'] ?>" class="form-label">نوع تخفیف</label>
                                                                <select id="edit_discount_type_<?= $discount['id'] ?>" name="discount_type" class="form-select" required>
                                                                    <option value="percentage" <?= $discount['discount_type'] === 'percentage' ? 'selected' : '' ?>>درصدی</option>
                                                                    <option value="fixed" <?= $discount['discount_type'] === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="edit_discount_value_<?= $discount['id'] ?>" class="form-label">مقدار تخفیف</label>
                                                                <input type="number" id="edit_discount_value_<?= $discount['id'] ?>" name="discount_value"
                                                                    class="form-control" value="<?= $discount['discount_value'] ?>" required step="0.01">
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_start_date_<?= $discount['id'] ?>" class="form-label">تاریخ شروع</label>
                                                                    <input type="datetime-local" id="edit_start_date_<?= $discount['id'] ?>" name="start_date"
                                                                        class="form-control" value="<?= $discount['start_date'] ?>">
                                                                </div>
                                                                <div class="col-md-6 mb-3">
                                                                    <label for="edit_end_date_<?= $discount['id'] ?>" class="form-label">تاریخ پایان</label>
                                                                    <input type="datetime-local" id="edit_end_date_<?= $discount['id'] ?>" name="end_date"
                                                                        class="form-control" value="<?= $discount['end_date'] ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                                                            <button type="submit" name="edit_discount" class="btn btn-primary">ذخیره تغییرات</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">هیچ تخفیفی ثبت نشده است.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $current_page - 1 ?>" aria-label="قبلی">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $current_page + 1 ?>" aria-label="بعدی">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-list-discounts.js"></script>

</body>

</html>
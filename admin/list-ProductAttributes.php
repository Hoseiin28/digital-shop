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
    $error = "خطا در دریافت تنظیمات: " . $e->getMessage();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 25;
$offset = ($page - 1) * $itemsPerPage;

$id = $product_id = $attribute_name = $attribute_value = '';
$errors = [];

$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, image_url FROM Products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست محصولات: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_delete'])) {
        if (!empty($_POST['selected_attributes'])) {
            $selected_ids = $_POST['selected_attributes'];
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

            try {
                $stmt = $pdo->prepare("DELETE FROM ProductAttributes WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                $_SESSION['success'] = "ویژگی‌های انتخاب شده با موفقیت حذف شدند.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "خطا در حذف ویژگی‌ها: " . $e->getMessage();
            }

            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        } else {
            $_SESSION['error'] = "هیچ ویژگی‌ای برای حذف انتخاب نشده است.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        }
    }

    $id = $_POST['id'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $attribute_name = trim($_POST['attribute_name'] ?? '');
    $attribute_value = trim($_POST['attribute_value'] ?? '');

    if (empty($product_id)) {
        $errors[] = "انتخاب محصول الزامی است.";
    }
    if (empty($attribute_name)) {
        $errors[] = "نام ویژگی الزامی است.";
    }
    if (empty($attribute_value)) {
        $errors[] = "مقدار ویژگی الزامی است.";
    }

    if (empty($errors)) {
        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO ProductAttributes (product_id, attribute_name, attribute_value) 
                                     VALUES (?, ?, ?)");
                $stmt->execute([$product_id, $attribute_name, $attribute_value]);
                $_SESSION['success'] = "ویژگی جدید با موفقیت اضافه شد.";
            } else {
                $stmt = $pdo->prepare("UPDATE ProductAttributes 
                                     SET product_id = ?, attribute_name = ?, attribute_value = ?
                                     WHERE id = ?");
                $stmt->execute([$product_id, $attribute_name, $attribute_value, $id]);
                $_SESSION['success'] = "ویژگی با موفقیت به‌روزرسانی شد.";
            }
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        } catch (PDOException $e) {
            $errors[] = "خطا در ذخیره‌سازی اطلاعات: " . $e->getMessage();
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM ProductAttributes WHERE id = ?");
        $stmt->execute([$edit_id]);
        $attribute = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attribute) {
            $id = $attribute['id'];
            $product_id = $attribute['product_id'];
            $attribute_name = $attribute['attribute_name'];
            $attribute_value = $attribute['attribute_value'];
        } else {
            $_SESSION['error'] = "ویژگی مورد نظر یافت نشد.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "خطا در دریافت اطلاعات ویژگی: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM ProductAttributes WHERE id = ?");
        $stmt->execute([$delete_id]);
        $_SESSION['success'] = "ویژگی با موفقیت حذف شد.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "خطا در حذف ویژگی: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
    exit;
}

$attributes = [];
$totalCount = 0;
try {
    $query = "SELECT pa.*, p.name as product_name 
              FROM ProductAttributes pa
              JOIN Products p ON pa.product_id = p.id
              ORDER BY p.name, pa.attribute_name
              LIMIT :offset, :itemsPerPage";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalQuery = "SELECT COUNT(*) AS total FROM ProductAttributes";
    $totalCount = $pdo->query("SELECT COUNT(*) AS total FROM ProductAttributes")->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalCount / $itemsPerPage);

    $range = 2;
    $startPage = max(1, $page - $range);
    $endPage = min($totalPages, $page + $range);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست ویژگی‌ها: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت ویژگی‌های محصولات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style-list-ProductAttributes.css">
    <?php if (!empty($settings['logo_url'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($settings['logo_url']) ?>" type="image/png">
    <?php endif; ?>
    <style>
        :root {
            --primary-color: <?= $settings['button_color'] ?? '#4e73df' ?>;
            --secondary-color: #f8f9fc;
            --dark-color: #5a5c69;
            --light-color: #f8f9fa;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: <?= $settings['font_family'] ?? 'Vazir, sans-serif' ?>;
            background-color: #f8f9fc;
            color: #333;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--primary-color);
            color: white;
        }

        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #ddd;
        }

        .select2-container .select2-selection--single {
            height: 38px;
            padding: 5px;
        }
    </style>
</head>

<body>

    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-tags me-2"></i>
                    مدیریت ویژگی‌های محصولات
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
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>خطا!</strong>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

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

        <div class="row">
            <div class="col-lg-5">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-<?= empty($id) ? 'plus' : 'edit' ?> me-2"></i>
                            <?= empty($id) ? 'افزودن ویژگی جدید' : 'ویرایش ویژگی' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

                            <div class="mb-3">
                                <label for="product_id" class="form-label">محصول <span class="text-danger">*</span></label>
                                <select class="form-select" id="product_id" name="product_id" required size="5">
                                    <option value="">-- انتخاب محصول --</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" <?= $product_id == $product['id'] ? 'selected' : '' ?>
                                            data-image="<?= htmlspecialchars($product['image_url']) ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="product-image-preview" class="mt-2 text-center">
                                    <?php if ($product_id): ?>
                                        <?php
                                        $selected_product_image = '';
                                        foreach ($products as $product) {
                                            if ($product['id'] == $product_id) {
                                                $selected_product_image = $product['image_url'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <img src="<?= htmlspecialchars($selected_product_image) ?>" alt="تصویر محصول" class="img-thumbnail" style="max-height: 100px;">
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="attribute_name" class="form-label">نام ویژگی <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="attribute_name" name="attribute_name" required
                                        value="<?= htmlspecialchars($attribute_name) ?>" placeholder="مثال: رنگ، اندازه، وزن">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="attribute_value" class="form-label">مقدار ویژگی <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="attribute_value" name="attribute_value" required
                                        value="<?= htmlspecialchars($attribute_value) ?>" placeholder="مثال: قرمز، 15 اینچ، 500 گرم">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <?php if (!empty($id)): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] ?>?page=<?= $page ?>" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-times me-1"></i> انصراف
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?= empty($id) ? 'ذخیره ویژگی' : 'به‌روزرسانی' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>
                                لیست ویژگی‌ها
                            </h5>
                            <div class="text-muted small">
                                <p>
                                    نمایش <?= count($attributes) ?> از <?= $totalCount ?> ویژگی
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attributes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tag fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ ویژگی‌ای یافت نشد</h5>
                                <p class="text-muted">برای افزودن ویژگی جدید از فرم سمت چپ استفاده کنید</p>
                            </div>
                        <?php else: ?>
                            <form id="bulkForm" method="post">
                                <div class="bulk-actions mb-3" id="bulkActions">
                                    <div class="d-flex align-items-center">
                                        <span class="me-3">عملیات گروهی:</span>
                                        <button type="submit" name="bulk_delete" class="btn btn-danger btn-sm me-2"
                                            onclick="return confirm('آیا از حذف ویژگی‌های انتخاب شده مطمئن هستید؟');">
                                            <i class="fas fa-trash me-1"></i> حذف انتخاب‌ها
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelBulk">
                                            <i class="fas fa-times me-1"></i> انصراف
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="50">
                                                    <input type="checkbox" id="selectAll" class="select-all-checkbox">
                                                </th>
                                                <th width="50">#</th>
                                                <th>محصول</th>
                                                <th>ویژگی</th>
                                                <th>مقدار</th>
                                                <th width="120">عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attributes as $index => $attr): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_attributes[]" value="<?= $attr['id'] ?>" class="attribute-checkbox">
                                                    </td>
                                                    <td><?= $offset + $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($attr['product_name']) ?></td>
                                                    <td><?= htmlspecialchars($attr['attribute_name']) ?></td>
                                                    <td><span class="badge bg-primary"><?= htmlspecialchars($attr['attribute_value']) ?></span></td>
                                                    <td class="text-nowrap">
                                                        <a href="?edit=<?= $attr['id'] ?>&page=<?= $page ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?delete=<?= $attr['id'] ?>&page=<?= $page ?>" class="btn btn-sm btn-outline-danger" title="حذف"
                                                            onclick="return confirm('آیا از حذف ویژگی «<?= htmlspecialchars($attr['attribute_name']) ?>» مطمئن هستید؟');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>

                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="صفحه‌بندی" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="قبلی">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>

                                        <?php if ($startPage > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1">1</a>
                                            </li>
                                            <?php if ($startPage > 2): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($endPage < $totalPages): ?>
                                            <?php if ($endPage < $totalPages - 1): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
                                            </li>
                                        <?php endif; ?>

                                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="بعدی">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script-list-ProductAttributes.js"></script>
</body>

</html>
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
$itemsPerPage = 20;
$offset = ($page - 1) * $itemsPerPage;

$id = $name = $category_id = $price = $description = $stock = $image_url = '';
$errors = [];

$categories = [];
try {
    $stmt = $pdo->query("
        SELECT c.id, c.name 
        FROM Categories c
        LEFT JOIN Articles a ON c.id = a.category
        WHERE a.category IS NULL
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست دسته‌بندی‌ها: " . $e->getMessage();
}

$image_url = '../image/default-Product.jpg';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['bulk_delete']) && !empty($_POST['selected_products'])) {
        $selected_products = $_POST['selected_products'];
        try {
            $pdo->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($selected_products), '?'));

            $stmt = $pdo->prepare("DELETE FROM Discounts WHERE product_id IN ($placeholders)");
            $stmt->execute($selected_products);

            $stmt = $pdo->prepare("DELETE FROM Reviews WHERE product_id IN ($placeholders)");
            $stmt->execute($selected_products);

            $stmt = $pdo->prepare("DELETE FROM Products WHERE id IN ($placeholders)");
            $stmt->execute($selected_products);

            $pdo->commit();

            $_SESSION['success'] = count($selected_products) . " محصول با موفقیت حذف شدند.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "خطا در حذف گروهی محصولات: " . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        }
    }

    $id = $_POST['id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $price = trim($_POST['price'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $stock = trim($_POST['stock'] ?? '0');

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image_file']['tmp_name'];
        $fileName = $_FILES['image_file']['name'];
        $fileSize = $_FILES['image_file']['size'];
        $fileType = $_FILES['image_file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = '../image/products-images/uploads/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $image_url = '../image/products-images/uploads/' . $newFileName;
            } else {
                $errors[] = 'خطا در بارگذاری فایل تصویر.';
            }
        } else {
            $errors[] = 'فرمت فایل تصویر مجاز نیست. (jpg, jpeg, png, gif, webp)';
        }
    } else {
        if (!empty($_POST['image_url'])) {
            $image_url = trim($_POST['image_url']);
        }
    }

    if (empty($name)) {
        $errors[] = "نام محصول الزامی است.";
    }
    if (empty($category_id)) {
        $errors[] = "انتخاب دسته‌بندی الزامی است.";
    }
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "قیمت محصول باید یک عدد مثبت باشد.";
    }
    if (!is_numeric($stock) || $stock < 0) {
        $errors[] = "تعداد موجودی باید یک عدد صحیح غیرمنفی باشد.";
    }

    if (empty($errors)) {
        try {
            if (empty($id)) {
                $stmt = $pdo->prepare("INSERT INTO Products (name, category_id, price, description, stock, image_url) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category_id, $price, $description, $stock, $image_url]);
                $_SESSION['success'] = "محصول با موفقیت ایجاد شد.";
            } else {
                $stmt = $pdo->prepare("UPDATE Products SET name = ?, category_id = ?, price = ?, description = ?, 
                                      stock = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP 
                                      WHERE id = ?");
                $stmt->execute([$name, $category_id, $price, $description, $stock, $image_url, $id]);
                $_SESSION['success'] = "محصول با موفقیت به‌روزرسانی شد.";
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
        $stmt = $pdo->prepare("SELECT * FROM Products WHERE id = ?");
        $stmt->execute([$edit_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $id = $product['id'];
            $name = $product['name'];
            $category_id = $product['category_id'];
            $price = $product['price'];
            $description = $product['description'];
            $stock = $product['stock'];
            $image_url = $product['image_url'];
        } else {
            $_SESSION['error'] = "محصول مورد نظر یافت نشد.";
            header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "خطا در دریافت اطلاعات محصول: " . $e->getMessage();
    }
}

if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM Discounts WHERE product_id = ?");
        $stmt->execute([$delete_id]);

        $stmt = $pdo->prepare("DELETE FROM Reviews WHERE product_id = ?");
        $stmt->execute([$delete_id]);

        $stmt = $pdo->prepare("DELETE FROM Products WHERE id = ?");
        $stmt->execute([$delete_id]);

        $pdo->commit();

        $_SESSION['success'] = "محصول با موفقیت حذف شد.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "خطا در حذف محصول: " . $e->getMessage();
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . $page);
    exit;
}

$allProducts = [];
try {
    $query = "SELECT p.id, p.name, p.price, p.stock, p.image_url, c.name as category_name 
              FROM Products p 
              JOIN Categories c ON p.category_id = c.id 
              ORDER BY p.name
              LIMIT :offset, :itemsPerPage";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':itemsPerPage', $itemsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalQuery = "SELECT COUNT(*) AS total FROM Products";
    $totalCount = $pdo->query($totalQuery)->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalCount / $itemsPerPage);
} catch (PDOException $e) {
    $errors[] = "خطا در دریافت لیست محصولات: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت محصولات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style-list-products.css">
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
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-boxes me-2"></i>
                    مدیریت محصولات
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
                            <?= empty($id) ? 'افزودن محصول جدید' : 'ویرایش محصول' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">

                            <div class="mb-3">
                                <label for="name" class="form-label">نام محصول <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required
                                    value="<?= htmlspecialchars($name) ?>" placeholder="نام محصول">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">دسته‌بندی <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">-- انتخاب کنید --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($category['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">قیمت (تومان) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="price" name="price" required
                                        value="<?= htmlspecialchars($price) ?>" placeholder="قیمت محصول">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="stock" class="form-label">موجودی</label>
                                    <input type="number" class="form-control" id="stock" name="stock"
                                        value="<?= htmlspecialchars($stock) ?>" placeholder="تعداد موجودی">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="image_file" class="form-label">تصویر محصول</label>
                                    <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">توضیحات محصول</label>
                                <textarea class="form-control" id="description" name="description" rows="3"
                                    placeholder="توضیحات محصول..."><?= htmlspecialchars($description) ?></textarea>
                            </div>

                            <?php if (!empty($image_url)): ?>
                                <div class="mb-3 text-center">
                                    <img src="<?= htmlspecialchars($image_url) ?>" alt="تصویر محصول" id="image_preview" class="img-thumbnail">
                                    <input type="hidden" name="image_url" value="<?= htmlspecialchars($image_url) ?>">
                                    <div class="mt-2">
                                        <a href="<?= htmlspecialchars($image_url) ?>" target="_self" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye me-1"></i> مشاهده تصویر
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end mt-4">
                                <?php if (!empty($id)): ?>
                                    <a href="<?= $_SERVER['PHP_SELF'] . '?page=' . $page ?>" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-times me-1"></i> انصراف
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    <?= empty($id) ? 'ذخیره محصول' : 'به‌روزرسانی' ?>
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
                                لیست محصولات
                            </h5>
                            <div class="text-muted small">
                                <p>
                                    نمایش <?= count($allProducts) ?> از <?= $totalCount ?> محصول
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($allProducts)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">هیچ محصولی یافت نشد</h5>
                                <p class="text-muted">برای افزودن محصول جدید از فرم سمت چپ استفاده کنید</p>
                            </div>
                        <?php else: ?>
                            <div class="bulk-actions" id="bulkActions">
                                <form method="post" id="bulkForm">
                                    <div class="d-flex align-items-center">
                                        <span class="me-3" id="selectedCount">0 محصول انتخاب شده</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger me-2" id="deleteSelected">
                                            <i class="fas fa-trash me-1"></i> حذف انتخاب شده‌ها
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelBulk">
                                            <i class="fas fa-times me-1"></i> انصراف
                                        </button>
                                        <input type="hidden" name="bulk_delete" value="1">
                                    </div>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" id="selectAll" class="form-check-input product-checkbox">
                                            </th>
                                            <th>نام محصول</th>
                                            <th>دسته‌بندی</th>
                                            <th width="120">قیمت</th>
                                            <th width="100">موجودی</th>
                                            <th width="120">عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="aa">
                                        <?php foreach ($allProducts as $index => $product): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="selected_products[]" value="<?= $product['id'] ?>"
                                                        class="form-check-input product-checkbox product-check">
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars(!empty($product['image_url']) ? $product['image_url'] : '../image/default-Product.jpg') ?>" alt="تصویر محصول" class="product-image me-3">
                                                        <div>
                                                            <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                                            <small class="text-muted">ID: <?= $product['id'] ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                                <td><?= number_format($product['price']) ?> تومان</td>
                                                <td>
                                                    <?php if ($product['stock'] > 0): ?>
                                                        <span class="badge bg-success"><?= $product['stock'] ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">ناموجود</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-nowrap">
                                                    <a href="?edit=<?= $product['id'] ?>&page=<?= $page ?>" class="btn btn-sm btn-outline-primary" title="ویرایش">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="?delete=<?= $product['id'] ?>&page=<?= $page ?>" class="btn btn-sm btn-outline-danger" title="حذف"
                                                        onclick="return confirm('آیا از حذف محصول «<?= htmlspecialchars($product['name']) ?>» مطمئن هستید؟');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
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
                                            <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="قبلی">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                        <?php endfor; ?>

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
    <script src="../assets/js/script-list-product.js"></script>
</body>

</html>
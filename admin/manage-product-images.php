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

function createSafeDirectoryName($name)
{
    $name = preg_replace('/[^A-Za-z0-9_\x{0600}-\x{06FF}\- ]/u', '', $name);
    $name = str_replace(' ', '_', trim($name));
    $name = preg_replace('/_+/', '_', $name);
    return $name;
}

$products = [];
try {
    $stmt = $pdo->query("SELECT id, name, image_url FROM Products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطا در دریافت لیست محصولات: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_image'])) {
        $product_id = $_POST['product_id'];

        if (empty($product_id)) {
            $error = "لطفاً یک محصول انتخاب کنید";
        } elseif (empty($_FILES['product_image']['name'][0])) {
            $error = "لطفاً حداقل یک فایل تصویر انتخاب کنید";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_file_size = 5 * 1024 * 1024;
            $files_count = count($_FILES['product_image']['name']);
            $success = 0;
            $error_count = 0;

            try {
                $stmt = $pdo->prepare("SELECT name FROM Products WHERE id = ?");
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new Exception("محصول مورد نظر یافت نشد");
                }

                $product_name = $product['name'];
                $safe_dir_name = createSafeDirectoryName($product_name);

                $upload_dir = '../image/products-images/' . $safe_dir_name;

                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception("خطا در ایجاد پوشه محصول");
                    }
                }

                for ($i = 0; $i < $files_count; $i++) {
                    $file_name = $_FILES['product_image']['name'][$i];
                    $file_type = $_FILES['product_image']['type'][$i];
                    $file_size = $_FILES['product_image']['size'][$i];
                    $file_tmp = $_FILES['product_image']['tmp_name'][$i];
                    $file_error = $_FILES['product_image']['error'][$i];

                    if ($file_error !== UPLOAD_ERR_OK) {
                        $error_count++;
                        continue;
                    }

                    if (!in_array($file_type, $allowed_types)) {
                        $error_count++;
                        continue;
                    }

                    if ($file_size > $max_file_size) {
                        $error_count++;
                        continue;
                    }

                    if (!getimagesize($file_tmp)) {
                        $error_count++;
                        continue;
                    }

                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $unique_name = uniqid('img_', true) . '.' . $file_ext;
                    $target_path = $upload_dir . '/' . $unique_name;

                    if (move_uploaded_file($file_tmp, $target_path)) {
                        try {
                            $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $target_path);
                            $stmt = $pdo->prepare("INSERT INTO ProductImages (product_id, image_url) VALUES (?, ?)");
                            $stmt->execute([$product_id, $relative_path]);
                            $success++;
                        } catch (PDOException $e) {
                            unlink($target_path);
                            $error = "خطا در ذخیره اطلاعات تصویر: " . $e->getMessage();
                        }
                    } else {
                        $error_count++;
                    }
                }

                if ($success > 0) {
                    $_SESSION['success'] = "$success تصویر با موفقیت اضافه شد.";
                }
                if ($error_count > 0) {
                    $_SESSION['error'] = "$error_count تصویر به دلیل نامعتبر بودن یا خطا در آپلود اضافه نشد.";
                }

                header("Location: " . $_SERVER['PHP_SELF'] . "?product_id=" . $product_id);
                exit();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_image'])) {
        $image_id = $_POST['image_id'];
        $product_id = $_POST['product_id'];

        try {
            $stmt = $pdo->prepare("SELECT image_url FROM ProductImages WHERE id = ?");
            $stmt->execute([$image_id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($image) {
                $stmt = $pdo->prepare("DELETE FROM ProductImages WHERE id = ?");
                $stmt->execute([$image_id]);

                $file_path = $_SERVER['DOCUMENT_ROOT'] . $image['image_url'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }

                $_SESSION['success'] = "تصویر با موفقیت حذف شد";

                header("Location: " . $_SERVER['PHP_SELF'] . "?product_id=" . $product_id);
                exit();
            } else {
                $error = "تصویر مورد نظر یافت نشد";
            }
        } catch (PDOException $e) {
            $error = "خطا در حذف تصویر: " . $e->getMessage();
        }
    }
}

$product_id = $_GET['product_id'] ?? ($_POST['product_id'] ?? null);
$images = [];

if ($product_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT pi.id, pi.image_url, p.name AS product_name 
            FROM ProductImages pi
            JOIN Products p ON pi.product_id = p.id
            WHERE pi.product_id = ?
            ORDER BY pi.created_at DESC
        ");
        $stmt->execute([$product_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "خطا در دریافت تصاویر محصول: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت تصاویر محصولات</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/style-manage-product-images.css">
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
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
    </style>
</head>

<body>
    <header class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <i class="fas fa-images me-2"></i>
                    مدیریت تصاویر محصولات
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
                <?= htmlspecialchars($error) ?>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            لیست محصولات
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="product-list-container">
                            <?php foreach ($products as $product): ?>
                                <a href="?product_id=<?= $product['id'] ?>" class="product-item <?= ($product_id == $product['id']) ? 'selected' : '' ?>">
                                    <img src="<?= htmlspecialchars($product['image_url'] ?? 'image/default-Product.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <span class="product-name"><?= htmlspecialchars($product['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if ($product_id): ?>
                    <?php 
                        $selected_product = null;
                        foreach ($products as $product) {
                            if ($product['id'] == $product_id) {
                                $selected_product = $product;
                                break;
                            }
                        }
                    ?>
                    
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-cube me-2"></i>
                                <?= htmlspecialchars($selected_product['name'] ?? 'محصول') ?>
                            </h5>
                            <span class="badge bg-primary"><?= count($images) ?> تصویر</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <img src="<?= htmlspecialchars($selected_product['image_url'] ?? '../image/default-Product.jpg') ?>" 
                                         alt="<?= htmlspecialchars($selected_product['name'] ?? 'محصول') ?>" 
                                         class="img-thumbnail w-100 mb-3">
                                </div>
                                <div class="col-md-8">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">

                                        <div class="mb-3">
                                            <div class="file-input-container">
                                                <label class="file-input-label">
                                                    <i class="fas fa-cloud-upload-alt"></i>
                                                    <span>برای آپلود تصاویر اینجا کلیک کنید یا فایل‌ها را بکشید</span>
                                                    <small>فرمت‌های مجاز: JPG, PNG, GIF, WEBP - حداکثر حجم هر فایل: 5MB</small>
                                                    <input type="file" id="product_image" name="product_image[]" class="file-input" accept="image/*" multiple required>
                                                </label>
                                            </div>
                                            <div id="file-list-container" class="mt-3" style="display: none;">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <span id="file-count">0</span> فایل انتخاب شده است
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" name="add_image" class="btn btn-primary">
                                                <i class="fas fa-upload me-1"></i> آپلود تصاویر
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-images me-2"></i>
                                تصاویر محصول
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($images)): ?>
                                <div class="empty-state text-center py-5">
                                    <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">تصویری برای این محصول یافت نشد</p>
                                </div>
                            <?php else: ?>
                                <div class="image-gallery">
                                    <?php foreach ($images as $image): ?>
                                        <div class="image-card">
                                            <img src="<?= htmlspecialchars($image['image_url']) ?>" alt="<?= htmlspecialchars($image['product_name']) ?>">
                                            <div class="image-actions">
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="image_id" value="<?= htmlspecialchars($image['id']) ?>">
                                                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
                                                    <button type="submit" name="delete_image" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('آیا از حذف این تصویر مطمئن هستید؟')">
                                                        <i class="fas fa-trash-alt me-1"></i> حذف
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-image fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">محصولی انتخاب نشده است</h5>
                            <p class="text-muted">لطفاً از لیست سمت راست یک محصول را انتخاب کنید</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#product_image').change(function() {
            const files = this.files;
            if (files.length > 0) {
                $('#file-count').text(files.length);
                $('#file-list-container').show();
            } else {
                $('#file-list-container').hide();
            }
        });
        
        const selectedItem = document.querySelector('.product-item.selected');
        if (selectedItem) {
            selectedItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    </script>
</body>
</html>